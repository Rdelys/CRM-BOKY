@extends('layouts.crm')
@section('title', 'Campagnes')

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Campagnes</h1>
        <p class="text-sm text-slate-500">Vos envois de SMS et d'emails d'offres</p>
    </div>
    <a href="{{ route('campaigns.create') }}" class="btn-primary">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        Nouvelle campagne
    </a>
</div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
            <tr>
                <th class="th">Campagne</th>
                <th class="th">Canal</th>
                <th class="th">Statut</th>
                <th class="th">Envoyés</th>
                <th class="th">Échecs</th>
                <th class="th">Date</th>
                <th class="th"></th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($campaigns as $campaign)
                <tr class="transition hover:bg-slate-50">
                    <td class="td font-semibold text-slate-900">{{ $campaign->name }}</td>
                    <td class="td">
                        <span class="rounded-md bg-blue-50 px-2 py-0.5 text-xs font-bold uppercase text-blue-700">{{ $campaign->channel }}</span>
                    </td>
                    <td class="td">@include('campaigns._status', ['status' => $campaign->status])</td>
                    <td class="td">{{ $campaign->sent_count }}</td>
                    <td class="td">{{ $campaign->failed_count }}</td>
                    <td class="td whitespace-nowrap">{{ ($campaign->sent_at ?? $campaign->created_at)->format('d/m/Y H:i') }}</td>
                    <td class="td text-right">
                        <a href="{{ route('campaigns.show', $campaign) }}" class="font-medium text-blue-600 hover:text-blue-800">Ouvrir</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-5 py-14 text-center">
                        <p class="font-medium text-slate-700">Aucune campagne pour le moment</p>
                        <p class="mt-1 text-sm text-slate-500">Créez votre première campagne SMS ou email.</p>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-5">{{ $campaigns->links() }}</div>
@endsection
