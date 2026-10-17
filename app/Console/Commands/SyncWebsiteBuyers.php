<?php

namespace App\Console\Commands;

use App\Models\Contact;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SyncWebsiteBuyers extends Command
{
    protected $signature = 'crm:sync-buyers {--full : Récupère tous les acheteurs (ignore le dernier point de synchro)}';

    protected $description = 'Importe et met à jour les profils acheteurs du siteweb dans le CRM';

    private const CACHE_KEY = 'crm.buyers.last_sync';

    public function handle(): int
    {
        $url = config('crm.website.url');
        $token = config('crm.website.token');

        if (!$url || !$token) {
            $this->error('WEBSITE_API_URL ou WEBSITE_API_TOKEN manquant dans le .env');
            return self::FAILURE;
        }

        $last = $this->option('full') ? null : Cache::get(self::CACHE_KEY);
        // Léger chevauchement (2 min) pour ne rien rater : les mises à jour sont sans danger si répétées
        $since = $last ? Carbon::parse($last)->subMinutes(2)->toIso8601String() : null;

        $afterId = 0;
        $serverTime = null;
        $created = 0;
        $updated = 0;

        try {
            do {
                $response = Http::withHeaders(['X-CRM-Token' => $token, 'Accept' => 'application/json'])
                    ->timeout(30)
                    ->get($url, array_filter([
                        'since'    => $since,
                        'after_id' => $afterId,
                        'limit'    => 200,
                    ], fn ($v) => $v !== null));

                if ($response->failed()) {
                    $this->error('API siteweb : HTTP ' . $response->status());
                    return self::FAILURE;
                }

                $json = $response->json();
                $serverTime ??= $json['server_time'] ?? now()->toIso8601String();

                foreach ($json['data'] ?? [] as $buyer) {
                    $this->upsert($buyer) ? $created++ : $updated++;
                }

                $afterId = $json['next_after_id'] ?? null;
            } while ($afterId !== null);
        } catch (ConnectionException $e) {
            $this->error('Siteweb injoignable : ' . $e->getMessage());
            return self::FAILURE;
        }

        // Le point de synchro n'avance que si tout s'est bien passé
        Cache::forever(self::CACHE_KEY, $serverTime);

        $this->info("Synchro OK : {$created} créés, {$updated} mis à jour.");
        return self::SUCCESS;
    }

    /** Retourne true si le contact a été créé, false s'il existait déjà. */
    private function upsert(array $b): bool
    {
        $email = !empty($b['email']) ? strtolower(trim($b['email'])) : null;

        // 1) déjà synchronisé, 2) contact existant avec le même email (ajouté à la main ou importé)
        $contact = Contact::where('website_user_id', $b['id'])->first()
            ?? ($email ? Contact::where('email', $email)->whereNull('website_user_id')->first() : null);

        $interests = collect([
            !empty($b['motif_inscription']) ? "Motif d'inscription : " . $b['motif_inscription'] : null,
            !empty($b['types_livres_recherches']) ? 'Livres recherchés : ' . $b['types_livres_recherches'] : null,
        ])->filter()->implode("\n");

        $registeredAt = !empty($b['registered_at'])
            ? Carbon::parse($b['registered_at'])->setTimezone(config('app.timezone'))
            : null;

        // Les valeurs vides du siteweb n'écrasent jamais ce que le CRM possède déjà
        $data = array_filter([
            'website_user_id' => $b['id'],
            'name'            => $b['name'] ?? null,
            'email'           => $email,
            'phone'           => $b['phone'] ?? null,
            'city'            => $b['localisation'] ?? null,
            'interests'       => $interests ?: null,
            'registered_at'   => $registeredAt,
        ], fn ($v) => $v !== null && $v !== '');

        $hasOrders = ((int) ($b['orders_count'] ?? 0)) > 0;

        if ($contact) {
            // Passe en « client » dès la première commande ; ne rétrograde jamais
            if ($hasOrders && $contact->status !== 'client') {
                $data['status'] = 'client';
            }
            $contact->update($data);

            return false;
        }

        Contact::create($data + [
            'source' => 'website',
            'status' => $hasOrders ? 'client' : 'prospect',
        ]);

        return true;
    }
}