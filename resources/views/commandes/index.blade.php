@extends('layouts.crm')
@section('title', 'Commandes')

@section('content')
@php
    $base = request()->except('statut', 'page');
@endphp

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Suivi des commandes</h1>
        <p class="mt-1 text-sm text-slate-500">{{ number_format($commandes->total(), 0, ',', ' ') }} commande(s)</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('commandes.import.form') }}" class="btn-outline">Importer un fichier</a>
        <a href="{{ route('commandes.classement') }}" class="btn-outline">Meilleurs clients</a>
        <a href="{{ route('commandes.create') }}" class="btn-primary">Nouvelle commande</a>
    </div>
</div>

{{-- Statuts --}}
<div class="mb-4 flex flex-wrap gap-2">
    <a href="{{ route('commandes.index', $base) }}" class="chip {{ request('statut') ? 'is-off' : '' }}">
        Toutes <span class="font-semibold text-slate-900">{{ array_sum($comptes) }}</span>
    </a>
    @foreach(\App\Models\Commande::STATUTS as $key => $label)
        <a href="{{ route('commandes.index', $base + ['statut' => $key]) }}"
           class="chip {{ request('statut') && request('statut') !== $key ? 'is-off' : '' }}">
            <span class="h-2 w-2 rounded-full" style="background: {{ \App\Models\Commande::COULEURS[$key] }}"></span>
            {{ $label }}
            <span class="font-semibold text-slate-900">{{ $comptes[$key] ?? 0 }}</span>
        </a>
    @endforeach
</div>

{{-- Filtres --}}
<form method="GET" action="{{ route('commandes.index') }}" class="card mb-5 flex flex-wrap items-end gap-3 p-4">
    @if(request('statut'))
        <input type="hidden" name="statut" value="{{ request('statut') }}">
    @endif
    <div class="min-w-[14rem] flex-1">
        <label class="label" for="q">Recherche</label>
        <input type="text" id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nom, téléphone, livre, responsable…">
    </div>
    <div>
        <label class="label" for="du">Du</label>
        <input type="date" id="du" name="du" value="{{ request('du') }}" class="input">
    </div>
    <div>
        <label class="label" for="au">Au</label>
        <input type="date" id="au" name="au" value="{{ request('au') }}" class="input">
    </div>
    <button type="submit" class="btn-dark">Filtrer</button>
    @if(request()->hasAny(['q', 'statut', 'du', 'au']))
        <a href="{{ route('commandes.index') }}" class="btn-outline">Réinitialiser</a>
    @endif
</form>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100">
            <thead class="bg-slate-50/70">
                <tr>
                    <th class="th">Horodateur</th>
                    <th class="th">Client</th>
                    <th class="th">Livre(s) commandé(s)</th>
                    <th class="th">Paiement</th>
                    <th class="th">Livraison</th>
                    <th class="th">Responsable</th>
                    <th class="th text-right">Montant total</th>
                    <th class="th">Statut</th>
                    <th class="th"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($commandes as $commande)
                    <tr class="align-top hover:bg-slate-50/60">
                        <td class="td whitespace-nowrap">{{ $commande->date_commande?->format('d/m/Y H:i') }}</td>
                        <td class="td">
                            <div class="font-medium text-slate-900">{{ $commande->contact->name ?? '—' }}</div>
                            <div class="text-xs text-slate-500">{{ $commande->contact->phone ?? ($commande->contact->email ?? '') }}</div>
                            @if($commande->adresse_livraison)
                                <div class="mt-0.5 max-w-[14rem] truncate text-xs text-slate-400" title="{{ $commande->adresse_livraison }}">{{ $commande->adresse_livraison }}</div>
                            @endif
                        </td>
                        <td class="td">
                            <div class="max-w-xs whitespace-pre-line">{{ \Illuminate\Support\Str::limit((string) $commande->livres, 140) }}</div>
                        </td>
                        <td class="td whitespace-nowrap">{{ $commande->mode_paiement ?: '—' }}</td>
                        <td class="td whitespace-nowrap">
                            @if($commande->date_livraison)
                                {{ $commande->date_livraison->locale('fr')->translatedFormat('d M Y') }}
                                @if($commande->heure_livraison)
                                    <span class="text-slate-500">· {{ $commande->heure_livraison }}</span>
                                @endif
                                @if($commande->precision_heure)
                                    <div class="text-xs text-slate-400">{{ $commande->precision_heure }}</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="td whitespace-nowrap">{{ $commande->responsable ?: '—' }}</td>
                        <td class="td whitespace-nowrap text-right font-semibold text-slate-900">{{ number_format($commande->montant_total, 0, ',', ' ') }} Ar</td>
                        <td class="td">
                            <form method="POST" action="{{ route('commandes.statut', $commande) }}">
                                @csrf
                                <select name="statut" class="input !w-auto !py-1.5 text-xs" onchange="this.form.submit()" aria-label="Statut de la commande">
                                    @foreach(\App\Models\Commande::STATUTS as $key => $label)
                                        <option value="{{ $key }}" @selected($commande->statut === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                        <td class="td whitespace-nowrap text-right">
                            <a href="{{ route('commandes.create', ['depuis' => $commande->id]) }}" class="text-sm font-medium text-emerald-600 hover:text-emerald-800">Recommander</a>
                            <a href="{{ route('commandes.edit', $commande) }}" class="ml-3 text-sm font-medium text-blue-600 hover:text-blue-800">Modifier</a>
                            <form method="POST" action="{{ route('commandes.destroy', $commande) }}" class="ml-3 inline" onsubmit="return confirm('Supprimer cette commande ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-5 py-10 text-center text-sm text-slate-500">Aucune commande.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-5">
    {{ $commandes->links() }}
</div>
@endsection