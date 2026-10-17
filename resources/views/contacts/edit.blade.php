@extends('layouts.crm')
@section('title', 'Modifier le contact')

@section('content')
<div class="mb-6">
    <a href="{{ route('contacts.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">← Retour aux contacts</a>
    <h1 class="mt-1 text-2xl font-bold text-slate-900">Modifier : {{ $contact->name }}</h1>
</div>
@if($contact->website_user_id)
    <div class="card mb-5 max-w-3xl border-blue-200 bg-blue-50 p-4 text-sm text-slate-700">
        <div class="font-semibold text-blue-800">Profil acheteur du siteweb</div>
        @if($contact->registered_at)
            <div class="mt-1 text-slate-600">Inscrit le {{ $contact->registered_at->format('d/m/Y') }}</div>
        @endif
        @if($contact->interests)
            <div class="mt-2 whitespace-pre-line">{{ $contact->interests }}</div>
        @endif
    </div>
@endif
<form method="POST" action="{{ route('contacts.update', $contact) }}" class="card max-w-3xl p-6">
    @csrf @method('PUT')
    @include('contacts._form', ['contact' => $contact])
    <div class="mt-6 flex gap-2 border-t border-slate-100 pt-5">
        <button class="btn-primary">Mettre à jour</button>
        <a href="{{ route('contacts.index') }}" class="btn-outline">Annuler</a>
    </div>
</form>
@endsection
