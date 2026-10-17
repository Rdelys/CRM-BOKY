@extends('layouts.crm')
@section('title', 'Mot de passe')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Changer mon mot de passe</h1>
    <p class="text-sm text-slate-500">Minimum 8 caractères</p>
</div>

<form method="POST" action="{{ route('password.update') }}" class="card max-w-lg p-6">
    @csrf @method('PUT')

    <div class="mb-4">
        <label class="label">Mot de passe actuel</label>
        <input type="password" name="current_password" class="input" required>
    </div>
    <div class="mb-4">
        <label class="label">Nouveau mot de passe</label>
        <input type="password" name="password" class="input" required>
    </div>
    <div class="mb-6">
        <label class="label">Confirmer le nouveau mot de passe</label>
        <input type="password" name="password_confirmation" class="input" required>
    </div>

    <button class="btn-primary">Enregistrer</button>
</form>
@endsection