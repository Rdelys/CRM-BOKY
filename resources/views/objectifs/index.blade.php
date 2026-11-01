@extends('layouts.crm')
@section('title', 'Objectifs')

@section('content')
@php
    $fmt = function (array $row, $v) {
        if ($v === null) {
            return '—';
        }
        $dec = $row['decimals'] ?? 1;

        return match ($row['type']) {
            'money' => number_format($v, 0, ',', ' ') . ' Ar',
            'rate'  => number_format($v, $dec, ',', ' ') . ' %',
            default => number_format($v, 0, ',', ' '),
        };
    };

    // Valeur saisissable dans le champ (sans unité)
    $input = function (array $row) {
        if ($row['target'] === null) {
            return '';
        }
        $t = (float) $row['target'];

        return $t == floor($t) ? (string) (int) $t : rtrim(rtrim(number_format($t, 2, '.', ''), '0'), '.');
    };

    $unit = fn (array $row) => match ($row['type']) { 'money' => 'Ar', 'rate' => '%', default => '' };

    $badges = [
        'reached'  => ['Atteint',       'bg-emerald-50 text-emerald-700', '#10b981'],
        'on_track' => ['En bonne voie', 'bg-blue-50 text-blue-700',       '#2563eb'],
        'late'     => ['En retard',     'bg-amber-50 text-amber-700',     '#f59e0b'],
        'missed'   => ['Manqué',        'bg-red-50 text-red-700',         '#ef4444'],
        'nodata'   => ['Pas de donnée', 'bg-slate-100 text-slate-600',    '#94a3b8'],
    ];

    $q = fn ($ref) => route('objectifs.index', ['period' => $period, 'ref' => $ref]);
    $groups = collect($rows)->groupBy('group');
    $nbDefined = $defined->count();
@endphp

{{-- En-tête + filtres --}}
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-sm font-medium text-blue-600">{{ $r['label'] }}{{ $r['is_current'] ? ' · en cours (' . round($elapsed * 100) . ' % de la période écoulée)' : '' }}</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">Objectifs</h1>
        <p class="mt-1 text-sm text-slate-500">Fixez des cibles sur les indicateurs du tableau de bord et suivez ce qui est atteint ou non.</p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <div class="seg">
            <a href="{{ route('objectifs.index', ['period' => 'month']) }}" class="seg-btn {{ $period === 'month' ? 'is-active' : '' }}">Mensuel</a>
            <a href="{{ route('objectifs.index', ['period' => 'year']) }}" class="seg-btn {{ $period === 'year' ? 'is-active' : '' }}">Annuel</a>
        </div>

        <div class="flex items-center gap-1">
            <a href="{{ $q($r['prev_ref']) }}" class="btn-outline" aria-label="Période précédente">←</a>
            <form method="GET" action="{{ route('objectifs.index') }}">
                <input type="hidden" name="period" value="{{ $period }}">
                @if($period === 'month')
                    <input type="month" name="ref" value="{{ $r['ref'] }}" class="input !w-auto !py-2" onchange="this.form.submit()">
                @else
                    <input type="number" name="ref" value="{{ $r['ref'] }}" min="2015" max="{{ now()->year + 1 }}" class="input !w-28 !py-2" onchange="this.form.submit()">
                @endif
            </form>
            @if($r['next_ref'])
                <a href="{{ $q($r['next_ref']) }}" class="btn-outline" aria-label="Période suivante">→</a>
            @else
                <span class="btn-outline pointer-events-none opacity-40">→</span>
            @endif
        </div>

        @if($hasPrevious)
            <form method="POST" action="{{ route('objectifs.copy') }}">
                @csrf
                <input type="hidden" name="period" value="{{ $period }}">
                <input type="hidden" name="ref" value="{{ $r['ref'] }}">
                <button class="btn-outline" type="submit">Reprendre ceux de la période précédente</button>
            </form>
        @endif
    </div>
</div>

{{-- Synthèse --}}
<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <div class="card p-5">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Objectifs fixés</span>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ $nbDefined }}</div>
        <p class="mt-2 text-xs text-slate-500">sur {{ count($rows) }} indicateurs</p>
    </div>
    <div class="card p-5">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Atteints</span>
        <div class="mt-2 text-3xl font-bold text-emerald-600">{{ $count['reached'] }}</div>
        <p class="mt-2 text-xs text-slate-500">{{ $nbDefined > 0 ? round($count['reached'] / $nbDefined * 100) . ' %' : '—' }} des objectifs</p>
    </div>
    <div class="card p-5">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $r['is_current'] ? 'En bonne voie' : 'Proches' }}</span>
        <div class="mt-2 text-3xl font-bold text-blue-600">{{ $count['on_track'] }}</div>
        <p class="mt-2 text-xs text-slate-500">{{ $r['is_current'] ? 'au bon rythme' : '' }}</p>
    </div>
    <div class="card p-5">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $r['is_current'] ? 'En retard' : 'Manqués' }}</span>
        <div class="mt-2 text-3xl font-bold text-red-600">{{ $count['late'] + $count['missed'] }}</div>
        <p class="mt-2 text-xs text-slate-500">à rattraper</p>
    </div>
</div>

{{-- Suivi --}}
<div class="mb-8">
    <h2 class="mb-3 text-lg font-bold text-slate-900">Suivi des objectifs</h2>

    @if($nbDefined === 0)
        <div class="card p-6 text-sm text-slate-500">
            Aucun objectif fixé pour {{ strtolower($r['label']) }}. Renseignez des cibles dans le formulaire ci-dessous.
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($defined as $row)
                @php
                    $b = $badges[$row['status']];
                    $bar = min(100, max(0, $row['pct'] ?? 0));
                    $pace = (!empty($row['cumulative']) && empty($row['inverse']) && $r['is_current']) ? round($elapsed * 100) : null;
                @endphp
                <div class="card p-5">
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $row['label'] }}</span>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold {{ $b[1] }}">{{ !empty($row['inverse']) && $row['status'] === 'reached' ? 'Respecté' : $b[0] }}</span>
                    </div>

                    <div class="mt-2 text-3xl font-bold text-slate-900">{{ $fmt($row, $row['value']) }}</div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ !empty($row['inverse']) ? 'plafond' : 'objectif' }} :
                        <strong class="text-slate-900">{{ $fmt($row, $row['target']) }}</strong>
                        @if($row['pct'] !== null) · {{ number_format($row['pct'], 0, ',', ' ') }} % @endif
                    </p>

                    <div class="relative mt-3 h-2 w-full rounded-full bg-slate-100">
                        <div class="h-2 rounded-full" style="width: {{ $bar }}%; background: {{ $b[2] }}"></div>
                        @if($pace !== null)
                            <div class="absolute -top-1 h-4 w-0.5 bg-slate-400" style="left: {{ $pace }}%" title="Rythme attendu : {{ $pace }} %"></div>
                        @endif
                    </div>
                    @if($pace !== null)
                        <p class="mt-1.5 text-[11px] text-slate-400">Le trait gris marque le rythme attendu aujourd'hui ({{ $pace }} %).</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Définir les objectifs --}}
<form method="POST" action="{{ route('objectifs.save') }}" class="card p-6">
    @csrf
    <input type="hidden" name="period" value="{{ $period }}">
    <input type="hidden" name="ref" value="{{ $r['ref'] }}">

    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-base font-bold text-slate-900">Définir les objectifs · {{ $r['label'] }}</h2>
            <p class="text-sm text-slate-500">Laissez vide les indicateurs que vous ne souhaitez pas suivre. Pour le CAC et les dépenses, la valeur saisie est un plafond à ne pas dépasser.</p>
        </div>
        <button class="btn-primary" type="submit">Enregistrer les objectifs</button>
    </div>

    <div class="grid grid-cols-1 gap-x-8 gap-y-6 lg:grid-cols-2">
        @foreach($groups as $group => $items)
            <div>
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $group }}</h3>
                <div class="space-y-2">
                    @foreach($items as $row)
                        <label class="flex items-center justify-between gap-3">
                            <span class="min-w-0 text-sm text-slate-700">
                                {{ $row['label'] }}
                                <span class="block text-xs text-slate-400">actuel : {{ $fmt($row, $row['value']) }}</span>
                            </span>
                            <span class="flex shrink-0 items-center gap-2">
                                <input type="text" inputmode="decimal" name="targets[{{ $row['key'] }}]"
                                       value="{{ old('targets.' . $row['key'], $input($row)) }}"
                                       placeholder="—" class="input !w-36 !py-2 text-right">
                                <span class="w-6 text-xs text-slate-400">{{ $unit($row) }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 flex justify-end">
        <button class="btn-primary" type="submit">Enregistrer les objectifs</button>
    </div>
</form>
@endsection