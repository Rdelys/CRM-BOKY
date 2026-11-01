<?php

namespace App\Console\Commands;

use App\Models\FacebookInsight;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncFacebookInsights extends Command
{
    /** Meta conserve environ 2 ans de statistiques de Page. */
    private const MAX_DAYS = 760;

    protected $signature = 'crm:sync-fb-insights
        {--days=30 : Nombre de jours à relire (max 760)}
        {--since= : Date de début (AAAA-MM-JJ), remplace --days}
        {--all : Importe tout l\'historique disponible (environ 2 ans)}';

    protected $description = 'Importe la visibilité de la Page Facebook (vues, personnes atteintes, engagements, abonnés)';

    public function handle(): int
    {
        $cfg = config('crm.facebook');
        $pageId = (string) ($cfg['page_id'] ?? '');

        if (empty($cfg['page_token']) || $pageId === '') {
            $this->error('FB_PAGE_TOKEN ou FB_PAGE_ID manquant dans le .env');

            return self::FAILURE;
        }

        $metrics = $cfg['insight_metrics'] ?? [
            'views'       => 'page_media_view',
            'reach'       => 'page_total_media_view_unique',
            'engagements' => 'page_post_engagements',
            'follows'     => 'page_daily_follows',
        ];

        // Période à lire
        $oldest = now()->subDays(self::MAX_DAYS)->startOfDay();

        if ($this->option('all')) {
            $start = $oldest->copy();
        } elseif ($this->option('since')) {
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
            $days = max(1, min(self::MAX_DAYS, (int) $this->option('days')));
            $start = now()->subDays($days)->startOfDay();
        }

        $end = now()->addDay()->startOfDay();
        $chunks = (int) ceil($start->diffInDays($end) / 30);

        $this->info('Lecture du ' . $start->format('d/m/Y') . ' au ' . now()->format('d/m/Y')
            . ' (' . $chunks . ' tranche(s) × ' . count($metrics) . ' métrique(s))');

        $bar = $this->output->createProgressBar($chunks);
        $bar->start();

        $rows = [];
        $errors = [];
        $cursor = $start->copy();

        // Par tranches de 30 jours (limite de l'API : 90 jours par requête)
        while ($cursor->lt($end)) {
            $chunkEnd = $cursor->copy()->addDays(30);
            if ($chunkEnd->gt($end)) {
                $chunkEnd = $end->copy();
            }

            // Une requête par métrique : si l'une est refusée, les autres passent quand même
            foreach ($metrics as $column => $metric) {
                $r = Http::timeout(30)->withToken($cfg['page_token'])
                    ->get("{$cfg['graph']}/{$pageId}/insights", [
                        'metric' => $metric,
                        'period' => 'day',
                        'since'  => $cursor->timestamp,
                        'until'  => $chunkEnd->timestamp,
                    ]);

                if ($r->failed()) {
                    $errors[$metric]['count'] = ($errors[$metric]['count'] ?? 0) + 1;
                    $errors[$metric]['message'] = Str::limit(
                        (string) ($r->json('error.message') ?? 'HTTP ' . $r->status()),
                        160
                    );
                    continue;
                }

                foreach ($r->json('data.0.values', []) as $v) {
                    if (empty($v['end_time'])) {
                        continue;
                    }

                    // end_time marque la fin de la journée : la valeur concerne la veille
                    $date = Carbon::parse($v['end_time'])->subDay()->toDateString();
                    $value = $v['value'] ?? 0;
                    $rows[$date][$column] = (int) (is_array($value) ? array_sum($value) : $value);
                }
            }

            $cursor = $chunkEnd;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        foreach ($errors as $metric => $e) {
            $this->warn("Métrique {$metric} refusée sur {$e['count']} tranche(s) : {$e['message']}");
        }

        if (!$rows) {
            $this->error('Aucune donnée importée. Vérifiez les permissions read_insights et pages_read_engagement, et que le token est celui de la Page.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $date => $columns) {
                FacebookInsight::updateOrCreate(['date' => $date], $columns);
            }
        });

        ksort($rows);
        $first = array_key_first($rows);
        $last  = array_key_last($rows);
        $views = array_sum(array_column($rows, 'views'));

        $this->info(count($rows) . ' jour(s) de visibilité importé(s), du '
            . Carbon::parse($first)->format('d/m/Y') . ' au ' . Carbon::parse($last)->format('d/m/Y')
            . ' · ' . number_format($views, 0, ',', ' ') . ' vues au total.');

        return self::SUCCESS;
    }
}