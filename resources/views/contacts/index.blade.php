@extends('layouts.crm')
@section('title', 'Contacts')

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Contacts</h1>
        <p class="text-sm text-slate-500">{{ $contacts->total() }} contact(s) au total</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('contacts.import.form') }}" class="btn-outline">Importer CSV</a>
        <a href="{{ route('commandes.create', ['contact_id' => $contact->id]) }}" class="text-sm font-medium text-emerald-600 hover:text-emerald-800">Commande</a>
        <a href="{{ route('contacts.create') }}" class="btn-primary">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            Nouveau contact
        </a>
    </div>
</div>

<form method="GET" class="card mb-5 grid grid-cols-1 gap-3 p-4 md:grid-cols-12">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Rechercher nom, téléphone, email…" class="input md:col-span-5">
    <select name="status" class="input md:col-span-3">
        <option value="">Tous les statuts</option>
        @foreach(\App\Models\Contact::STATUSES as $key => $label)
            <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
        @endforeach
    </select>
    <select name="source" class="input md:col-span-3">
        <option value="">Toutes les sources</option>
        @foreach(\App\Models\Contact::SOURCES as $key => $label)
            <option value="{{ $key }}" @selected(request('source') === $key)>{{ $label }}</option>
        @endforeach
    </select>
    <button class="btn-dark md:col-span-1">Filtrer</button>
</form>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
            <tr>
                <th class="th">Contact</th>
                <th class="th">Téléphone</th>
                <th class="th">Email</th>
                <th class="th">Source</th>
                <th class="th">Statut</th>
                <th class="th"></th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($contacts as $contact)
                <tr class="transition hover:bg-slate-50">
                    <td class="td">
                        <div class="flex items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-700">
                                {{ strtoupper(mb_substr($contact->name, 0, 1)) }}
                            </span>
                            <div>
                                <div class="font-semibold text-slate-900">
                                    {{ $contact->name }}
                                    @if($contact->unsubscribed)
                                        <span class="ml-1 rounded bg-slate-900 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white">Stop</span>
                                    @endif
                                </div>
                                @if($contact->city)
                                    <div class="text-xs text-slate-500">{{ $contact->city }}</div>
                                @endif
                                @if($contact->facebook_psid)
                                    @php($fbActive = $contact->last_fb_interaction_at && $contact->last_fb_interaction_at->gt(now()->subHours(24)))
                                    <div class="mt-1 flex items-center gap-1.5 text-xs">
                                        <span class="rounded bg-blue-100 px-1.5 py-0.5 font-semibold text-blue-700">Messenger</span>
                                        @if($fbActive)
                                            <span class="font-medium text-emerald-600">● joignable</span>
                                        @elseif($contact->last_fb_interaction_at)
                                            <span class="text-slate-400">vu le {{ $contact->last_fb_interaction_at->format('d/m H:i') }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="td whitespace-nowrap">{{ $contact->phone ?: '—' }}</td>
                    <td class="td">{{ $contact->email ?: '—' }}</td>
                    <td class="td">{{ \App\Models\Contact::SOURCES[$contact->source] ?? $contact->source }}</td>
                    <td class="td">@include('contacts._status', ['status' => $contact->status])</td>
                    <td class="td whitespace-nowrap text-right">
                        <a href="{{ route('contacts.edit', $contact) }}" class="font-medium text-blue-600 hover:text-blue-800">Modifier</a>
                        <form method="POST" action="{{ route('contacts.destroy', $contact) }}" class="ml-3 inline"
                              onsubmit="return confirm('Supprimer ce contact ?')">
                            @csrf @method('DELETE')
                            <button class="text-slate-400 transition hover:text-red-600">Supprimer</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-14 text-center">
                        <p class="font-medium text-slate-700">Aucun contact pour le moment</p>
                        <p class="mt-1 text-sm text-slate-500">Ajoutez-en un ou importez un fichier CSV.</p>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-5">{{ $contacts->links() }}</div>
@endsection
