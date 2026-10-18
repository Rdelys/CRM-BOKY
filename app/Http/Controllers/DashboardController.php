<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\MessageLog;
use Carbon\CarbonInterface;
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

        $stats = [
            'contacts'   => Contact::count(),
            'prospects'  => Contact::where('status', 'prospect')->count(),
            'clients'    => Contact::where('status', 'client')->count(),
            'campaigns'  => Campaign::where('status', 'sent')->count(),
            'reachable'  => Contact::whereNotNull('facebook_psid')
                ->where('unsubscribed', false)
                ->where('last_fb_interaction_at', '>=', now()->subHours(24))
                ->count(),
            'new_today'        => $newToday,
            'new_today_trend'  => $this->trend($newToday, $newYesterday),
            'new_month'        => $newMonth,
            'new_month_trend'  => $this->trend($newMonth, $newPrevMonth),
            'mails_month'      => $mailsMonth,
            'mails_month_trend' => $this->trend($mailsMonth, $mailsPrev),
        ];

        $latestContacts  = Contact::latest()->limit(6)->get();
        $latestCampaigns = Campaign::latest()->limit(5)->get();

        return view('dashboard', compact('stats', 'latestContacts', 'latestCampaigns'));
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

        return response()->json([
            'period'   => $period,
            'range'    => $range,
            'labels'   => $labels,
            'titles'   => $titles,
            'contacts' => $contacts,
            'messages' => $messages,
        ]);
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

    /** Variation en % (null s'il n'y a rien à comparer). */
    private function trend(int $current, int $previous): ?float
    {
        return $previous === 0 ? null : round(($current - $previous) / $previous * 100, 1);
    }
}