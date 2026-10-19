<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Commande;
use App\Models\Contact;
use App\Models\MessageLog;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    private const RANGES = [
        'day'   => ['min' => 7, 'max' => 90, 'default' => 30],
        'month' => ['min' => 6, 'max' => 24, 'default' => 12],
        'year'  => ['min' => 2, 'max' => 10, 'default' => 5],
    ];

    /** Date d'arrivée réelle : inscription sur le siteweb si connue, sinon création dans le CRM. */
    private const CONTACT_DATE = 'COALESCE(registered_at, created_at)';

    /** Nombre de fenêtres affichées selon la durée (en mois) d'une fenêtre de fidélité. */
    private const LOYALTY_UNITS = [1 => 6, 2 => 6, 3 => 6, 12 => 4];

    public function __invoke()
    {
        $todayStart     = today();
        $yesterdayStart = today()->subDay();
        $monthStart     = now()->startOfMonth();
        $prevMonthStart = now()->startOfMonth()->subMonth();

        $newToday     = $this->acquired($todayStart, $todayStart->copy()->addDay());
        $newYesterday = $this->acquired($yesterdayStart, $todayStart);
        $newMonth     = $this->acquired($monthStart, $monthStart->copy()->addMonth());
        $newPrevMonth = $this->acquired($prevMonthStart, $monthStart);

        $mailsMonth = $this->sent('email', $monthStart, $monthStart->copy()->addMonth());
        $mailsPrev  = $this->sent('email', $prevMonthStart, $monthStart);

        $monthEnd = $monthStart->copy()->addMonth();

        $cmdMonth = $this->commandes($monthStart, $monthEnd);
        $cmdPrev  = $this->commandes($prevMonthStart, $monthStart);
        $caMonth  = $this->chiffre($monthStart, $monthEnd);
        $caPrev   = $this->chiffre($prevMonthStart, $monthStart);
        $livMonth = $this->commandes($monthStart, $monthEnd, 'livree');
        $livPrev  = $this->commandes($prevMonthStart, $monthStart, 'livree');

        // Conversion globale et fidélité (3 mois)
        $contactsTotal = Contact::count();
        $converted     = Contact::whereIn('contacts.id', $this->convertedIds())->count();
        [$cohortTotal, $cohortConv] = $this->cohortConversion($monthStart, $monthEnd);
        $loyalty = $this->loyaltyData(3, 2);

        $stats = [
            'contacts'   => $contactsTotal,
            'prospects'  => Contact::where('status', 'prospect')->count(),
            'clients'    => Contact::where('status', 'client')->count(),
            'campaigns'  => Campaign::where('status', 'sent')->count(),
            'reachable'  => Contact::whereNotNull('facebook_psid')
                ->where('unsubscribed', false)
                ->where('last_fb_interaction_at', '>=', now()->subHours(24))
                ->count(),
            'new_today'         => $newToday,
            'new_today_trend'   => $this->trend($newToday, $newYesterday),
            'new_month'         => $newMonth,
            'new_month_trend'   => $this->trend($newMonth, $newPrevMonth),
            'mails_month'       => $mailsMonth,
            'mails_month_trend' => $this->trend($mailsMonth, $mailsPrev),

            'conversion_rate'       => $contactsTotal > 0 ? round($converted / $contactsTotal * 100, 1) : null,
            'converted'             => $converted,
            'conversion_month_rate' => $cohortTotal > 0 ? round($cohortConv / $cohortTotal * 100, 1) : null,
            'fidelite_rate'         => $loyalty['rate'][array_key_last($loyalty['rate'])] ?? null,
            'fidelite_prev'         => $loyalty['rate'][0] ?? null,
            'repeat_rate'           => $loyalty['repeat_rate'],
            'repeat_clients'        => $loyalty['repeaters'],
            'clients_total'         => $loyalty['clients'],

            'cmd_month'           => $cmdMonth,
            'cmd_month_trend'     => $this->trend($cmdMonth, $cmdPrev),
            'ca_month'            => $caMonth,
            'ca_month_trend'      => $this->trend($caMonth, $caPrev),
            'livrees_month'       => $livMonth,
            'livrees_month_trend' => $this->trend($livMonth, $livPrev),
            'a_livrer'            => Commande::whereIn('statut', Commande::STATUTS_A_LIVRER)->count(),
            'a_livrer_today'      => Commande::whereIn('statut', Commande::STATUTS_A_LIVRER)
                ->whereDate('date_livraison', today())
                ->count(),
        ];

        $latestContacts  = Contact::latest()->limit(6)->get();
        $latestCampaigns = Campaign::latest()->limit(5)->get();
        $latestCommandes = Commande::with('contact')->orderByDesc('date_commande')->orderByDesc('id')->limit(6)->get();

        return view('dashboard', compact('stats', 'latestContacts', 'latestCampaigns', 'latestCommandes'));
    }

    /** Données des graphiques (JSON), filtrées par période. */
    public function stats(Request $request): JsonResponse
    {
        $period = in_array($request->query('period'), ['day', 'month', 'year'], true)
            ? $request->query('period')
            : 'day';

        $cfg   = self::RANGES[$period];
        $range = max($cfg['min'], min($cfg['max'], (int) $request->query('range', $cfg['default'])));

        [$start, $keys, $labels, $titles] = $this->buckets($period, $range);
        $index = array_flip($keys);
        $zeros = array_fill(0, count($keys), 0);

        // Contacts par source
        $contacts = array_fill_keys(array_keys(Contact::SOURCES), $zeros);

        $rows = Contact::query()
            ->selectRaw($this->bucketExpr(self::CONTACT_DATE, $period) . ' as bucket_key, source, COUNT(*) as total')
            ->whereRaw(self::CONTACT_DATE . ' >= ?', [$start])
            ->groupBy('bucket_key', 'source')
            ->get();

        foreach ($rows as $row) {
            if (!isset($index[$row->bucket_key])) {
                continue;
            }
            $source = isset($contacts[$row->source]) ? $row->source : 'other';
            $contacts[$source][$index[$row->bucket_key]] += (int) $row->total;
        }

        // Messages envoyés / en échec par canal
        $messages = [];
        foreach (['email', 'sms', 'messenger'] as $channel) {
            $messages[$channel] = ['sent' => $zeros, 'failed' => $zeros];
        }

        $dateExpr = $this->logDateExpr();

        $rows = MessageLog::query()
            ->selectRaw($this->bucketExpr($dateExpr, $period) . ' as bucket_key, channel, status, COUNT(*) as total')
            ->whereIn('status', ['sent', 'failed'])
            ->whereRaw($dateExpr . ' >= ?', [$start])
            ->groupBy('bucket_key', 'channel', 'status')
            ->get();

        foreach ($rows as $row) {
            if (isset($index[$row->bucket_key], $messages[$row->channel])) {
                $messages[$row->channel][$row->status][$index[$row->bucket_key]] = (int) $row->total;
            }
        }

        // Commandes par statut + chiffre d'affaires (hors annulées)
        $commandes = array_fill_keys(array_keys(Commande::STATUTS), $zeros);
        $chiffre = $zeros;

        $rows = Commande::query()
            ->selectRaw($this->bucketExpr('date_commande', $period) . ' as bucket_key, statut, COUNT(*) as total, COALESCE(SUM(montant_total), 0) as ca')
            ->where('date_commande', '>=', $start)
            ->groupBy('bucket_key', 'statut')
            ->get();

        foreach ($rows as $row) {
            if (!isset($index[$row->bucket_key]) || !isset($commandes[$row->statut])) {
                continue;
            }
            $commandes[$row->statut][$index[$row->bucket_key]] = (int) $row->total;
            if ($row->statut !== 'annulee') {
                $chiffre[$index[$row->bucket_key]] += (int) $row->ca;
            }
        }

        // Conversion : contacts arrivés dans la période et ceux qui ont commandé depuis
        $conv   = ['contacts' => $zeros, 'converted' => $zeros, 'rate' => array_fill(0, count($keys), null)];
        $bucket = $this->bucketExpr(self::CONTACT_DATE, $period);

        $totaux = Contact::query()
            ->selectRaw("{$bucket} as bucket_key, COUNT(*) as total")
            ->whereRaw(self::CONTACT_DATE . ' >= ?', [$start])
            ->groupBy('bucket_key')
            ->pluck('total', 'bucket_key');

        $convertis = Contact::query()
            ->whereIn('contacts.id', $this->convertedIds())
            ->selectRaw("{$bucket} as bucket_key, COUNT(*) as total")
            ->whereRaw(self::CONTACT_DATE . ' >= ?', [$start])
            ->groupBy('bucket_key')
            ->pluck('total', 'bucket_key');

        foreach ($keys as $i => $key) {
            $t = (int) ($totaux[$key] ?? 0);
            $c = (int) ($convertis[$key] ?? 0);
            $conv['contacts'][$i]  = $t;
            $conv['converted'][$i] = $c;
            $conv['rate'][$i]      = $t > 0 ? round($c / $t * 100, 1) : null;
        }

        $srcTotaux = Contact::query()
            ->selectRaw('source, COUNT(*) as total')
            ->whereRaw(self::CONTACT_DATE . ' >= ?', [$start])
            ->groupBy('source')
            ->pluck('total', 'source');

        $srcConvertis = Contact::query()
            ->whereIn('contacts.id', $this->convertedIds())
            ->selectRaw('source, COUNT(*) as total')
            ->whereRaw(self::CONTACT_DATE . ' >= ?', [$start])
            ->groupBy('source')
            ->pluck('total', 'source');

        $conv['sources'] = $srcTotaux->map(fn ($t, $source) => [
            'key'       => $source,
            'total'     => (int) $t,
            'converted' => (int) ($srcConvertis[$source] ?? 0),
            'rate'      => $t > 0 ? round(($srcConvertis[$source] ?? 0) / $t * 100, 1) : 0,
        ])->values()->all();

        return response()->json([
            'period'      => $period,
            'range'       => $range,
            'labels'      => $labels,
            'titles'      => $titles,
            'contacts'    => $contacts,
            'messages'    => $messages,
            'commandes'   => $commandes,
            'chiffre'     => $chiffre,
            'conversion'  => $conv,
            'top_clients' => $this->topClients($start),
            'top_livres'  => Commande::topLivres($start, 5),
        ]);
    }

    /** Données de fidélité (JSON) pour des fenêtres de 1, 2, 3 mois ou 1 an. */
    public function loyalty(Request $request): JsonResponse
    {
        $months = (int) $request->query('months', 3);
        if (!isset(self::LOYALTY_UNITS[$months])) {
            $months = 3;
        }

        return response()->json($this->loyaltyData($months, self::LOYALTY_UNITS[$months]));
    }

    /** Meilleurs clients de la période : classés par nombre de commandes (le montant ne départage que les égalités). */
    private function topClients(CarbonInterface $start): array
    {
        $lignes = Commande::query()
            ->selectRaw('contact_id, COUNT(*) as nb, COALESCE(SUM(montant_total), 0) as total')
            ->where('statut', '!=', 'annulee')
            ->where('date_commande', '>=', $start)
            ->groupBy('contact_id')
            ->orderByDesc('nb')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $contacts = Contact::whereIn('id', $lignes->pluck('contact_id'))->get()->keyBy('id');

        return $lignes->map(fn ($l) => [
            'id'        => (int) $l->contact_id,
            'name'      => $contacts->get($l->contact_id)?->name ?? '—',
            'commandes' => (int) $l->nb,
            'total'     => (int) $l->total,
        ])->values()->all();
    }

    /** Contacts ayant au moins une commande non annulée (sous-requête non corrélée, compatible ONLY_FULL_GROUP_BY). */
    private function convertedIds(): Builder
    {
        return Commande::query()
            ->select('contact_id')
            ->where('statut', '!=', 'annulee');
    }

    /** Contacts arrivés dans [from, to[ : total et nombre ayant commandé. */
    private function cohortConversion(CarbonInterface $from, CarbonInterface $to): array
    {
        $base = fn () => Contact::query()
            ->whereRaw(self::CONTACT_DATE . ' >= ? and ' . self::CONTACT_DATE . ' < ?', [$from, $to]);

        $total     = $base()->count();
        $converted = $base()->whereIn('contacts.id', $this->convertedIds())->count();

        return [$total, $converted];
    }

    /**
     * Fidélité par fenêtres de $months mois (la plus récente se termine à la fin du mois en cours).
     * Taux de fidélité d'une fenêtre = clients actifs dans la fenêtre précédente qui recommandent dans celle-ci.
     */
    private function loyaltyData(int $months, int $count): array
    {
        $end    = now()->startOfMonth()->addMonth();
        $oldest = $end->copy()->subMonthsNoOverflow($months * ($count + 1));

        $windows = [];
        for ($k = 0; $k <= $count; $k++) {
            $windows[] = [
                's'   => $oldest->copy()->addMonthsNoOverflow($k * $months),
                'e'   => $oldest->copy()->addMonthsNoOverflow(($k + 1) * $months),
                'ids' => [],
            ];
        }

        $orders = Commande::query()
            ->where('statut', '!=', 'annulee')
            ->where('date_commande', '>=', $oldest)
            ->where('date_commande', '<', $end)
            ->get(['contact_id', 'date_commande']);

        foreach ($orders as $o) {
            foreach ($windows as $k => $w) {
                if ($o->date_commande >= $w['s'] && $o->date_commande < $w['e']) {
                    $windows[$k]['ids'][$o->contact_id] = ($windows[$k]['ids'][$o->contact_id] ?? 0) + 1;
                    break;
                }
            }
        }

        // Date de la toute première commande de chaque client
        $first = Commande::query()
            ->where('statut', '!=', 'annulee')
            ->selectRaw('contact_id, MIN(date_commande) as first_at')
            ->groupBy('contact_id')
            ->pluck('first_at', 'contact_id');

        $out = [
            'months' => $months, 'labels' => [], 'titles' => [], 'active' => [], 'retained' => [],
            'back' => [], 'new' => [], 'lost' => [], 'rate' => [], 'commandes' => [],
        ];

        for ($k = 1; $k <= $count; $k++) {
            $prev = $windows[$k - 1]['ids'];
            $cur  = $windows[$k]['ids'];
            $s    = $windows[$k]['s'];

            $retained = count(array_intersect_key($cur, $prev));
            $new = 0;
            foreach (array_keys($cur) as $cid) {
                if (isset($first[$cid]) && Carbon::parse($first[$cid]) >= $s) {
                    $new++;
                }
            }

            $out['labels'][]    = $this->loyaltyLabel($windows[$k]['s'], $windows[$k]['e'], $months, false);
            $out['titles'][]    = $this->loyaltyLabel($windows[$k]['s'], $windows[$k]['e'], $months, true);
            $out['active'][]    = count($cur);
            $out['retained'][]  = $retained;
            $out['new'][]       = $new;
            $out['back'][]      = max(0, count($cur) - $retained - $new);
            $out['lost'][]      = count($prev) - $retained;
            $out['rate'][]      = count($prev) > 0 ? round($retained / count($prev) * 100, 1) : null;
            $out['commandes'][] = array_sum($cur);
        }

        // Vue globale : récurrence et niveaux (mêmes seuils que la page « Meilleurs clients »)
        $nbs = Commande::query()
            ->where('statut', '!=', 'annulee')
            ->selectRaw('contact_id, COUNT(*) as nb')
            ->groupBy('contact_id')
            ->pluck('nb');

        $clients   = $nbs->count();
        $repeaters = $nbs->filter(fn ($n) => $n >= 2)->count();

        $out['clients']     = $clients;
        $out['repeaters']   = $repeaters;
        $out['repeat_rate'] = $clients > 0 ? round($repeaters / $clients * 100, 1) : null;
        $out['tiers']       = [
            'vip'     => $nbs->filter(fn ($n) => $n >= 5)->count(),
            'fidele'  => $nbs->filter(fn ($n) => $n >= 2 && $n < 5)->count(),
            'nouveau' => $nbs->filter(fn ($n) => $n < 2)->count(),
        ];

        return $out;
    }

    private function loyaltyLabel(CarbonInterface $s, CarbonInterface $e, int $months, bool $long): string
    {
        $s    = $s->copy()->locale('fr');
        $last = $e->copy()->subMonthNoOverflow()->locale('fr');

        if ($months === 1) {
            return $s->translatedFormat($long ? 'F Y' : 'M Y');
        }

        return $s->translatedFormat($long ? 'F Y' : 'M y') . ' → ' . $last->translatedFormat($long ? 'F Y' : 'M y');
    }

    /** @return array{0: CarbonInterface, 1: string[], 2: string[], 3: string[]} */
    private function buckets(string $period, int $range): array
    {
        $keys = $labels = $titles = [];

        if ($period === 'day') {
            $start = today()->subDays($range - 1);
            for ($i = 0; $i < $range; $i++) {
                $d = $start->copy()->addDays($i);
                $keys[]   = $d->format('Y-m-d');
                $labels[] = $d->format('d/m');
                $titles[] = $d->locale('fr')->translatedFormat('l d F Y');
            }
        } elseif ($period === 'month') {
            $start = now()->startOfMonth()->subMonths($range - 1);
            for ($i = 0; $i < $range; $i++) {
                $d = $start->copy()->addMonthsNoOverflow($i);
                $keys[]   = $d->format('Y-m');
                $labels[] = $d->locale('fr')->translatedFormat('M Y');
                $titles[] = $d->locale('fr')->translatedFormat('F Y');
            }
        } else {
            $start = now()->startOfYear()->subYears($range - 1);
            for ($i = 0; $i < $range; $i++) {
                $d = $start->copy()->addYears($i);
                $keys[]   = $d->format('Y');
                $labels[] = $d->format('Y');
                $titles[] = 'Année ' . $d->format('Y');
            }
        }

        return [$start, $keys, $labels, $titles];
    }

    /** Expression SQL qui regroupe une date par jour, mois ou année (MySQL, PostgreSQL, SQLite). */
    private function bucketExpr(string $column, string $period): string
    {
        $format = match ($period) {
            'year'  => '%Y',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('{$format}', {$column})",
            'pgsql'  => "to_char({$column}, '" . str_replace(['%Y', '%m', '%d'], ['YYYY', 'MM', 'DD'], $format) . "')",
            default  => "DATE_FORMAT({$column}, '{$format}')",
        };
    }

    /** Date d'un envoi : sent_at, sinon la dernière mise à jour (cas d'un échec). */
    private function logDateExpr(): string
    {
        $existing = array_values(array_intersect(
            ['sent_at', 'updated_at', 'created_at'],
            Schema::getColumnListing('message_logs')
        ));

        return count($existing) > 1 ? 'COALESCE(' . implode(', ', $existing) . ')' : ($existing[0] ?? 'sent_at');
    }

    private function acquired(CarbonInterface $from, CarbonInterface $to): int
    {
        return Contact::whereRaw(
            self::CONTACT_DATE . ' >= ? and ' . self::CONTACT_DATE . ' < ?',
            [$from, $to]
        )->count();
    }

    private function sent(string $channel, CarbonInterface $from, CarbonInterface $to): int
    {
        return MessageLog::where('channel', $channel)
            ->where('status', 'sent')
            ->where('sent_at', '>=', $from)
            ->where('sent_at', '<', $to)
            ->count();
    }

    private function commandes(CarbonInterface $from, CarbonInterface $to, ?string $statut = null): int
    {
        return Commande::where('date_commande', '>=', $from)
            ->where('date_commande', '<', $to)
            ->when($statut, fn ($q, $s) => $q->where('statut', $s))
            ->count();
    }

    /** Chiffre d'affaires (commandes non annulées). */
    private function chiffre(CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) Commande::where('date_commande', '>=', $from)
            ->where('date_commande', '<', $to)
            ->where('statut', '!=', 'annulee')
            ->sum('montant_total');
    }

    /** Variation en % (null s'il n'y a rien à comparer). */
    private function trend(int $current, int $previous): ?float
    {
        return $previous === 0 ? null : round(($current - $previous) / $previous * 100, 1);
    }
}