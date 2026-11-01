<?php

namespace App\Console\Commands;

use App\Models\MarketingSpend;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncFacebookAdSpend extends Command
{
    /** Marge de sécurité : on relit par tranches de 90 jours. */
    private const CHUNK_DAYS = 90;

    /** Historique maximum relu en une fois (environ 3 ans). */
    private const MAX_DAYS = 1095;

    protected $signature = 'crm:sync-fb-spend
        {--days=30 : Nombre de jours à relire (max 1095)}
        {--since= : Date de début (AAAA-MM-JJ), remplace --days}';

    protected $description = 'Importe les dépenses publicitaires (boosts) du compte publicitaire Facebook dans les dépenses marketing';

    public function handle(): int
    {
        $cfg     = config('crm.facebook');
        $token   = (string) ($cfg['ads_token'] ?? '');
        $account = trim((string) ($cfg['ad_account_id'] ?? ''));
        $rate    = (float) ($cfg['ads_rate'] ?? 1);

        if ($token === '' || $account === '') {
            $this->error('FB_ADS_TOKEN ou FB_AD_ACCOUNT_ID manquant dans le .env');

            return self::FAILURE;
        }

        if ($rate <= 0) {
            $this->error('FB_ADS_RATE doit être supérieur à 0.');

            return self::FAILURE;
        }

        $account = Str::start($account, 'act_');

        // Période à lire
        $oldest = now()->subDays(self::MAX_DAYS)->startOfDay();

        if ($this->option('since')) {
            try {
                $start = Carbon::parse((string) $this->option('since'))->startOfDay();
            } catch (\Throwable) {
                $this->error('Date invalide pour --since (format attendu : AAAA-MM-JJ).');

                return self::FAILURE;
            }
            if ($start->lt($oldest)) {
                $start = $oldest->copy();
            }
        } else {
            $days  = max(1, min(self::MAX_DAYS, (int) $this->option('days')));
            $start = now()->subDays($days)->startOfDay();
        }

        $end = now()->startOfDay();

        $this->info('Lecture des dépenses du ' . $start->format('d/m/Y') . ' au ' . $end->format('d/m/Y')
            . " (compte {$account}, taux × {$rate})");

        $rows     = [];   // date => montant en Ar
        $currency = null;
        $cursor   = $start->copy();

        while ($cursor->lte($end)) {
            $chunkEnd = $cursor->copy()->addDays(self::CHUNK_DAYS - 1);
            if ($chunkEnd->gt($end)) {
                $chunkEnd = $end->copy();
            }

            $url    = "{$cfg['graph']}/{$account}/insights";
            $params = [
                'fields'         => 'spend,account_currency',
                'level'          => 'account',
                'time_increment' => 1,
                'time_range'     => json_encode([
                    'since' => $cursor->toDateString(),
                    'until' => $chunkEnd->toDateString(),
                ]),
                'limit'          => 100,
            ];

            $pages = 0;

            do {
                $r = Http::timeout(30)->withToken($token)->get($url, $params);

                if ($r->failed()) {
                    $this->error('Facebook a refusé la requête (HTTP ' . $r->status() . ') : '
                        . Str::limit((string) ($r->json('error.message') ?? 'erreur inconnue'), 200));
                    $this->line('Vérifiez que le token a la permission ads_read et que l\'ID du compte publicitaire est correct.');

                    return self::FAILURE;
                }

                foreach ($r->json('data', []) as $row) {
                    if (empty($row['date_start'])) {
                        continue;
                    }

                    $currency ??= $row['account_currency'] ?? null;
                    $date = Carbon::parse($row['date_start'])->toDateString();
                    $rows[$date] = (int) round(((float) ($row['spend'] ?? 0)) * $rate);
                }

                $next   = $r->json('paging.next');
                $url    = $next ?: null;
                $params = [];
            } while ($url && ++$pages < 50);

            $cursor = $chunkEnd->copy()->addDay();
        }

        if ($currency) {
            $this->line("Devise du compte publicitaire : {$currency}"
                . ($rate === 1.0 ? ' (aucune conversion appliquée : FB_ADS_RATE=1)' : ''));
        }

        if (!$rows) {
            $this->warn('Aucune dépense publicitaire trouvée sur cette période.');

            return self::SUCCESS;
        }

        $imported = 0;
        $removed  = 0;

        DB::transaction(function () use ($rows, &$imported, &$removed) {
            foreach ($rows as $date => $amount) {
                $key = 'fb_ads:' . $date;

                // Jour sans dépense : on retire une ancienne ligne importée si elle existe
                if ($amount <= 0) {
                    $removed += MarketingSpend::where('external_key', $key)->delete();
                    continue;
                }

                MarketingSpend::updateOrCreate(
                    ['external_key' => $key],
                    [
                        'spent_on' => $date,
                        'channel'  => 'facebook',
                        'amount'   => $amount,
                        'label'    => 'Boost Facebook (import auto)',
                    ]
                );
                $imported++;
            }
        });

        ksort($rows);
        $total = array_sum($rows);

        $this->info("{$imported} jour(s) de dépenses importé(s) · total "
            . number_format($total, 0, ',', ' ') . ' Ar'
            . ($removed ? " · {$removed} ligne(s) à zéro retirée(s)" : '') . '.');

        return self::SUCCESS;
    }
}