<?php

namespace App\Http\Controllers;

use App\Models\MarketingSpend;
use App\Services\MarketingStatsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;

class MarketingController extends Controller
{
    public function __construct(private MarketingStatsService $stats)
    {
    }

    /** Page « Marketing » : filtre mensuel ou annuel. */
    public function index(Request $request)
    {
        $period = $request->query('period') === 'year' ? 'year' : 'month';
        $r = $this->stats->range($period, $request->query('ref'));

        $cur  = $this->stats->kpis($r['from'], $r['end']);
        $prev = $this->stats->kpis($r['prev_from'], $r['prev_end']);

        $kpis   = $this->stats->compare($cur, $prev);
        $series = $this->stats->series($period, $r['from'], $r['to']);

        $fbClients     = $this->stats->facebookClients($r['from'], $r['end']);
        $fbClientsEver = $this->stats->facebookClientsEver();

        $spends = MarketingSpend::query()
            ->whereDate('spent_on', '>=', $r['from']->toDateString())
            ->whereDate('spent_on', '<=', $r['end']->copy()->subSecond()->toDateString())
            ->orderByDesc('spent_on')->orderByDesc('id')
            ->limit(50)
            ->get();

        $hasInsights = \App\Models\FacebookInsight::query()->exists();

        return view('marketing.index', compact(
            'period', 'r', 'cur', 'prev', 'kpis', 'series', 'fbClients', 'fbClientsEver', 'spends', 'hasInsights'
        ));
    }

    /** Résumé en direct pour le tableau de bord (jour, semaine, mois, an, ou plage personnalisée). */
    public function summary(Request $request): JsonResponse
    {
        $period = in_array($request->query('period'), ['day', 'week', 'month', 'year', 'custom'], true)
            ? $request->query('period')
            : 'day';

        if ($period === 'custom') {
            $isDate = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);

            try {
                $from = $isDate($request->query('from')) ? Carbon::parse($request->query('from'))->startOfDay() : today()->subDays(29);
                $to   = $isDate($request->query('to')) ? Carbon::parse($request->query('to'))->startOfDay() : today();
            } catch (\Throwable) {
                $from = today()->subDays(29);
                $to   = today();
            }

            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }

            $end = $to->copy()->addDay();
            if ($end->gt(now())) {
                $end = now();                                   // on ne dépasse pas maintenant
            }
            if ($end->lt($from)) {
                $end = $from->copy();
            }

            // Période précédente de même durée, juste avant
            $len      = $end->getTimestamp() - $from->getTimestamp();
            $prevFrom = $from->copy()->subSeconds($len);
            $prevEnd  = $from->copy();

            $label = 'Du ' . $from->copy()->locale('fr')->translatedFormat('d F Y')
                . ' au ' . $to->copy()->locale('fr')->translatedFormat('d F Y');
        } else {
            $r        = $this->stats->range($period);
            $from     = $r['from'];
            $end      = $r['end'];
            $prevFrom = $r['prev_from'];
            $prevEnd  = $r['prev_end'];
            $label    = $r['label'];
        }

        $kpis = $this->stats->compare(
            $this->stats->kpis($from, $end),
            $this->stats->kpis($prevFrom, $prevEnd)
        );

        return response()->json([
            'period'       => $period,
            'label'        => $label,
            'generated_at' => now()->format('H:i:s'),
            'kpis'         => array_values($kpis),
        ]);
    }

    public function storeSpend(Request $request)
    {
        $data = $request->validate([
            'spent_on' => ['required', 'date'],
            'channel'  => ['required', Rule::in(array_keys(MarketingSpend::CHANNELS))],
            'amount'   => ['required', 'integer', 'min:1'],
            'label'    => ['nullable', 'string', 'max:150'],
        ], [], [
            'spent_on' => 'date',
            'channel'  => 'canal',
            'amount'   => 'montant',
            'label'    => 'libellé',
        ]);

        MarketingSpend::create($data);

        return back()->with('success', 'Dépense enregistrée.');
    }

    public function destroySpend(MarketingSpend $spend)
    {
        $spend->delete();

        return back()->with('success', 'Dépense supprimée.');
    }

    /**
     * Lance l'import de la visibilité Facebook (90 derniers jours)
     * puis celui des dépenses publicitaires (boosts), si le compte publicitaire est configuré.
     */
    public function syncInsights()
    {
        $code = Artisan::call('crm:sync-fb-insights', ['--days' => 90]);
        $output = trim(Artisan::output());

        if ($code !== 0) {
            return back()->withErrors(['insights' => 'Import de la visibilité impossible. ' . $output]);
        }

        $message = $output ?: 'Visibilité Facebook mise à jour.';

        // Dépenses publicitaires : facultatif tant que FB_ADS_TOKEN / FB_AD_ACCOUNT_ID ne sont pas renseignés
        if (config('crm.facebook.ads_token') && config('crm.facebook.ad_account_id')) {
            $spendCode = Artisan::call('crm:sync-fb-spend', ['--days' => 90]);
            $spendOutput = trim(Artisan::output());

            if ($spendCode !== 0) {
                return back()
                    ->with('success', $message)
                    ->withErrors(['insights' => 'Import des dépenses publicitaires impossible. ' . $spendOutput]);
            }

            $message .= ' ' . ($spendOutput ?: 'Dépenses publicitaires mises à jour.');
        }

        return back()->with('success', $message);
    }
}