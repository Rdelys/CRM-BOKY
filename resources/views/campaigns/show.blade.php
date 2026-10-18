@extends('layouts.crm')
@section('title', $campaign->name)

@section('content')
@php
    $isOpen = in_array($campaign->status, ['draft', 'scheduled'], true);
    $isFinished = in_array($campaign->status, ['sent', 'cancelled'], true);
    $done = $stats['sent'] + $stats['failed'] + $stats['skipped'] + $stats['cancelled'];
    $percent = $total > 0 ? round($done / $total * 100) : 0;
    $perMinute = max(1, (int) $campaign->per_minute);
    $minutes = (int) ceil($recipientCount / $perMinute);
    $logMap = [
        'sent'      => ['Envoyé',     'bg-blue-100 text-blue-700'],
        'failed'    => ['Échec',      'bg-red-100 text-red-700'],
        'pending'   => ['En attente', 'bg-slate-200 text-slate-700'],
        'skipped'   => ['Ignoré',     'bg-amber-100 text-amber-800'],
        'cancelled' => ['Annulé',     'bg-white text-slate-500 ring-1 ring-inset ring-slate-300'],
    ];
@endphp

<div class="mb-6">
    <a href="{{ route('campaigns.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">← Retour aux campagnes</a>
    <div class="mt-1 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-bold text-slate-900">{{ $campaign->name }}</h1>
        @include('campaigns._status', ['status' => $campaign->status])
    </div>
</div>

<div class="mb-5 grid grid-cols-2 gap-4 lg:grid-cols-5">
    <div class="card p-4">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Canal</div>
        <div class="mt-1 text-lg font-bold uppercase text-blue-600">{{ $campaign->channel }}</div>
    </div>
    <div class="card p-4">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Destinataires</div>
        <div class="mt-1 text-lg font-bold text-slate-900">{{ $total }}</div>
    </div>
    <div class="card p-4">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Envoyés</div>
        <div class="mt-1 text-lg font-bold text-slate-900">{{ $stats['sent'] }}</div>
    </div>
    <div class="card p-4">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">En attente</div>
        <div class="mt-1 text-lg font-bold text-slate-900">{{ $stats['pending'] }}</div>
    </div>
    <div class="card p-4">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Échecs</div>
        <div class="mt-1 text-lg font-bold text-slate-900">{{ $stats['failed'] }}</div>
    </div>
</div>

@unless($isOpen)
    <div class="card mb-5 p-4">
        <div class="mb-2 flex justify-between text-sm">
            <span class="font-medium text-slate-700">Progression</span>
            <span class="text-slate-500">{{ $done }} / {{ $total }} ({{ $percent }} %)</span>
        </div>
        <div class="h-2 rounded-full bg-slate-100">
            <div class="h-2 rounded-full bg-blue-600" style="width: {{ $percent }}%"></div>
        </div>
    </div>
@endunless

<div class="card mb-5 p-6">
    <div class="mb-4 flex flex-wrap gap-x-6 gap-y-1 text-sm text-slate-600">
        <span><span class="font-medium text-slate-800">Ciblage :</span>
            {{ $campaign->segment_status ? \App\Models\Contact::STATUSES[$campaign->segment_status] : 'Tous les statuts' }}
            ·
            {{ $campaign->segment_source ? \App\Models\Contact::SOURCES[$campaign->segment_source] : 'toutes les sources' }}
        </span>
        <span><span class="font-medium text-slate-800">Cadence :</span> {{ $perMinute }} / minute</span>
        @if($campaign->channel === 'email')
            <span><span class="font-medium text-slate-800">Objet :</span> {{ $campaign->subject }}</span>
        @endif
    </div>

    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm leading-relaxed text-slate-800 whitespace-pre-line">{{ $campaign->message }}</div>

    @if($campaign->status === 'scheduled')
        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <span>
                Programmée le <strong>{{ $campaign->scheduled_at->format('d/m/Y à H:i') }}</strong>
                pour {{ $recipientCount }} destinataire(s).
            </span>
            <form method="POST" action="{{ route('campaigns.unschedule', $campaign) }}">
                @csrf
                <button class="font-medium text-amber-900 underline hover:no-underline">Annuler la programmation</button>
            </form>
        </div>
    @endif

    {{-- Email de test : avant l'envoi, et aussi après (pour vérifier le SMTP avant de renvoyer) --}}
    @if($campaign->channel === 'email' && ($isOpen || $isFinished))
        <form method="POST" action="{{ route('campaigns.test', $campaign) }}" class="mt-5 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-5">
            @csrf
            <div class="min-w-[16rem] flex-1">
                <label class="label">Envoyer un email de test à</label>
                <input type="email" name="test_email" value="{{ old('test_email', auth()->user()->email) }}" class="input" required>
            </div>
            <button class="btn-outline">Envoyer le test</button>
        </form>
    @endif

    @if($isOpen)
        <div class="mt-5 grid grid-cols-1 gap-4 border-t border-slate-100 pt-5 md:grid-cols-2">
            <form method="POST" action="{{ route('campaigns.schedule', $campaign) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <div class="min-w-[14rem] flex-1">
                    <label class="label">Programmer l'envoi</label>
                    <input type="datetime-local" name="scheduled_at"
                           value="{{ old('scheduled_at', $campaign->scheduled_at?->format('Y-m-d\TH:i')) }}"
                           min="{{ now()->format('Y-m-d\TH:i') }}" class="input" required>
                </div>
                <button class="btn-dark">Programmer</button>
            </form>

            <form method="POST" action="{{ route('campaigns.send', $campaign) }}" class="flex flex-wrap items-end"
                  onsubmit="return confirm('Envoyer maintenant à {{ $recipientCount }} destinataire(s) ? Durée estimée : {{ $minutes }} min.')">
                @csrf
                <button class="btn-primary">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                    Envoyer maintenant à {{ $recipientCount }} contact(s)
                </button>
            </form>
        </div>
        <p class="mt-3 text-xs text-slate-500">Durée estimée de l'envoi : environ {{ $minutes }} min à {{ $perMinute }} message(s) par minute.</p>
    @endif

    {{-- Renvoi d'une campagne terminée --}}
    @if($isFinished)
        <div class="mt-5 border-t border-slate-100 pt-5">
            <h2 class="text-base font-bold text-slate-900">Renvoyer cette campagne</h2>
            <p class="mb-4 mt-1 text-sm text-slate-500">
                Le renvoi utilise la même cadence ({{ $perMinute }} / minute). Vérifiez d'abord avec un email de test que l'envoi fonctionne.
            </p>

            <div class="flex flex-wrap gap-3">
                @if($retryCount > 0)
                    <form method="POST" action="{{ route('campaigns.relaunch', $campaign) }}"
                          onsubmit="return confirm('Renvoyer à {{ $retryCount }} contact(s) qui n\'ont pas reçu le message ?')">
                        @csrf
                        <input type="hidden" name="mode" value="failed">
                        <button class="btn-primary">Renvoyer aux {{ $retryCount }} contact(s) sans envoi réussi</button>
                    </form>
                @endif

                <form method="POST" action="{{ route('campaigns.relaunch', $campaign) }}"
                      onsubmit="return confirm('Renvoyer la campagne à TOUS les contacts ciblés, y compris ceux qui l\'ont déjà reçue ?')">
                    @csrf
                    <input type="hidden" name="mode" value="all">
                    <button class="btn-outline">Renvoyer à tous</button>
                </form>
            </div>

            @if($retryCount === 0)
                <p class="mt-3 text-xs text-slate-500">Aucun contact en échec : tous les envois ont réussi.</p>
            @endif
        </div>
    @endif

    @if($campaign->status === 'sending')
        <form method="POST" action="{{ route('campaigns.cancel', $campaign) }}" class="mt-5 border-t border-slate-100 pt-5"
              onsubmit="return confirm('Arrêter l\'envoi ? Les messages en attente ne seront pas envoyés.')">
            @csrf
            <button class="btn-outline">Annuler l'envoi en cours</button>
        </form>
    @endif
</div>

@if($logs->isNotEmpty())
    <h2 class="mb-3 text-lg font-bold text-slate-900">Derniers envois</h2>
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                <tr><th class="th">Contact</th><th class="th">Statut</th><th class="th">Envoyé à</th><th class="th">Erreur</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @foreach($logs as $log)
                    @php([$logLabel, $logClasses] = $logMap[$log->status] ?? [$log->status, 'bg-slate-100 text-slate-700'])
                    <tr>
                        <td class="td font-medium text-slate-900">{{ $log->contact->name ?? '—' }}</td>
                        <td class="td">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $logClasses }}">{{ $logLabel }}</span>
                        </td>
                        <td class="td whitespace-nowrap text-slate-500">{{ $log->sent_at?->format('d/m/Y H:i:s') ?? '—' }}</td>
                        <td class="td text-slate-500">{{ $log->error }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@if($isOpen)
    <form method="POST" action="{{ route('campaigns.destroy', $campaign) }}" class="mt-5"
          onsubmit="return confirm('Supprimer cette campagne ?')">
        @csrf @method('DELETE')
        <button class="text-sm text-slate-400 transition hover:text-red-600">Supprimer cette campagne</button>
    </form>
@endif

@if($campaign->status === 'sending')
    <script>setTimeout(function () { window.location.reload(); }, 10000);</script>
@endif
@endsection