@extends('layouts.crm')
@section('title', 'Meilleurs clients')

@section('content')
@php
    $periodes = ['tout' => 'Tout', '30' => '30 jours', '90' => '90 jours', '365' => '12 mois'];
    $panierMoyen = $resume->commandes > 0 ? (int) round($resume->total / $resume->commandes) : 0;
@endphp

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Meilleurs clients</h1>
        <p class="mt-1 text-sm text-slate-500">Classement des clients qui commandent le plus (commandes annulées exclues).</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('commandes.index') }}" class="btn-outline">Toutes les commandes</a>
        <a href="{{ route('commandes.create') }}" class="btn-primary">Nouvelle commande</a>
    </div>
</div>

{{-- Filtres --}}
<div class="mb-5 flex flex-wrap items-center gap-3">
    <div class="seg">
        @foreach($periodes as $cle => $label)
            <a href="{{ route('commandes.classement', ['periode' => $cle, 'tri' => $tri]) }}"
               class="seg-btn {{ $periode === (string) $cle ? 'is-active' : '' }}">{{ $label }}</a>
        @endforeach
    </div>
    <div class="seg">
        <a href="{{ route('commandes.classement', ['periode' => $periode, 'tri' => 'montant']) }}"
           class="seg-btn {{ $tri === 'montant' ? 'is-active' : '' }}">Par montant</a>
        <a href="{{ route('commandes.classement', ['periode' => $periode, 'tri' => 'commandes']) }}"
           class="seg-btn {{ $tri === 'commandes' ? 'is-active' : '' }}">Par nombre de commandes</a>
    </div>
</div>

{{-- Résumé --}}
<div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="card p-5">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Clients ayant commandé</div>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($resume->clients, 0, ',', ' ') }}</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Commandes</div>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($resume->commandes, 0, ',', ' ') }}</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Chiffre d'affaires</div>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($resume->total, 0, ',', ' ') }} <span class="text-base font-semibold text-slate-400">Ar</span></div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Panier moyen</div>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($panierMoyen, 0, ',', ' ') }} <span class="text-base font-semibold text-slate-400">Ar</span></div>
    </div>
</div>

<div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
    {{-- Classement --}}
    <div class="card overflow-hidden xl:col-span-2">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100">
                <thead class="bg-slate-50/70">
                    <tr>
                        <th class="th">Rang</th>
                        <th class="th">Client</th>
                        <th class="th text-right">Commandes</th>
                        <th class="th text-right">Montant total</th>
                        <th class="th text-right">Panier moyen</th>
                        <th class="th">Dernière commande</th>
                        <th class="th"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($clients as $i => $ligne)
                        @php
                            $rang = $clients->firstItem() + $i;
                            $contact = $ligne->contact;
                            $medaille = [1 => 'bg-amber-100 text-amber-700', 2 => 'bg-slate-200 text-slate-700', 3 => 'bg-orange-100 text-orange-700'][$rang] ?? 'bg-slate-100 text-slate-500';
                            $niveau = $ligne->nb_commandes >= 5 ? ['VIP', 'bg-violet-50 text-violet-700 ring-violet-600/20']
                                : ($ligne->nb_commandes >= 2 ? ['Fidèle', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20']
                                : ['Nouveau', 'bg-slate-100 text-slate-600 ring-slate-500/20']);
                        @endphp
                        <tr class="hover:bg-slate-50/60">
                            <td class="td">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold {{ $medaille }}">{{ $rang }}</span>
                            </td>
                            <td class="td">
                                <div class="flex items-center gap-2">
                                    <a href="{{ $contact ? route('contacts.edit', $contact) : '#' }}" class="font-medium text-slate-900 hover:text-blue-600">{{ $contact->name ?? '—' }}</a>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $niveau[1] }}">{{ $niveau[0] }}</span>
                                </div>
                                <div class="text-xs text-slate-500">{{ $contact->phone ?? ($contact->email ?? '') }}</div>
                            </td>
                            <td class="td text-right font-semibold text-slate-900">{{ number_format($ligne->nb_commandes, 0, ',', ' ') }}</td>
                            <td class="td whitespace-nowrap text-right font-semibold text-slate-900">{{ number_format($ligne->total, 0, ',', ' ') }} Ar</td>
                            <td class="td whitespace-nowrap text-right">{{ number_format($ligne->nb_commandes > 0 ? round($ligne->total / $ligne->nb_commandes) : 0, 0, ',', ' ') }} Ar</td>
                            <td class="td whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($ligne->derniere)->format('d/m/Y') }}</td>
                            <td class="td whitespace-nowrap text-right">
                                @if($contact)
                                    <a href="{{ route('commandes.create', ['contact_id' => $contact->id]) }}" class="text-sm font-medium text-emerald-600 hover:text-emerald-800">Nouvelle commande</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-500">Aucune commande sur cette période.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Livres les plus commandés --}}
    <div class="card p-6">
        <h2 class="text-base font-bold text-slate-900">Livres les plus commandés</h2>
        @php
            $maxLivre = max(1, ...array_column($livres, 'quantite') ?: [1]);
        @endphp
        <ul class="mt-4 space-y-3">
            @forelse($livres as $i => $livre)
                <li>
                    <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                        <span class="truncate font-medium text-slate-900">{{ $i + 1 }}. {{ $livre['titre'] }}</span>
                        <span class="shrink-0 font-semibold text-slate-700">{{ number_format($livre['quantite'], 0, ',', ' ') }} ×</span>
                    </div>
                    <div class="h-1.5 w-full rounded-full bg-slate-100">
                        <div class="h-1.5 rounded-full bg-blue-600" style="width: {{ round($livre['quantite'] / $maxLivre * 100) }}%"></div>
                    </div>
                </li>
            @empty
                <li class="text-sm text-slate-500">Aucun livre commandé sur cette période.</li>
            @endforelse
        </ul>
    </div>
</div>

<div class="mt-5">
    {{ $clients->links() }}
</div>
@endsection