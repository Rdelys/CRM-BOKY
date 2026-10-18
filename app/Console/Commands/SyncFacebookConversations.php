<?php

namespace App\Console\Commands;

use App\Models\Contact;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncFacebookConversations extends Command
{
    protected $signature = 'crm:sync-facebook {--limit=200 : Nombre max de conversations}';

    protected $description = 'Importe les personnes qui ont écrit à la Page Facebook';

    public function handle(): int
    {
        $cfg = config('crm.facebook');

        if (!$cfg['page_token'] || !$cfg['page_id']) {
            $this->error('FB_PAGE_TOKEN ou FB_PAGE_ID manquant dans le .env');

            return self::FAILURE;
        }

        $url = "{$cfg['graph']}/me/conversations";
        $params = ['fields' => 'participants,updated_time', 'limit' => 50];
        $limit = (int) $this->option('limit');
        $seen = 0;
        $created = 0;
        $updated = 0;

        do {
            $response = Http::timeout(30)->withToken($cfg['page_token'])->get($url, $params);

            if ($response->failed()) {
                $this->error('Facebook : HTTP ' . $response->status() . ' ' . $response->body());

                return self::FAILURE;
            }

            foreach ($response->json('data', []) as $conversation) {
                if ($seen++ >= $limit) {
                    break 2;
                }

                $lastAt = Carbon::parse($conversation['updated_time'])->setTimezone(config('app.timezone'));

                foreach ($conversation['participants']['data'] ?? [] as $p) {
                    $psid = (string) ($p['id'] ?? '');

                    if ($psid === '' || $psid === (string) $cfg['page_id']) {
                        continue;
                    }

                    $contact = Contact::firstOrNew(['facebook_psid' => $psid]);
                    $isNew = !$contact->exists;

                    if ($isNew) {
                        $contact->fill([
                            'name'   => mb_substr($p['name'] ?? 'Contact Facebook', 0, 120),
                            'source' => 'facebook',
                            'status' => 'prospect',
                        ]);
                    }

                    if (!$contact->last_fb_interaction_at || $lastAt->gt($contact->last_fb_interaction_at)) {
                        $contact->last_fb_interaction_at = $lastAt;
                    }

                    $contact->save();
                    $isNew ? $created++ : $updated++;
                }
            }

            $url = $response->json('paging.next');
            $params = [];
        } while ($url);

        $this->info("Synchro Facebook OK : {$created} créés, {$updated} mis à jour.");

        return self::SUCCESS;
    }
}