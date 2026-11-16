@extends('layouts.crm')
@section('title', 'Satisfaction')

@section('content')
@php
    $topSource = $parSource->first();
    $moyenneEmoji = $moyenne > 0 ? $notes[(int) round($moyenne)]['emoji'] : '—';
@endphp

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Satisfaction clients</h1>
        <p class="text-sm text-slate-500">Avis reçus via le lien de satisfaction.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('satisfactions.wall') }}" target="_blank" class="btn-outline">Voir la page publique</a>
        <button type="button" class="btn-dark" data-copy="{{ route('satisfactions.wall') }}">Copier le lien des avis</button>
        <a href="{{ route('satisfactions.public') }}" target="_blank" class="btn-outline">Voir le formulaire</a>
    </div>
</div>

{{-- Liens à envoyer --}}
<div class="card mb-6 p-5">
    <h2 class="mb-1 text-sm font-semibold text-slate-900">Liens à envoyer aux clients</h2>
    <p class="mb-4 text-xs text-slate-500">Utilisez un lien différent par canal pour savoir d'où viennent les avis.</p>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($sources as $key => $label)
            @php $url = route('satisfactions.public', ['src' => $key]); @endphp
            <div class="rounded-xl border border-slate-200 p-3">
                <div class="mb-2 text-xs font-semibold text-slate-700">{{ $label }}</div>
                <div class="flex gap-2">
                    <input type="text" readonly value="{{ $url }}" class="input !py-1.5 text-xs" onclick="this.select()">
                    <button type="button" class="btn-dark !px-3 !py-1.5 text-xs" data-copy="{{ $url }}">Copier</button>
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- Statistiques --}}
<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <div class="card p-5">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Avis reçus</div>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ $total }}</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Note moyenne</div>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ $moyenneEmoji }} {{ $moyenne }}<span class="text-base font-medium text-slate-400">/5</span></div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Clients satisfaits</div>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ $taux }}%</div>
        <div class="text-xs text-slate-400">notes 🙂 et 😍</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Source n°1</div>
        <div class="mt-2 truncate text-xl font-bold text-slate-900">
            {{ $topSource ? ($sources[$topSource->source] ?? ucfirst($topSource->source)) : '—' }}
        </div>
        @if($topSource)<div class="text-xs text-slate-400">{{ $topSource->total }} avis</div>@endif
    </div>
</div>

<div class="mb-6 grid gap-4 lg:grid-cols-2">
    {{-- Répartition des notes --}}
    <div class="card p-5">
        <h2 class="mb-4 text-sm font-semibold text-slate-900">Répartition des notes</h2>
        <div class="space-y-3">
            @foreach([5,4,3,2,1] as $v)
                @php
                    $c = $distribution[$v] ?? 0;
                    $pct = $total ? round($c / $total * 100) : 0;
                @endphp
                <div class="flex items-center gap-3">
                    <span class="w-8 text-2xl">{{ $notes[$v]['emoji'] }}</span>
                    <div class="h-3 flex-1 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-blue-600" style="width: {{ $pct }}%"></div>
                    </div>
                    <span class="w-20 text-right text-xs text-slate-500">{{ $c }} ({{ $pct }}%)</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Par source --}}
    <div class="card p-5">
        <h2 class="mb-4 text-sm font-semibold text-slate-900">Avis par source</h2>
        @if($parSource->isEmpty())
            <p class="text-sm text-slate-400">Aucun avis pour le moment.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead><tr><th class="th !px-2">Source</th><th class="th !px-2">Avis</th><th class="th !px-2">Moyenne</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($parSource as $s)
                            <tr>
                                <td class="td !px-2 font-medium">{{ $sources[$s->source] ?? ucfirst($s->source) }}</td>
                                <td class="td !px-2">{{ $s->total }}</td>
                                <td class="td !px-2">{{ $notes[(int) round($s->moyenne)]['emoji'] }} {{ number_format($s->moyenne, 1) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- Liste --}}
<div class="card overflow-hidden">
    <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-slate-100 p-4">
        <div class="min-w-[180px] flex-1">
            <label class="label">Recherche</label>
            <input type="text" name="q" value="{{ request('q') }}" class="input" placeholder="Nom, livre, commentaire…">
        </div>
        <div>
            <label class="label">Note</label>
            <select name="note" class="input">
                <option value="">Toutes</option>
                @foreach($notes as $v => $n)
                    <option value="{{ $v }}" @selected((string) request('note') === (string) $v)>{{ $n['emoji'] }} {{ $n['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Source</label>
            <select name="source" class="input">
                <option value="">Toutes</option>
                @foreach($parSource as $s)
                    <option value="{{ $s->source }}" @selected(request('source') === $s->source)>{{ $sources[$s->source] ?? ucfirst($s->source) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn-primary">Filtrer</button>
        @if(request()->hasAny(['q','note','source']))
            <a href="{{ route('satisfactions.index') }}" class="btn-outline">Réinitialiser</a>
        @endif
    </form>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100">
            <thead class="bg-slate-50">
                <tr>
                    <th class="th">Date</th>
                    <th class="th">Client</th>
                    <th class="th">Livre</th>
                    <th class="th">Note</th>
                    <th class="th">Commentaire</th>
                    <th class="th">Source</th>
                    <th class="th"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($liste as $s)
                    <tr class="align-top">
                        <td class="td whitespace-nowrap text-slate-500">{{ $s->created_at->format('d/m/Y H:i') }}</td>
                        <td class="td font-medium">{{ $s->nom }}</td>
                        <td class="td">{{ $s->livre ?: '—' }}</td>
                        <td class="td whitespace-nowrap text-xl" title="{{ $notes[$s->note]['label'] }}">{{ $notes[$s->note]['emoji'] }}</td>
                        <td class="td max-w-xs whitespace-pre-line">{{ $s->commentaire ?: '—' }}</td>
                        <td class="td"><span class="chip">{{ $sources[$s->source] ?? ucfirst($s->source) }}</span></td>
                        <td class="td">
                            <form method="POST" action="{{ route('satisfactions.destroy', $s) }}" onsubmit="return confirm('Supprimer cet avis ?')">
                                @csrf @method('DELETE')
                                <button class="text-xs font-semibold text-red-600 hover:underline">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="td py-10 text-center text-slate-400">Aucun avis trouvé.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($liste->hasPages())
        <div class="border-t border-slate-100 p-4">{{ $liste->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const url = btn.dataset.copy;
            try {
                await navigator.clipboard.writeText(url);
            } catch (e) {
                const i = btn.previousElementSibling; i.select(); document.execCommand('copy');
            }
            const old = btn.textContent;
            btn.textContent = 'Copié ✓';
            setTimeout(() => btn.textContent = old, 1500);
        });
    });
</script>
@endpush