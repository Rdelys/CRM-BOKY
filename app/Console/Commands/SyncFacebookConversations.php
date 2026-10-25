<?php

namespace App\Console\Commands;

use App\Models\Contact;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class SyncFacebookConversations extends Command
{
    protected $signature = 'crm:sync-facebook
        {--full : Relit toutes les conversations (ignore le dernier point de synchro)}
        {--limit=0 : Nombre max de conversations (0 = illimité)}
        {--run= : Identifiant de lancement (fourni par le bouton manuel du CRM)}';

    protected $description = 'Importe les personnes qui ont écrit à la Page Facebook';

    private const CACHE_KEY = 'crm.facebook.last_sync';
    public const STATUS_KEY = 'crm.facebook.status';
    private const LOCK_KEY  = 'crm.facebook.sync.lock';

    /** État affiché dans le toast du CRM. */
    private array $status = [];

    public function handle(): int
    {
        $manual = (bool) $this->option('run');

        $this->status = [
            'run_id'      => $this->option('run') ?: (string) Str::uuid(),
            'trigger'     => $manual ? 'manual' : 'schedule',
            'state'       => 'running',
            'created'     => 0,
            'updated'     => 0,
            'errors'      => 0,
            'seen'        => 0,
            'pages'       => 0,
            'complete'    => true,
            'message'     => null,
            'started_at'  => now()->toIso8601String(),
            'finished_at' => null,
        ];

        // Une seule synchro à la fois (planifiée ou manuelle)
        $lock = Cache::lock(self::LOCK_KEY, 660);

        if (!$lock->get()) {
            $this->warn('Une synchronisation est déjà en cours.');

            if ($manual) {
                $this->report([
                    'state'       => 'failed',
                    'message'     => 'Une synchronisation est déjà en cours.',
                    'finished_at' => now()->toIso8601String(),
                ]);
            }

            return self::SUCCESS;
        }

        try {
            $this->report([]);

            return $this->sync();
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->report([
                'state'       => 'failed',
                'message'     => 'Erreur : ' . Str::limit($e->getMessage(), 200),
                'finished_at' => now()->toIso8601String(),
            ]);

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    private function sync(): int
    {
        $cfg = config('crm.facebook');
        $pageId = (string) $cfg['page_id'];

        if (!$cfg['page_token'] || $pageId === '') {
            $this->error('FB_PAGE_TOKEN ou FB_PAGE_ID manquant dans le .env');
            $this->report([
                'state'       => 'failed',
                'message'     => 'FB_PAGE_TOKEN ou FB_PAGE_ID manquant dans le .env',
                'finished_at' => now()->toIso8601String(),
            ]);

            return self::FAILURE;
        }

        $startedAt = now()->toIso8601String();
        $last = $this->option('full') ? null : Cache::get(self::CACHE_KEY);
        // Léger chevauchement (5 min) : les mises à jour sont sans danger si répétées
        $since = $last ? Carbon::parse($last)->subMinutes(5) : null;
        $limit = (int) $this->option('limit');

        $url = "{$cfg['graph']}/me/conversations";
        $params = [
            'fields' => 'participants,updated_time,messages.limit(10){from,created_time}',
            'limit'  => 25,
        ];

        $deadline = now()->addMinutes(5);
        $pages = 0;
        $seen = 0;
        $created = 0;
        $updated = 0;
        $errors = 0;
        $complete = true;

        do {
            // Garde-fous : jamais plus de 5 minutes ni 100 pages
            if (now()->gt($deadline) || ++$pages > 100) {
                $this->warn('Arrêt de sécurité (durée ou nombre de pages dépassé).');
                $complete = false;
                break;
            }

            $response = Http::timeout(30)->withToken($cfg['page_token'])->get($url, $params);

            if ($response->failed()) {
                $this->error('Facebook : HTTP ' . $response->status() . ' ' . $response->body());
                $this->report([
                    'state'       => 'failed',
                    'created'     => $created,
                    'updated'     => $updated,
                    'errors'      => $errors,
                    'seen'        => $seen,
                    'pages'       => $pages,
                    'message'     => 'Facebook a refusé la requête (HTTP ' . $response->status() . ') : '
                        . Str::limit((string) ($response->json('error.message') ?? 'erreur inconnue'), 160),
                    'finished_at' => now()->toIso8601String(),
                ]);

                return self::FAILURE; // le point de synchro n'avance pas
            }

            $data = $response->json('data', []);
            $this->line("Page {$pages} : " . count($data) . ' conversation(s)');

            // Plus rien à lire : on s'arrête, même si Meta renvoie encore un lien « suivant »
            if (empty($data)) {
                break;
            }

            foreach ($data as $conversation) {
                // Les conversations arrivent de la plus récente à la plus ancienne
                if ($since && Carbon::parse($conversation['updated_time'])->lt($since)) {
                    break 2;
                }

                if ($limit > 0 && $seen >= $limit) {
                    $complete = false;
                    break 2;
                }
                $seen++;

                // Date du dernier message écrit par la personne (pas par la Page)
                $lastUserMessageAt = null;
                foreach ($conversation['messages']['data'] ?? [] as $m) {
                    if ((string) ($m['from']['id'] ?? '') !== $pageId) {
                        $lastUserMessageAt = Carbon::parse($m['created_time'])->setTimezone(config('app.timezone'));
                        break;
                    }
                }

                foreach ($conversation['participants']['data'] ?? [] as $p) {
                    $psid = (string) ($p['id'] ?? '');

                    if ($psid === '' || $psid === $pageId) {
                        continue;
                    }

                    try {
                        $contact = Contact::firstOrNew(['facebook_psid' => $psid]);
                        $isNew = !$contact->exists;

                        if ($isNew) {
                            $contact->fill([
                                'name'   => mb_substr($p['name'] ?? 'Contact Facebook', 0, 120),
                                'source' => 'facebook',
                                'status' => 'prospect',
                            ]);
                        }

                        if ($lastUserMessageAt
                            && (!$contact->last_fb_interaction_at || $lastUserMessageAt->gt($contact->last_fb_interaction_at))) {
                            $contact->last_fb_interaction_at = $lastUserMessageAt;
                        }

                        $contact->save();
                        $isNew ? $created++ : $updated++;
                    } catch (Throwable $e) {
                        $errors++;
                        $this->warn("Contact {$psid} ignoré : " . $e->getMessage());
                    }
                }
            }

            // Avancement en direct pour le toast
            $this->report([
                'created' => $created,
                'updated' => $updated,
                'errors'  => $errors,
                'seen'    => $seen,
                'pages'   => $pages,
            ]);

            // Évite de relire la même page si Meta renvoie le même lien
            $next = $response->json('paging.next');
            $url = ($next && $next !== $url) ? $next : null;
            $params = [];
        } while ($url);

        // Le point de synchro n'avance que si tout a été lu
        if ($complete) {
            Cache::forever(self::CACHE_KEY, $startedAt);
        }

        $this->report([
            'state'       => 'done',
            'created'     => $created,
            'updated'     => $updated,
            'errors'      => $errors,
            'seen'        => $seen,
            'pages'       => $pages,
            'complete'    => $complete,
            'finished_at' => now()->toIso8601String(),
        ]);

        $this->info("Synchro Facebook OK : {$created} créés, {$updated} mis à jour, {$errors} erreur(s).");

        return self::SUCCESS;
    }

    /** Met à jour l'état partagé (lu par FacebookSyncController@status). */
    private function report(array $patch): void
    {
        $this->status = array_merge($this->status, $patch, [
            'updated_at' => now()->toIso8601String(),
        ]);

        Cache::put(self::STATUS_KEY, $this->status, now()->addDay());
    }
}