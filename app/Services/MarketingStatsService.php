<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\Contact;
use App\Models\FacebookInsight;
use App\Models\MarketingSpend;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MarketingStatsService
{
    /** Date d'arrivée réelle d'un contact (identique au tableau de bord). */
    public const CONTACT_DATE = 'COALESCE(registered_at, created_at)';

    /** Définition des indicateurs : type d'affichage et sens du « mieux ». */
    public const DEFS = [
        'conversion'  => ['label' => 'Taux de conversion',  'type' => 'rate',  'decimals' => 1],
        'panier'      => ['label' => 'Panier moyen',        'type' => 'money'],
        'views'       => ['label' => 'Vues Facebook',       'type' => 'count'],
        'cac'         => ['label' => 'CAC',                 'type' => 'money', 'inverse' => true],
        'roi'         => ['label' => 'ROI Facebook',        'type' => 'rate',  'decimals' => 1],
        'prospection' => ['label' => 'Taux de prospection', 'type' => 'rate',  'decimals' => 2],
    ];

    /* ------------------------------------------------------------------ */
    /*  Périodes                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Calcule la période demandée et la période précédente de même durée écoulée.
     *
     * @param  string      $period day|week|month|year
     * @param  string|null $ref    Y-m-d (day, week) | Y-m (month) | Y (year)
     */
    public function range(string $period, ?string $ref = null): array
    {
        $now = now();
        $base = $this->parseRef($period, $ref) ?? $now->copy();

        $from = match ($period) {
            'day'   => $base->copy()->startOfDay(),
            'week'  => $base->copy()->startOfWeek(),
            'year'  => $base->copy()->startOfYear(),
            default => $base->copy()->startOfMonth(),
        };

        $to       = $this->step($from, $period, 1);
        $prevFrom = $this->step($from, $period, -1);

        // Période en cours : on s'arrête à maintenant (et on compare à la même durée écoulée)
        $end = $now->lt($to) ? $now->copy() : $to->copy();
        if ($end->lt($from)) {
            $end = $from->copy();
        }

        $elapsed = $end->getTimestamp() - $from->getTimestamp();
        $prevEnd = $prevFrom->copy()->addSeconds($elapsed);
        if ($prevEnd->gt($from)) {
            $prevEnd = $from->copy();
        }

        $refFmt = match ($period) {
            'year'  => 'Y',
            'month' => 'Y-m',
            default => 'Y-m-d',
        };

        $label = match ($period) {
            'day'   => $from->copy()->locale('fr')->translatedFormat('l d F Y'),
            'week'  => 'Semaine du ' . $from->copy()->locale('fr')->translatedFormat('d F Y'),
            'year'  => 'Année ' . $from->format('Y'),
            default => ucfirst($from->copy()->locale('fr')->translatedFormat('F Y')),
        };

        return [
            'period'     => $period,
            'from'       => $from,
            'to'         => $to,            // fin complète de la période
            'end'        => $end,           // fin effective (maintenant si en cours)
            'prev_from'  => $prevFrom,
            'prev_end'   => $prevEnd,
            'label'      => $label,
            'is_current' => $now->lt($to),
            'ref'        => $from->format($refFmt),
            'prev_ref'   => $prevFrom->format($refFmt),
            'next_ref'   => $to->gt($now) ? null : $to->format($refFmt),
        ];
    }

    private function parseRef(string $period, ?string $ref): ?Carbon
    {
        if (!$ref) {
            return null;
        }

        try {
            return match ($period) {
                'year'  => preg_match('/^\d{4}$/', $ref) ? Carbon::create((int) $ref, 1, 1) : null,
                'month' => preg_match('/^\d{4}-\d{2}$/', $ref) ? Carbon::parse($ref . '-01') : null,
                default => preg_match('/^\d{4}-\d{2}-\d{2}$/', $ref) ? Carbon::parse($ref) : null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    private function step(Carbon $d, string $period, int $n): Carbon
    {
        $d = $d->copy();

        return match ($period) {
            'day'   => $d->addDays($n),
            'week'  => $d->addWeeks($n),
            'year'  => $d->addYears($n),
            default => $d->addMonthsNoOverflow($n),
        };
    }

    /* ------------------------------------------------------------------ */
    /*  Indicateurs                                                        */
    /* ------------------------------------------------------------------ */

    /** Indicateurs sur [$from, $end[. */
    public function kpis(Carbon $from, Carbon $end): array
    {
        $firstDay = $from->toDateString();
        $lastDay  = $end->copy()->subSecond()->toDateString();

        // Commandes valides avec montant (base du panier moyen)
        $paid = fn () => Commande::query()
            ->where('statut', '!=', 'annulee')
            ->where('montant_total', '>', 0)
            ->where('date_commande', '>=', $from)
            ->where('date_commande', '<', $end);

        $nbOrders = $paid()->count();
        $ca       = (int) $paid()->sum('montant_total');
        $panier   = $nbOrders > 0 ? (int) round($ca / $nbOrders) : null;

        // Conversion : contacts arrivés dans la période qui ont commandé
        $arrived = fn () => Contact::query()->whereRaw(
            self::CONTACT_DATE . ' >= ? and ' . self::CONTACT_DATE . ' < ?',
            [$from, $end]
        );

        $totalContacts = $arrived()->count();
        $converted     = $arrived()->whereIn('contacts.id', $this->convertedIds())->count();
        $conversion    = $totalContacts > 0 ? round($converted / $totalContacts * 100, 1) : null;

        // Nouveaux clients = première commande valide dans la période
        $newCustomers = Commande::query()
            ->select('contact_id')
            ->where('statut', '!=', 'annulee')
            ->groupBy('contact_id')
            ->havingRaw('MIN(date_commande) >= ? AND MIN(date_commande) < ?', [$from, $end])
            ->get()
            ->count();

        // Dépenses
        $spends = fn () => MarketingSpend::query()
            ->whereDate('spent_on', '>=', $firstDay)
            ->whereDate('spent_on', '<=', $lastDay);

        $spendAll = (int) $spends()->sum('amount');
        $spendFb  = (int) $spends()->where('channel', 'facebook')->sum('amount');
        $cac      = ($newCustomers > 0 && $spendAll > 0) ? (int) round($spendAll / $newCustomers) : null;

        // Visibilité Facebook
        $ins = FacebookInsight::query()
            ->whereDate('date', '>=', $firstDay)
            ->whereDate('date', '<=', $lastDay)
            ->selectRaw('COALESCE(SUM(views),0) as v, COALESCE(SUM(reach),0) as r, COALESCE(SUM(engagements),0) as e, COALESCE(SUM(follows),0) as f')
            ->first();

        $views = (int) $ins->v;
        $reach = (int) $ins->r;

        // ROI Facebook : revenu des clients venus de Facebook vs dépenses Facebook
        $fbRevenue = (int) Commande::query()
            ->where('statut', '!=', 'annulee')
            ->where('date_commande', '>=', $from)
            ->where('date_commande', '<', $end)
            ->whereHas('contact', fn ($q) => $q->where('source', 'facebook'))
            ->sum('montant_total');

        $roi = $spendFb > 0 ? round(($fbRevenue - $spendFb) / $spendFb * 100, 1) : null;

        // Taux de prospection : nouveaux contacts Facebook / personnes atteintes
        $fbProspects = $arrived()->where('source', 'facebook')->count();
        $prospection = $reach > 0 ? round($fbProspects / $reach * 100, 2) : null;

        return [
            'conversion'  => $conversion,
            'panier'      => $panier,
            'views'       => $views,
            'cac'         => $cac,
            'roi'         => $roi,
            'prospection' => $prospection,

            // détails affichés sous les cartes
            'reach'          => $reach,
            'engagements'    => (int) $ins->e,
            'follows'        => (int) $ins->f,
            'nb_orders'      => $nbOrders,
            'ca'             => $ca,
            'total_contacts' => $totalContacts,
            'converted'      => $converted,
            'new_customers'  => $newCustomers,
            'spend'          => $spendAll,
            'spend_fb'       => $spendFb,
            'fb_revenue'     => $fbRevenue,
            'fb_prospects'   => $fbProspects,
        ];
    }

    /** Écart de chaque indicateur par rapport à la période précédente. */
    public function compare(array $cur, array $prev): array
    {
        $out = [];

        foreach (self::DEFS as $key => $def) {
            $c = $cur[$key] ?? null;
            $p = $prev[$key] ?? null;

            $diff = ($c !== null && $p !== null) ? round($c - $p, 2) : null;
            $pct  = ($diff !== null && $def['type'] !== 'rate' && $p != 0)
                ? round($diff / abs($p) * 100, 1)
                : null;
            $good = ($diff === null || $diff == 0)
                ? null
                : (($diff > 0) !== !empty($def['inverse']));

            $out[$key] = $def + [
                'key'      => $key,
                'value'    => $c,
                'previous' => $p,
                'diff'     => $diff,
                'pct'      => $pct,
                'good'     => $good,
            ];
        }

        return $out;
    }

    /** Clients venus de Facebook qui ont commandé dans la période. */
    public function facebookClients(Carbon $from, Carbon $end, int $limit = 10)
    {
        return Commande::query()
            ->selectRaw('contact_id, COUNT(*) as nb_commandes, COALESCE(SUM(montant_total), 0) as total, MAX(date_commande) as derniere')
            ->where('statut', '!=', 'annulee')
            ->where('date_commande', '>=', $from)
            ->where('date_commande', '<', $end)
            ->whereHas('contact', fn ($q) => $q->where('source', 'facebook'))
            ->groupBy('contact_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->with('contact')
            ->get();
    }

    /** Nombre de clients Facebook ayant déjà commandé (toutes périodes). */
    public function facebookClientsEver(): int
    {
        return Contact::query()
            ->where('source', 'facebook')
            ->whereIn('contacts.id', $this->convertedIds())
            ->count();
    }

    /* ------------------------------------------------------------------ */
    /*  Séries pour les graphiques (par jour si mois, par mois si année)   */
    /* ------------------------------------------------------------------ */

    public function series(string $period, Carbon $from, Carbon $to): array
    {
        $unit   = $period === 'year' ? 'month' : 'day';
        $phpFmt = $unit === 'month' ? 'Y-m' : 'Y-m-d';
        $sqlFmt = $unit === 'month' ? '%Y-%m' : '%Y-%m-%d';

        $keys = [];
        $labels = [];
        $cursor = $from->copy();

        while ($cursor->lt($to)) {
            $keys[]   = $cursor->format($phpFmt);
            $labels[] = $unit === 'month'
                ? $cursor->copy()->locale('fr')->translatedFormat('M')
                : $cursor->format('d');
            $unit === 'month' ? $cursor->addMonthNoOverflow() : $cursor->addDay();
        }

        $index = array_flip($keys);
        $zeros = array_fill(0, count($keys), 0);
        $out = ['labels' => $labels, 'views' => $zeros, 'reach' => $zeros, 'prospects' => $zeros, 'spend' => $zeros, 'revenue' => $zeros];

        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        $fill = function ($rows, array $map) use ($index, &$out) {
            foreach ($rows as $row) {
                if (!isset($index[$row->k])) {
                    continue;
                }
                foreach ($map as $target => $column) {
                    $out[$target][$index[$row->k]] = (int) $row->{$column};
                }
            }
        };

        $fill(
            FacebookInsight::query()
                ->selectRaw($this->bucketExpr('date', $sqlFmt) . ' as k, SUM(views) as v, SUM(reach) as r')
                ->whereDate('date', '>=', $fromDate)->whereDate('date', '<', $toDate)
                ->groupBy('k')->get(),
            ['views' => 'v', 'reach' => 'r']
        );

        $fill(
            Contact::query()
                ->selectRaw($this->bucketExpr(self::CONTACT_DATE, $sqlFmt) . ' as k, COUNT(*) as n')
                ->where('source', 'facebook')
                ->whereRaw(self::CONTACT_DATE . ' >= ? and ' . self::CONTACT_DATE . ' < ?', [$from, $to])
                ->groupBy('k')->get(),
            ['prospects' => 'n']
        );

        $fill(
            MarketingSpend::query()
                ->selectRaw($this->bucketExpr('spent_on', $sqlFmt) . ' as k, SUM(amount) as s')
                ->whereDate('spent_on', '>=', $fromDate)->whereDate('spent_on', '<', $toDate)
                ->groupBy('k')->get(),
            ['spend' => 's']
        );

        $fill(
            Commande::query()
                ->selectRaw($this->bucketExpr('date_commande', $sqlFmt) . ' as k, COALESCE(SUM(montant_total),0) as c')
                ->where('statut', '!=', 'annulee')
                ->where('date_commande', '>=', $from)->where('date_commande', '<', $to)
                ->whereHas('contact', fn ($q) => $q->where('source', 'facebook'))
                ->groupBy('k')->get(),
            ['revenue' => 'c']
        );

        return $out;
    }

    /* ------------------------------------------------------------------ */
    /*  Outils                                                             */
    /* ------------------------------------------------------------------ */

    private function convertedIds(): Builder
    {
        return Commande::query()->select('contact_id')->where('statut', '!=', 'annulee');
    }

    /** Regroupe une date selon un format MySQL (%Y-%m-%d ou %Y-%m), compatible SQLite/PostgreSQL. */
    private function bucketExpr(string $column, string $format): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('{$format}', {$column})",
            'pgsql'  => "to_char({$column}, '" . str_replace(['%Y', '%m', '%d'], ['YYYY', 'MM', 'DD'], $format) . "')",
            default  => "DATE_FORMAT({$column}, '{$format}')",
        };
    }
}