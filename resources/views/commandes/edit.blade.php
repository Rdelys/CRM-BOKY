@extends('layouts.crm')
@section('title', 'Modifier la commande')

@section('content')
<div class="mb-6">
    <a href="{{ route('commandes.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">← Retour aux commandes</a>
    <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">Modifier la commande #{{ $commande->id }}</h1>
</div>

@include('commandes._form', [
    'action'      => route('commandes.update', $commande),
    'method'      => 'PUT',
    'bouton'      => 'Mettre à jour',
    'commande'    => $commande,
    'contact'     => $contact,
    'modeContact' => $modeContact,
])
@endsection