<?php

namespace App\Http\Controllers;

use App\Models\Objectif;
use App\Services\MarketingStatsService;
use App\Services\ObjectifService;
use Illuminate\Http\Request;

class ObjectifController extends Controller
{
    public function __construct(
        private MarketingStatsService $stats,
        private ObjectifService $service,
    ) {
    }

    /** Page « Objectifs » : mensuel ou annuel. */
    public function index(Request $request)
    {
        $period = $request->query('period') === 'year' ? 'year' : 'month';
        $r = $this->stats->range($period, $request->query('ref'));

        $values  = $this->service->values($r['from'], $r['end']);
        $targets = Objectif::where('period', $period)->where('ref', $r['ref'])->pluck('target', 'metric');

        $total   = max(1, $r['to']->getTimestamp() - $r['from']->getTimestamp());
        $elapsed = min(1, max(0, ($r['end']->getTimestamp() - $r['from']->getTimestamp()) / $total));

        $rows = [];
        $count = ['reached' => 0, 'on_track' => 0, 'late' => 0, 'missed' => 0, 'nodata' => 0];

        foreach (Objectif::METRICS as $key => $def) {
            $target = $targets[$key] ?? null;
            $value  = $values[$key] ?? null;

            $eval = $target !== null
                ? $this->service->evaluate($key, (float) $target, $value, $elapsed, $r['is_current'])
                : null;

            if ($eval) {
                $count[$eval['status']]++;
            }

            $rows[$key] = $def + [
                'key'    => $key,
                'value'  => $value,
                'target' => $target,
                'pct'    => $eval['pct'] ?? null,
                'status' => $eval['status'] ?? null,
            ];
        }

        $defined = collect($rows)->whereNotNull('target');
        $hasPrevious = Objectif::where('period', $period)->where('ref', $r['prev_ref'])->exists();

        return view('objectifs.index', compact('period', 'r', 'rows', 'defined', 'count', 'elapsed', 'hasPrevious'));
    }

    /** Enregistre tous les objectifs de la période d'un coup (champ vide = pas d'objectif). */
    public function save(Request $request)
    {
        $period = $request->input('period') === 'year' ? 'year' : 'month';
        $ref    = (string) $request->input('ref');

        abort_unless(
            $period === 'year' ? preg_match('/^\d{4}$/', $ref) : preg_match('/^\d{4}-\d{2}$/', $ref),
            422
        );

        $input = (array) $request->input('targets', []);
        $errors = [];

        foreach (Objectif::METRICS as $key => $def) {
            $raw = trim((string) ($input[$key] ?? ''));

            if ($raw === '') {
                Objectif::where(compact('period', 'ref'))->where('metric', $key)->delete();
                continue;
            }

            $number = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $raw);

            if (!is_numeric($number) || (float) $number < 0) {
                $errors["targets.$key"] = "Objectif invalide pour « {$def['label']} ».";
                continue;
            }

            Objectif::updateOrCreate(
                ['metric' => $key, 'period' => $period, 'ref' => $ref],
                ['target' => (float) $number]
            );
        }

        if ($errors) {
            return back()->withErrors($errors)->withInput();
        }

        return redirect()
            ->route('objectifs.index', ['period' => $period, 'ref' => $ref])
            ->with('success', 'Objectifs enregistrés.');
    }

    /** Reprend les objectifs de la période précédente (sans écraser ceux déjà saisis). */
    public function copy(Request $request)
    {
        $period = $request->input('period') === 'year' ? 'year' : 'month';
        $r = $this->stats->range($period, (string) $request->input('ref'));

        $copied = 0;

        foreach (Objectif::where('period', $period)->where('ref', $r['prev_ref'])->get() as $o) {
            $new = Objectif::firstOrCreate(
                ['metric' => $o->metric, 'period' => $period, 'ref' => $r['ref']],
                ['target' => $o->target]
            );
            $copied += $new->wasRecentlyCreated ? 1 : 0;
        }

        return redirect()
            ->route('objectifs.index', ['period' => $period, 'ref' => $r['ref']])
            ->with('success', $copied . ' objectif(s) repris de la période précédente.');
    }
}