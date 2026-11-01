<?php

namespace App\Console\Commands;

use App\Models\FacebookInsight;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncFacebookInsights extends Command
{
    protected $signature = 'crm:sync-fb-insights {--days=30 : Nombre de jours à relire (max 365)}';

    protected $description = 'Importe la visibilité de la Page Facebook (vues, personnes atteintes, engagements)';

    public function handle(): int
    {
        $cfg = config('crm.facebook');
        $pageId = (string) $cfg['page_id'];

        if (!$cfg['page_token'] || $pageId === '') {
            $this->error('FB_PAGE_TOKEN ou FB_PAGE_ID manquant dans le .env');

            return self::FAILURE;
        }

        $days = max(1, min(365, (int) $this->option('days')));
        $end = now()->addDay()->startOfDay();
        $cursor = now()->subDays($days)->startOfDay();

        $rows = [];
        $errors = [];

        // Par tranches de 30 jours (limite de l'API : 93 jours par requête)
        while ($cursor->lt($end)) {
            $chunkEnd = $cursor->copy()->addDays(30);
            if ($chunkEnd->gt($end)) {
                $chunkEnd = $end->copy();
            }

            // Une requête par métrique : si l'une est refusée, les autres passent quand même
            foreach ($cfg['insight_metrics'] as $column => $metric) {
                $r = Http::timeout(30)->withToken($cfg['page_token'])
                    ->get("{$cfg['graph']}/{$pageId}/insights", [
                        'metric' => $metric,
                        'period' => 'day',
                        'since'  => $cursor->timestamp,
                        'until'  => $chunkEnd->timestamp,
                    ]);

                if ($r->failed()) {
                    $errors[$metric] = Str::limit((string) ($r->json('error.message') ?? 'HTTP ' . $r->status()), 160);
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
        }

        foreach ($rows as $date => $columns) {
            FacebookInsight::updateOrCreate(['date' => $date], $columns);
        }

        foreach ($errors as $metric => $message) {
            $this->warn("Métrique {$metric} refusée : {$message}");
        }

        if (!$rows && $errors) {
            $this->error('Aucune donnée importée. Vérifiez les permissions read_insights et pages_read_engagement.');

            return self::FAILURE;
        }

        $this->info(count($rows) . ' jour(s) de visibilité importé(s).');

        return self::SUCCESS;
    }
}