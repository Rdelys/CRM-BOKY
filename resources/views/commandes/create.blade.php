@extends('layouts.crm')
@section('title', 'Nouvelle commande')

@section('content')
<div class="mb-6">
    <a href="{{ route('commandes.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">← Retour aux commandes</a>
    <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">Nouvelle commande</h1>
    <p class="mt-1 text-sm text-slate-500">Choisissez un client existant (Facebook, siteweb, direct…) ou créez-en un nouveau.</p>
</div>

@include('commandes._form', [
    'action'      => route('commandes.store'),
    'method'      => 'POST',
    'bouton'      => 'Enregistrer la commande',
    'commande'    => $commande,
    'contact'     => $contact,
    'modeContact' => $modeContact,
])
@endsection