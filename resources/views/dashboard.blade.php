@extends('layouts.crm')
@section('title', 'Tableau de bord')

@section('content')
@php
    $colors = [
        'website'  => '#6366f1',
        'direct'   => '#10b981',
        'facebook' => '#2563eb',
        'whatsapp' => '#84cc16',
        'other'    => '#94a3b8',
    ];
    $sources = collect(\App\Models\Contact::SOURCES)
        ->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'color' => $colors[$key] ?? '#94a3b8'])
        ->values();

    $firstName = \Illuminate\Support\Str::before((string) auth()->user()->name, ' ');

    $trend = function (?float $v, string $suffix) {
        if ($v === null) {
            return '<span class="text-xs text-slate-400">Pas de comparaison</span>';
        }
        $cls = $v >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700';
        $arrow = $v >= 0 ? '▲' : '▼';

        return '<span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ' . $cls . '">'
            . $arrow . ' ' . number_format(abs($v), 0, ',', ' ') . ' %</span> '
            . '<span class="text-xs text-slate-400">' . $suffix . '</span>';
    };
@endphp

{{-- En-tête --}}
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-sm font-medium text-blue-600">{{ now()->locale('fr')->translatedFormat('l d F Y') }}</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900">Bonjour{{ $firstName !== '' ? ', ' . $firstName : '' }}</h1>
        <p class="mt-1 text-sm text-slate-500">Voici l'activité de votre CRM.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('contacts.create') }}" class="btn-outline">Nouveau contact</a>
        <a href="{{ route('campaigns.create') }}" class="btn-primary">Nouvelle campagne</a>
    </div>
</div>

{{-- KPI --}}
<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 p-5 text-white shadow-lg shadow-blue-600/20">
        <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10"></div>
        <div class="absolute -bottom-10 right-10 h-24 w-24 rounded-full bg-white/5"></div>
        <div class="relative">
            <div class="text-xs font-semibold uppercase tracking-wider text-blue-100">Contacts</div>
            <div class="mt-2 text-4xl font-bold">{{ number_format($stats['contacts'], 0, ',', ' ') }}</div>
            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-blue-100">
                <span><strong class="text-white">{{ $stats['prospects'] }}</strong> prospects</span>
                <span><strong class="text-white">{{ $stats['clients'] }}</strong> clients</span>
                <span><strong class="text-white">{{ $stats['reachable'] }}</strong> Messenger joignables</span>
            </div>
        </div>
    </div>

    <div class="card p-5">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Nouveaux aujourd'hui</span>
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
            </span>
        </div>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ $stats['new_today'] }}</div>
        <div class="mt-3">{!! $trend($stats['new_today_trend'], 'vs hier') !!}</div>
    </div>

    <div class="card p-5">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Nouveaux ce mois</span>
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            </span>
        </div>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ $stats['new_month'] }}</div>
        <div class="mt-3">{!! $trend($stats['new_month_trend'], 'vs mois dernier') !!}</div>
    </div>

    <div class="card p-5">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Emails ce mois</span>
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 7L2 7"/></svg>
            </span>
        </div>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($stats['mails_month'], 0, ',', ' ') }}</div>
        <div class="mt-3">{!! $trend($stats['mails_month_trend'], 'vs mois dernier') !!}</div>
    </div>
</div>

{{-- Filtre de période --}}
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <h2 class="text-lg font-bold text-slate-900">Statistiques</h2>
    <div class="flex flex-wrap items-center gap-3">
        <span id="loading" class="hidden text-xs text-slate-400">Chargement…</span>
        <span id="error" class="hidden text-xs text-red-600">Impossible de charger les statistiques.</span>
        <div class="seg" id="period-seg">
            <button type="button" class="seg-btn" data-period="day">Jour</button>
            <button type="button" class="seg-btn" data-period="month">Mois</button>
            <button type="button" class="seg-btn" data-period="year">Année</button>
        </div>
        <select id="range" class="input !w-auto !py-2"></select>
    </div>
</div>

{{-- Nouveaux contacts --}}
<div class="card mb-5 p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-base font-bold text-slate-900">Nouveaux contacts</h2>
            <p class="text-sm text-slate-500"><span id="contacts-total" class="font-semibold text-slate-900">0</span> sur la période</p>
        </div>
        <p class="text-xs text-slate-400">Cliquez sur une source pour l'isoler</p>
    </div>
    <div id="source-chips" class="mt-4 flex flex-wrap gap-2"></div>
    <div class="relative mt-4 h-72">
        <canvas id="contacts-chart"></canvas>
        <p id="contacts-empty" class="absolute inset-0 hidden items-center justify-center text-sm text-slate-400">Aucun contact sur cette période</p>
    </div>
</div>

<div class="mb-5 grid grid-cols-1 gap-5 lg:grid-cols-3">
    {{-- Origine --}}
    <div class="card p-6">
        <h2 class="text-base font-bold text-slate-900">Origine des contacts</h2>
        <div class="relative mx-auto mt-4 h-48 w-48">
            <canvas id="origin-chart"></canvas>
            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                <span id="origin-total" class="text-2xl font-bold text-slate-900">0</span>
                <span class="text-xs text-slate-400">contacts</span>
            </div>
        </div>
        <ul id="origin-list" class="mt-5 space-y-2 text-sm"></ul>
    </div>

    {{-- Messages --}}
    <div class="card p-6 lg:col-span-2">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-slate-900">Messages envoyés</h2>
                <p class="text-sm text-slate-500">
                    <span id="msg-sent" class="font-semibold text-slate-900">0</span> envoyés ·
                    <span id="msg-failed" class="font-semibold text-red-600">0</span> échecs ·
                    réussite <span id="msg-rate" class="font-semibold text-slate-900">—</span>
                </p>
            </div>
            <div class="seg" id="channel-seg">
                <button type="button" class="seg-btn" data-channel="email">Email</button>
                <button type="button" class="seg-btn" data-channel="sms">SMS</button>
                <button type="button" class="seg-btn" data-channel="messenger">Messenger</button>
                <button type="button" class="seg-btn" data-channel="all">Tous</button>
            </div>
        </div>
        <div class="relative mt-4 h-64">
            <canvas id="messages-chart"></canvas>
            <p id="messages-empty" class="absolute inset-0 hidden items-center justify-center text-sm text-slate-400">Aucun envoi sur cette période</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
    {{-- Derniers contacts --}}
    <div class="card p-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900">Derniers contacts</h2>
            <a href="{{ route('contacts.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">Voir tout</a>
        </div>
        <ul class="divide-y divide-slate-100">
            @forelse($latestContacts as $contact)
                <li class="flex items-center justify-between py-2.5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-700">
                            {{ strtoupper(mb_substr($contact->name, 0, 1)) }}
                        </span>
                        <div>
                            <div class="text-sm font-medium text-slate-900">{{ $contact->name }}</div>
                            <div class="text-xs text-slate-500">
                                {{ $contact->phone ?: ($contact->email ?: ($contact->facebook_psid ? 'Messenger' : '—')) }}
                                · {{ \App\Models\Contact::SOURCES[$contact->source] ?? $contact->source }}
                            </div>
                        </div>
                    </div>
                    @include('contacts._status', ['status' => $contact->status])
                </li>
            @empty
                <li class="py-2 text-sm text-slate-500">Aucun contact.</li>
            @endforelse
        </ul>
    </div>

    {{-- Dernières campagnes --}}
    <div class="card p-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900">Dernières campagnes</h2>
            <a href="{{ route('campaigns.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">Voir tout</a>
        </div>
        <ul class="divide-y divide-slate-100">
            @forelse($latestCampaigns as $campaign)
                <li class="flex items-center justify-between py-2.5">
                    <a href="{{ route('campaigns.show', $campaign) }}" class="text-sm font-medium text-slate-900 hover:text-blue-600">
                        {{ $campaign->name }}
                        <span class="ml-1 text-xs font-bold uppercase text-blue-600">{{ $campaign->channel }}</span>
                    </a>
                    @include('campaigns._status', ['status' => $campaign->status])
                </li>
            @empty
                <li class="py-2 text-sm text-slate-500">Aucune campagne.</li>
            @endforelse
        </ul>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    const SOURCES = @json($sources);
    const STATS_URL = @json(route('dashboard.stats'));
    const RANGES = { day: [7, 30, 90], month: [6, 12, 24], year: [3, 5, 10] };
    const DEFAULT_RANGE = { day: 30, month: 12, year: 5 };
    const UNIT = { day: 'jours', month: 'mois', year: 'ans' };
    const CHANNELS = ['email', 'sms', 'messenger'];
    const TTL = 120000;
    const nf = new Intl.NumberFormat('fr-FR');

    const state = {
        period: 'day',
        range: 30,
        channel: 'email',
        active: new Set(SOURCES.map(s => s.key)),
    };
    const cache = new Map();
    let data = null;
    let reqId = 0;
    let prefetched = false;

    const $ = id => document.getElementById(id);
    const sum = arr => arr.reduce((a, b) => a + b, 0);

    Chart.defaults.font.family = 'inherit';
    Chart.defaults.color = '#64748b';

    function barOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: { title: items => (data && data.titles[items[0].dataIndex]) || '' },
                },
            },
            scales: {
                x: { stacked: true, grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
                y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' }, border: { display: false } },
            },
        };
    }

    const contactsChart = new Chart($('contacts-chart'), {
        type: 'bar', data: { labels: [], datasets: [] }, options: barOptions(),
    });
    const messagesChart = new Chart($('messages-chart'), {
        type: 'bar', data: { labels: [], datasets: [] }, options: barOptions(),
    });
    const originChart = new Chart($('origin-chart'), {
        type: 'doughnut',
        data: {
            labels: SOURCES.map(s => s.label),
            datasets: [{ data: SOURCES.map(() => 0), backgroundColor: SOURCES.map(s => s.color), borderWidth: 0 }],
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { display: false } } },
    });

    function dataset(label, values, color) {
        return { label, data: values, backgroundColor: color, borderRadius: 3, borderSkipped: false, maxBarThickness: 32 };
    }

    function chip(label, color, count, off, onClick) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'chip' + (off ? ' is-off' : '');
        if (color) {
            const dot = document.createElement('span');
            dot.className = 'h-2 w-2 rounded-full';
            dot.style.background = color;
            b.appendChild(dot);
        }
        b.appendChild(document.createTextNode(label));
        if (count !== null) {
            const c = document.createElement('span');
            c.className = 'font-semibold text-slate-900';
            c.textContent = nf.format(count);
            b.appendChild(c);
        }
        b.addEventListener('click', onClick);
        return b;
    }

    function toggleSource(key) {
        if (state.active.size === SOURCES.length) {
            state.active = new Set([key]);              // premier clic : on isole la source
        } else if (state.active.has(key)) {
            state.active.delete(key);
            if (state.active.size === 0) state.active = new Set(SOURCES.map(s => s.key));
        } else {
            state.active.add(key);
        }
        renderContacts();
    }

    function renderContacts() {
        const active = SOURCES.filter(s => state.active.has(s.key));

        contactsChart.data.labels = data.labels;
        contactsChart.data.datasets = active.map(s => dataset(s.label, data.contacts[s.key] || [], s.color));
        contactsChart.update();

        const total = active.reduce((t, s) => t + sum(data.contacts[s.key] || []), 0);
        $('contacts-total').textContent = nf.format(total);
        $('contacts-empty').classList.toggle('hidden', total > 0);
        $('contacts-empty').classList.toggle('flex', total === 0);

        const box = $('source-chips');
        box.innerHTML = '';
        const all = SOURCES.reduce((t, s) => t + sum(data.contacts[s.key] || []), 0);
        box.appendChild(chip('Tous', null, all, state.active.size !== SOURCES.length, () => {
            state.active = new Set(SOURCES.map(s => s.key));
            renderContacts();
        }));
        SOURCES.forEach(s => {
            box.appendChild(chip(s.label, s.color, sum(data.contacts[s.key] || []), !state.active.has(s.key), () => toggleSource(s.key)));
        });
    }

    function renderOrigin() {
        const totals = SOURCES.map(s => sum(data.contacts[s.key] || []));
        const total = sum(totals);

        originChart.data.datasets[0].data = totals;
        originChart.update();
        $('origin-total').textContent = nf.format(total);

        const list = $('origin-list');
        list.innerHTML = '';
        SOURCES.forEach((s, i) => {
            const li = document.createElement('li');
            li.className = 'flex items-center justify-between';
            const left = document.createElement('span');
            left.className = 'flex items-center gap-2 text-slate-600';
            const dot = document.createElement('span');
            dot.className = 'h-2.5 w-2.5 rounded-full';
            dot.style.background = s.color;
            left.appendChild(dot);
            left.appendChild(document.createTextNode(s.label));
            const right = document.createElement('span');
            right.className = 'font-semibold text-slate-900';
            right.textContent = nf.format(totals[i]) + (total ? '  ·  ' + Math.round(totals[i] / total * 100) + ' %' : '');
            li.appendChild(left);
            li.appendChild(right);
            list.appendChild(li);
        });
    }

    function renderMessages() {
        const channels = state.channel === 'all' ? CHANNELS : [state.channel];
        const merge = key => data.labels.map((_, i) =>
            channels.reduce((t, c) => t + ((data.messages[c] && data.messages[c][key][i]) || 0), 0));

        const sent = merge('sent');
        const failed = merge('failed');

        messagesChart.data.labels = data.labels;
        messagesChart.data.datasets = [
            dataset('Envoyés', sent, '#2563eb'),
            dataset('Échecs', failed, '#f87171'),
        ];
        messagesChart.update();

        const s = sum(sent), f = sum(failed), t = s + f;
        $('msg-sent').textContent = nf.format(s);
        $('msg-failed').textContent = nf.format(f);
        $('msg-rate').textContent = t ? Math.round(s / t * 100) + ' %' : '—';
        $('messages-empty').classList.toggle('hidden', t > 0);
        $('messages-empty').classList.toggle('flex', t === 0);
    }

    function renderAll() {
        renderContacts();
        renderOrigin();
        renderMessages();
    }

    function syncControls() {
        document.querySelectorAll('#period-seg [data-period]').forEach(b =>
            b.classList.toggle('is-active', b.dataset.period === state.period));
        document.querySelectorAll('#channel-seg [data-channel]').forEach(b =>
            b.classList.toggle('is-active', b.dataset.channel === state.channel));

        const sel = $('range');
        sel.innerHTML = '';
        RANGES[state.period].forEach(n => {
            const o = document.createElement('option');
            o.value = n;
            o.textContent = 'Derniers ' + n + ' ' + UNIT[state.period];
            o.selected = n === state.range;
            sel.appendChild(o);
        });
    }

    async function fetchStats(period, range) {
        const key = period + '-' + range;
        const hit = cache.get(key);
        if (hit && Date.now() - hit.at < TTL) return hit.json;

        const res = await fetch(STATS_URL + '?period=' + period + '&range=' + range, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);

        const json = await res.json();
        cache.set(key, { at: Date.now(), json });
        return json;
    }

    async function load() {
        const id = ++reqId;
        $('loading').classList.remove('hidden');
        $('error').classList.add('hidden');

        try {
            const json = await fetchStats(state.period, state.range);
            if (id !== reqId) return;           // une réponse plus récente est déjà arrivée
            data = json;
            renderAll();

            if (!prefetched) {                  // précharge les autres périodes pour des changements instantanés
                prefetched = true;
                ['month', 'year'].forEach(p => fetchStats(p, DEFAULT_RANGE[p]).catch(() => {}));
            }
        } catch (e) {
            if (id === reqId) $('error').classList.remove('hidden');
        } finally {
            if (id === reqId) $('loading').classList.add('hidden');
        }
    }

    document.querySelectorAll('#period-seg [data-period]').forEach(b => b.addEventListener('click', () => {
        state.period = b.dataset.period;
        state.range = DEFAULT_RANGE[state.period];
        syncControls();
        load();
    }));

    document.querySelectorAll('#channel-seg [data-channel]').forEach(b => b.addEventListener('click', () => {
        state.channel = b.dataset.channel;
        syncControls();
        if (data) renderMessages();             // pas de requête : filtre instantané
    }));

    $('range').addEventListener('change', e => {
        state.range = parseInt(e.target.value, 10);
        load();
    });

    syncControls();
    load();
})();
</script>
@endpush
@endsection