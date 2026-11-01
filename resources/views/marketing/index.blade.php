@extends('layouts.crm')
@section('title', 'Marketing')

@section('content')
@php
    $fmt = function ($type, $v, $dec = 1) {
        if ($v === null) {
            return '—';
        }

        return match ($type) {
            'money' => number_format($v, 0, ',', ' ') . ' Ar',
            'rate'  => number_format($v, $dec, ',', ' ') . ' %',
            default => number_format($v, 0, ',', ' '),
        };
    };
    $q = fn ($ref, $p = null) => route('marketing.index', ['period' => $p ?? $period, 'ref' => $ref]);
@endphp

{{-- En-tête + filtres --}}
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-sm font-medium text-blue-600">{{ $r['label'] }}{{ $r['is_current'] ? ' · en cours' : '' }}</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">Marketing</h1>
        <p class="mt-1 text-sm text-slate-500">Conversion, panier moyen, visibilité Facebook, coût d'acquisition et retour sur investissement.</p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <div class="seg">
            <a href="{{ route('marketing.index', ['period' => 'month']) }}" class="seg-btn {{ $period === 'month' ? 'is-active' : '' }}">Mensuel</a>
            <a href="{{ route('marketing.index', ['period' => 'year']) }}" class="seg-btn {{ $period === 'year' ? 'is-active' : '' }}">Annuel</a>
        </div>

        <div class="flex items-center gap-1">
            <a href="{{ $q($r['prev_ref']) }}" class="btn-outline" aria-label="Période précédente">←</a>
            <form method="GET" action="{{ route('marketing.index') }}">
                <input type="hidden" name="period" value="{{ $period }}">
                @if($period === 'month')
                    <input type="month" name="ref" value="{{ $r['ref'] }}" class="input !w-auto !py-2" onchange="this.form.submit()">
                @else
                    <input type="number" name="ref" value="{{ $r['ref'] }}" min="2015" max="{{ now()->year }}" class="input !w-28 !py-2" onchange="this.form.submit()">
                @endif
            </form>
            @if($r['next_ref'])
                <a href="{{ $q($r['next_ref']) }}" class="btn-outline" aria-label="Période suivante">→</a>
            @else
                <span class="btn-outline pointer-events-none opacity-40">→</span>
            @endif
        </div>

        <form method="POST" action="{{ route('marketing.insights.sync') }}">
            @csrf
            <button class="btn-outline" type="submit">Actualiser la visibilité Facebook</button>
        </form>
    </div>
</div>

@if(!$hasInsights)
    <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Aucune donnée de visibilité Facebook importée. Cliquez sur « Actualiser la visibilité Facebook »
        (le token de la Page doit avoir les permissions <strong>read_insights</strong> et <strong>pages_read_engagement</strong>).
    </div>
@endif

{{-- Les 6 indicateurs --}}
<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @php $k = $kpis['conversion']; @endphp
    <div class="card p-5">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Taux de conversion</span>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ $fmt('rate', $k['value'], 1) }}</div>
        <div class="mt-3">@include('marketing._ecart', ['k' => $k, 'fmt' => $fmt])</div>
        <p class="mt-2 text-xs text-slate-500"><strong class="text-slate-900">{{ $cur['converted'] }}</strong> sur {{ $cur['total_contacts'] }} nouveaux contacts ont commandé</p>
    </div>

    @php $k = $kpis['panier']; @endphp
    <div class="card p-5">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Panier moyen</span>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ $fmt('money', $k['value']) }}</div>
        <div class="mt-3">@include('marketing._ecart', ['k' => $k, 'fmt' => $fmt])</div>
        <p class="mt-2 text-xs text-slate-500">{{ $cur['nb_orders'] }} commande(s) · CA {{ number_format($cur['ca'], 0, ',', ' ') }} Ar</p>
    </div>

    @php $k = $kpis['views']; @endphp
    <div class="card p-5">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Visibilité Facebook (vues)</span>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ $fmt('count', $k['value']) }}</div>
        <div class="mt-3">@include('marketing._ecart', ['k' => $k, 'fmt' => $fmt])</div>
        <p class="mt-2 text-xs text-slate-500">
            <strong class="text-slate-900">{{ number_format($cur['reach'], 0, ',', ' ') }}</strong> personnes atteintes ·
            {{ number_format($cur['engagements'], 0, ',', ' ') }} interactions ·
            +{{ number_format($cur['follows'], 0, ',', ' ') }} abonnés
        </p>
    </div>

    @php $k = $kpis['cac']; @endphp
    <div class="card p-5">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">CAC (coût d'acquisition client)</span>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ $fmt('money', $k['value']) }}</div>
        <div class="mt-3">@include('marketing._ecart', ['k' => $k, 'fmt' => $fmt])</div>
        <p class="mt-2 text-xs text-slate-500">
            {{ number_format($cur['spend'], 0, ',', ' ') }} Ar dépensés ÷ {{ $cur['new_customers'] }} nouveau(x) client(s)
            @if($cur['spend'] === 0) · <span class="text-amber-600">aucune dépense saisie</span>@endif
        </p>
    </div>

    @php $k = $kpis['roi']; @endphp
    <div class="card p-5">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">ROI Facebook</span>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ $fmt('rate', $k['value'], 1) }}</div>
        <div class="mt-3">@include('marketing._ecart', ['k' => $k, 'fmt' => $fmt])</div>
        <p class="mt-2 text-xs text-slate-500">
            Revenu clients Facebook {{ number_format($cur['fb_revenue'], 0, ',', ' ') }} Ar
            − dépenses {{ number_format($cur['spend_fb'], 0, ',', ' ') }} Ar
        </p>
    </div>

    @php $k = $kpis['prospection']; @endphp
    <div class="card p-5">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Taux de prospection</span>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ $fmt('rate', $k['value'], 2) }}</div>
        <div class="mt-3">@include('marketing._ecart', ['k' => $k, 'fmt' => $fmt])</div>
        <p class="mt-2 text-xs text-slate-500">
            <strong class="text-slate-900">{{ $cur['fb_prospects'] }}</strong> nouveaux contacts Facebook
            ÷ {{ number_format($cur['reach'], 0, ',', ' ') }} personnes atteintes
        </p>
    </div>
</div>

{{-- Graphiques --}}
<div class="mb-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
    <div class="card p-6">
        <h2 class="text-base font-bold text-slate-900">Visibilité et prospects Facebook</h2>
        <p class="text-sm text-slate-500">Vues, personnes atteintes et nouveaux contacts Facebook</p>
        <div class="relative mt-4 h-72"><canvas id="visi-chart"></canvas></div>
    </div>
    <div class="card p-6">
        <h2 class="text-base font-bold text-slate-900">Dépenses et revenus Facebook</h2>
        <p class="text-sm text-slate-500">Ce qui a été investi contre ce que rapportent les clients venus de Facebook</p>
        <div class="relative mt-4 h-72"><canvas id="roi-chart"></canvas></div>
    </div>
</div>

<div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
    {{-- Clients Facebook --}}
    <div class="card p-6">
        <div class="mb-1 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900">Clients venus de Facebook</h2>
            <span class="text-xs text-slate-400">{{ $fbClientsEver }} ont déjà commandé (total)</span>
        </div>
        <p class="mb-3 text-sm text-slate-500">Ayant commandé sur la période</p>
        <ul class="divide-y divide-slate-100">
            @forelse($fbClients as $row)
                <li class="flex items-center justify-between gap-3 py-2.5">
                    <div class="min-w-0">
                        <a href="{{ route('contacts.edit', $row->contact_id) }}" class="block truncate text-sm font-medium text-slate-900 hover:text-blue-600">{{ $row->contact->name ?? '—' }}</a>
                        <div class="text-xs text-slate-500">{{ $row->nb_commandes }} commande(s) · dernière le {{ \Carbon\Carbon::parse($row->derniere)->format('d/m/Y') }}</div>
                    </div>
                    <span class="shrink-0 text-sm font-semibold text-slate-900">{{ number_format($row->total, 0, ',', ' ') }} Ar</span>
                </li>
            @empty
                <li class="py-2 text-sm text-slate-500">Aucune commande de client Facebook sur cette période.</li>
            @endforelse
        </ul>
    </div>

    {{-- Dépenses --}}
    <div class="card p-6">
        <h2 class="text-base font-bold text-slate-900">Dépenses marketing</h2>
        <p class="mb-3 text-sm text-slate-500">À saisir pour calculer le CAC et le ROI</p>

        <form method="POST" action="{{ route('marketing.spends.store') }}" class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-5">
            @csrf
            <input type="date" name="spent_on" value="{{ old('spent_on', now()->toDateString()) }}" class="input" required>
            <select name="channel" class="input">
                @foreach(\App\Models\MarketingSpend::CHANNELS as $key => $label)
                    <option value="{{ $key }}" @selected(old('channel', 'facebook') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <input type="number" name="amount" min="1" placeholder="Montant (Ar)" value="{{ old('amount') }}" class="input" required>
            <input type="text" name="label" maxlength="150" placeholder="Libellé (ex : boost)" value="{{ old('label') }}" class="input">
            <button class="btn-primary" type="submit">Ajouter</button>
        </form>

        <ul class="divide-y divide-slate-100">
            @forelse($spends as $s)
                <li class="flex items-center justify-between gap-3 py-2.5">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-medium text-slate-900">
                            {{ \App\Models\MarketingSpend::CHANNELS[$s->channel] ?? $s->channel }}
                            @if($s->label) <span class="font-normal text-slate-500">· {{ $s->label }}</span> @endif
                        </div>
                        <div class="text-xs text-slate-500">{{ $s->spent_on->format('d/m/Y') }}</div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-semibold text-slate-900">{{ number_format($s->amount, 0, ',', ' ') }} Ar</span>
                        <form method="POST" action="{{ route('marketing.spends.destroy', $s) }}" onsubmit="return confirm('Supprimer cette dépense ?')">
                            @csrf @method('DELETE')
                            <button class="text-xs font-medium text-red-600 hover:text-red-800" type="submit">Supprimer</button>
                        </form>
                    </div>
                </li>
            @empty
                <li class="py-2 text-sm text-slate-500">Aucune dépense sur cette période.</li>
            @endforelse
        </ul>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    const S = @json($series);
    const nf = new Intl.NumberFormat('fr-FR');

    Chart.defaults.font.family = 'inherit';
    Chart.defaults.color = '#64748b';

    const base = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: true, position: 'bottom', labels: { boxWidth: 10, usePointStyle: true } },
            tooltip: { backgroundColor: '#0f172a', padding: 10, cornerRadius: 8,
                callbacks: { label: c => c.dataset.label + ' : ' + nf.format(c.parsed.y) } },
        },
        scales: {
            x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 16 } },
            y: { beginAtZero: true, grid: { color: '#f1f5f9' }, border: { display: false }, ticks: { precision: 0 } },
        },
    };

    const visiOpts = JSON.parse(JSON.stringify(base));
    visiOpts.plugins.tooltip.callbacks = undefined;
    visiOpts.scales.y1 = { position: 'right', beginAtZero: true, grid: { display: false }, border: { display: false }, ticks: { precision: 0 } };

    new Chart(document.getElementById('visi-chart'), {
        type: 'bar',
        data: {
            labels: S.labels,
            datasets: [
                { type: 'line', label: 'Vues', data: S.views, borderColor: '#2563eb', backgroundColor: '#2563eb', tension: .3, pointRadius: 2, borderWidth: 2, yAxisID: 'y', order: 0 },
                { type: 'line', label: 'Personnes atteintes', data: S.reach, borderColor: '#7c3aed', backgroundColor: '#7c3aed', tension: .3, pointRadius: 2, borderWidth: 2, yAxisID: 'y', order: 1 },
                { label: 'Nouveaux contacts Facebook', data: S.prospects, backgroundColor: '#10b981', borderRadius: 3, maxBarThickness: 24, yAxisID: 'y1', order: 2 },
            ],
        },
        options: visiOpts,
    });

    const roiOpts = JSON.parse(JSON.stringify(base));
    roiOpts.plugins.tooltip.callbacks = undefined;

    new Chart(document.getElementById('roi-chart'), {
        type: 'bar',
        data: {
            labels: S.labels,
            datasets: [
                { label: 'Dépenses (Ar)', data: S.spend, backgroundColor: '#f87171', borderRadius: 3, maxBarThickness: 24 },
                { label: 'Revenu clients Facebook (Ar)', data: S.revenue, backgroundColor: '#10b981', borderRadius: 3, maxBarThickness: 24 },
            ],
        },
        options: roiOpts,
    });
})();
</script>
@endpush
@endsection