@extends('layouts.crm')
@section('title', 'Tableau de bord')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Tableau de bord</h1>
    <p class="text-sm text-slate-500">Vue d'ensemble de votre activité</p>
</div>

<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <div class="rounded-xl bg-slate-950 p-5 text-white shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Contacts</div>
        <div class="mt-2 text-3xl font-bold">{{ $stats['contacts'] }}</div>
    </div>
    <div class="rounded-xl bg-blue-600 p-5 text-white shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-wider text-blue-100">Prospects</div>
        <div class="mt-2 text-3xl font-bold">{{ $stats['prospects'] }}</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Clients</div>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ $stats['clients'] }}</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Campagnes envoyées</div>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ $stats['campaigns'] }}</div>
    </div>
</div>

<div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
    {{-- Sources --}}
    <div class="card p-6">
        <h2 class="mb-4 text-base font-bold text-slate-900">Contacts par source</h2>
        @php($total = max(1, $bySource->sum()))
        @forelse($bySource as $source => $count)
            <div class="mb-3">
                <div class="mb-1 flex justify-between text-sm">
                    <span class="font-medium text-slate-700">{{ \App\Models\Contact::SOURCES[$source] ?? $source }}</span>
                    <span class="text-slate-500">{{ $count }}</span>
                </div>
                <div class="h-2 rounded-full bg-slate-100">
                    <div class="h-2 rounded-full bg-blue-600" style="width: {{ round($count / $total * 100) }}%"></div>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500">Aucune donnée pour le moment.</p>
        @endforelse
    </div>

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
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-700">
                            {{ strtoupper(mb_substr($contact->name, 0, 1)) }}
                        </span>
                        <div>
                            <div class="text-sm font-medium text-slate-900">{{ $contact->name }}</div>
                            <div class="text-xs text-slate-500">{{ $contact->phone ?: $contact->email }}</div>
                        </div>
                    </div>
                    @include('contacts._status', ['status' => $contact->status])
                </li>
            @empty
                <li class="py-2 text-sm text-slate-500">Aucun contact.</li>
            @endforelse
        </ul>
    </div>
</div>

{{-- Dernières campagnes --}}
<div class="card mt-5 p-6">
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
@endsection