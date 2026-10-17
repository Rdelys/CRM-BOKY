@extends('layouts.crm')
@section('title', $campaign->name)

@section('content')
<div class="mb-6">
    <a href="{{ route('campaigns.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">← Retour aux campagnes</a>
    <div class="mt-1 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-bold text-slate-900">{{ $campaign->name }}</h1>
        @include('campaigns._status', ['status' => $campaign->status])
    </div>
</div>

<div class="mb-5 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <div class="card p-4">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Canal</div>
        <div class="mt-1 text-lg font-bold uppercase text-blue-600">{{ $campaign->channel }}</div>
    </div>
    <div class="card p-4">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Destinataires</div>
        <div class="mt-1 text-lg font-bold text-slate-900">{{ $recipientCount }}</div>
    </div>
    <div class="card p-4">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Envoyés</div>
        <div class="mt-1 text-lg font-bold text-slate-900">{{ $campaign->sent_count }}</div>
    </div>
    <div class="card p-4">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Échecs</div>
        <div class="mt-1 text-lg font-bold text-slate-900">{{ $campaign->failed_count }}</div>
    </div>
</div>

<div class="card mb-5 p-6">
    <div class="mb-4 flex flex-wrap gap-x-6 gap-y-1 text-sm text-slate-600">
        <span><span class="font-medium text-slate-800">Ciblage :</span>
            {{ $campaign->segment_status ? \App\Models\Contact::STATUSES[$campaign->segment_status] : 'Tous les statuts' }}
            ·
            {{ $campaign->segment_source ? \App\Models\Contact::SOURCES[$campaign->segment_source] : 'toutes les sources' }}
        </span>
        @if($campaign->channel === 'email')
            <span><span class="font-medium text-slate-800">Objet :</span> {{ $campaign->subject }}</span>
        @endif
    </div>

    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm leading-relaxed text-slate-800 whitespace-pre-line">{{ $campaign->message }}</div>

    @if($campaign->status === 'draft')
        <form method="POST" action="{{ route('campaigns.send', $campaign) }}" class="mt-5"
              onsubmit="return confirm('Envoyer à {{ $recipientCount }} destinataire(s) ? Cette action est définitive.')">
            @csrf
            <button class="btn-primary">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                Envoyer à {{ $recipientCount }} contact(s)
            </button>
        </form>
    @endif
</div>

@if($logs->isNotEmpty())
    <h2 class="mb-3 text-lg font-bold text-slate-900">Derniers envois</h2>
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                <tr><th class="th">Contact</th><th class="th">Statut</th><th class="th">Erreur</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @foreach($logs as $log)
                    <tr>
                        <td class="td font-medium text-slate-900">{{ $log->contact->name ?? '—' }}</td>
                        <td class="td">
                            @if($log->status === 'sent')
                                <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700">Envoyé</span>
                            @else
                                <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700">Échec</span>
                            @endif
                        </td>
                        <td class="td text-slate-500">{{ $log->error }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@if($campaign->status === 'draft')
    <form method="POST" action="{{ route('campaigns.destroy', $campaign) }}" class="mt-5"
          onsubmit="return confirm('Supprimer ce brouillon ?')">
        @csrf @method('DELETE')
        <button class="text-sm text-slate-400 transition hover:text-red-600">Supprimer ce brouillon</button>
    </form>
@endif
@endsection
