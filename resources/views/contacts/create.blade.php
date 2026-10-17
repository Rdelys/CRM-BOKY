@extends('layouts.crm')
@section('title', 'Nouveau contact')

@section('content')
<div class="mb-6">
    <a href="{{ route('contacts.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">← Retour aux contacts</a>
    <h1 class="mt-1 text-2xl font-bold text-slate-900">Nouveau contact</h1>
</div>

<form method="POST" action="{{ route('contacts.store') }}" class="card max-w-3xl p-6">
    @csrf
    @include('contacts._form')
    <div class="mt-6 flex gap-2 border-t border-slate-100 pt-5">
        <button class="btn-primary">Enregistrer</button>
        <a href="{{ route('contacts.index') }}" class="btn-outline">Annuler</a>
    </div>
</form>
@endsection
