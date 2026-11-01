<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\Contact;
use App\Models\MessageLog;
use App\Models\Objectif;
use Carbon\Carbon;

class ObjectifService
{
    public function __construct(private MarketingStatsService $stats)
    {
    }

    /** Valeur réelle de chaque indicateur sur [$from, $end[. */
    public function values(Carbon $from, Carbon $end): array
    {
        $k = $this->stats->kpis($from, $end);

        $orders = fn () => Commande::query()
            ->where('date_commande', '>=', $from)
            ->where('date_commande', '<', $end);

        $sent = fn (string $channel) => MessageLog::query()
            ->where('channel', $channel)
            ->where('status', 'sent')
            ->where('sent_at', '>=', $from)
            ->where('sent_at', '<', $end)
            ->count();

        return [
            'ca'                => (int) Commande::where('statut', '!=', 'annulee')
                ->where('date_commande', '>=', $from)->where('date_commande', '<', $end)
                ->sum('montant_total'),
            'commandes'         => $orders()->count(),
            'livrees'           => $orders()->where('statut', 'livree')->count(),
            'panier'            => $k['panier'],

            'nouveaux_contacts' => $k['total_contacts'],
            'nouveaux_clients'  => $k['new_customers'],
            'conversion'        => $k['conversion'],

            'views'             => $k['views'],
            'reach'             => $k['reach'],
            'engagements'       => $k['engagements'],
            'follows'           => $k['follows'],
            'prospection'       => $k['prospection'],
            'roi'               => $k['roi'],
            'cac'               => $k['cac'],
            'depenses'          => $k['spend'],

            'emails'            => $sent('email'),
            'sms'               => $sent('sms'),
            'messenger'         => $sent('messenger'),
        ];
    }

    /**
     * Compare une valeur à son objectif.
     *
     * @return array{pct: ?float, status: string}
     *   status : reached | on_track | late | missed | nodata
     */
    public function evaluate(string $metric, float $target, $value, float $elapsed, bool $isCurrent): array
    {
        $def = Objectif::METRICS[$metric];

        if ($value === null) {
            return ['pct' => null, 'status' => 'nodata'];
        }

        $value = (float) $value;

        // Plafond (CAC, dépenses) : on ne doit pas dépasser l'objectif
        if (!empty($def['inverse'])) {
            if ($value <= $target) {
                return ['pct' => $value > 0 ? min(100.0, round($target / $value * 100, 1)) : 100.0, 'status' => 'reached'];
            }

            return ['pct' => round($target / $value * 100, 1), 'status' => $isCurrent ? 'late' : 'missed'];
        }

        $pct = $target > 0 ? round($value / $target * 100, 1) : 0.0;

        if ($pct >= 100) {
            return ['pct' => $pct, 'status' => 'reached'];
        }

        if (!$isCurrent) {
            return ['pct' => $pct, 'status' => 'missed'];
        }

        // Période en cours : cumulatif → comparé au rythme attendu ; sinon → seuil de 80 %
        $onTrack = !empty($def['cumulative'])
            ? $pct >= $elapsed * 100 * 0.9
            : $pct >= 80;

        return ['pct' => $pct, 'status' => $onTrack ? 'on_track' : 'late'];
    }
}