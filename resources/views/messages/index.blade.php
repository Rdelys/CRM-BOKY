@extends('layouts.crm')
@use('App\Services\MapiService')
@section('title', 'Messages')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Messages SMS</h1>
    <p class="mt-1 text-sm text-slate-500">Historique des SMS envoyés depuis le CRM via Mapi.</p>
</div>

{{-- Statistiques --}}
<div class="mb-5 grid grid-cols-2 gap-4 xl:grid-cols-5">
    <div class="card p-5">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Envoyés</div>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ number_format((int) $stats->envoyes, 0, ',', ' ') }}</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Échecs</div>
        <div class="mt-2 text-3xl font-bold {{ (int) $stats->echecs > 0 ? 'text-red-600' : 'text-slate-900' }}">{{ number_format((int) $stats->echecs, 0, ',', ' ') }}</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Aujourd'hui</div>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ $aujourdhui }}</div>
    </div>
    <div class="card p-5">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">SMS consommés</div>
        <div class="mt-2 text-3xl font-bold text-slate-900">{{ number_format((int) $stats->unites, 0, ',', ' ') }}</div>
    </div>
    <div class="card col-span-2 p-5 xl:col-span-1">
        <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Solde Mapi</div>
        <div class="mt-2 text-3xl font-bold text-slate-900">
            {{ $solde !== null ? number_format($solde, 0, ',', ' ') : '—' }}
            <span class="text-base font-semibold text-slate-400">unités</span>
        </div>
    </div>
</div>

{{-- Filtres --}}
<form method="GET" class="card mb-5 grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-12">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Nom, numéro, message…" class="input lg:col-span-3">
    <select name="statut" class="input lg:col-span-2">
        <option value="">Tous les statuts</option>
        @foreach(\App\Models\SmsMessage::STATUTS as $k => $label)
            <option value="{{ $k }}" @selected(request('statut') === $k)>{{ $label }}</option>
        @endforeach
    </select>
    <select name="origine" class="input lg:col-span-2">
        <option value="">Toutes les origines</option>
        @foreach(\App\Models\SmsMessage::ORIGINES as $k => $label)
            <option value="{{ $k }}" @selected(request('origine') === $k)>{{ $label }}</option>
        @endforeach
    </select>
    <input type="date" name="du" value="{{ request('du') }}" class="input lg:col-span-2" title="Du">
    <input type="date" name="au" value="{{ request('au') }}" class="input lg:col-span-2" title="Au">
    <button class="btn-dark lg:col-span-1">Filtrer</button>
    @if(request()->hasAny(['q', 'statut', 'origine', 'du', 'au']))
        <a href="{{ route('messages.index') }}" class="text-xs font-medium text-blue-600 hover:text-blue-800 sm:col-span-2 lg:col-span-12">Réinitialiser les filtres</a>
    @endif
</form>

{{-- Liste --}}
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100">
            <thead class="bg-slate-50/70">
                <tr>
                    <th class="th">Date</th>
                    <th class="th">Destinataire</th>
                    <th class="th">Message</th>
                    <th class="th">Origine</th>
                    <th class="th text-right">SMS</th>
                    <th class="th">Statut</th>
                    <th class="th">Envoyé par</th>
                    <th class="th"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($messages as $m)
                    <tr class="align-top hover:bg-slate-50/60">
                        <td class="td whitespace-nowrap text-slate-500">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                        <td class="td whitespace-nowrap">
                            @if($m->contact)
                                <a href="{{ route('contacts.edit', $m->contact) }}" class="font-medium text-slate-900 hover:text-blue-600">{{ $m->nom }}</a>
                            @else
                                <span class="font-medium text-slate-900">{{ $m->nom ?: '—' }}</span>
                            @endif
                            <div class="text-xs text-slate-500">{{ MapiService::format($m->telephone) }}</div>
                        </td>
                        <td class="td max-w-md whitespace-pre-line break-words">{{ $m->message }}</td>
                        <td class="td whitespace-nowrap"><span class="chip">{{ \App\Models\SmsMessage::ORIGINES[$m->origine] ?? $m->origine }}</span></td>
                        <td class="td text-right">{{ $m->nb_sms }}</td>
                        <td class="td">
                            @if($m->statut === 'sent')
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Envoyé</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700 ring-1 ring-inset ring-red-600/20">Échec</span>
                                @if($m->erreur)<div class="mt-1 max-w-[200px] text-xs text-red-600">{{ $m->erreur }}</div>@endif
                            @endif
                        </td>
                        <td class="td whitespace-nowrap text-slate-500">{{ $m->user->name ?? '—' }}</td>
                        <td class="td whitespace-nowrap text-right">
                            @if($m->statut === 'failed')
                                <form method="POST" action="{{ route('messages.resend', $m) }}" onsubmit="return confirm('Renvoyer ce SMS ?')">
                                    @csrf
                                    <button class="text-sm font-medium text-blue-600 hover:text-blue-800">Renvoyer</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-12 text-center text-sm text-slate-500">Aucun message trouvé.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-5">{{ $messages->links() }}</div>
@endsection