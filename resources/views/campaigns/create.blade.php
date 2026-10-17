@extends('layouts.crm')
@section('title', 'Nouvelle campagne')

@section('content')
<div class="mb-6">
    <a href="{{ route('campaigns.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">← Retour aux campagnes</a>
    <h1 class="mt-1 text-2xl font-bold text-slate-900">Nouvelle campagne</h1>
</div>

<form method="POST" action="{{ route('campaigns.store') }}" class="card max-w-3xl p-6">
    @csrf
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <div>
            <label class="label">Nom de la campagne *</label>
            <input type="text" name="name" value="{{ old('name') }}" class="input" placeholder="Ex : Promo octobre" required>
        </div>
        <div>
            <label class="label">Canal *</label>
            <select name="channel" id="channel" class="input">
                <option value="sms" @selected(old('channel') === 'sms')>SMS</option>
                <option value="email" @selected(old('channel') === 'email')>Email</option>
            </select>
        </div>

        <div class="md:col-span-2" id="subject-box">
            <label class="label">Objet de l'email</label>
            <input type="text" name="subject" value="{{ old('subject') }}" class="input">
        </div>

        <div class="md:col-span-2">
            <label class="label">Message *</label>
            <textarea name="message" id="message" rows="5" class="input" required>{{ old('message') }}</textarea>
            <div class="mt-1.5 flex flex-wrap justify-between gap-2 text-xs text-slate-500">
                <span>Astuce : écrivez <code class="rounded bg-slate-100 px-1 py-0.5 text-slate-800">{nom}</code> pour insérer le nom du contact.</span>
                <span id="counter" class="font-medium text-slate-700"></span>
            </div>
        </div>

        <div>
            <label class="label">Cibler par statut</label>
            <select name="segment_status" class="input">
                <option value="">Tous les statuts</option>
                @foreach(\App\Models\Contact::STATUSES as $key => $label)
                    <option value="{{ $key }}" @selected(old('segment_status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Cibler par source</label>
            <select name="segment_source" class="input">
                <option value="">Toutes les sources</option>
                @foreach(\App\Models\Contact::SOURCES as $key => $label)
                    <option value="{{ $key }}" @selected(old('segment_source') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="mt-6 flex gap-2 border-t border-slate-100 pt-5">
        <button class="btn-primary">Créer le brouillon</button>
        <a href="{{ route('campaigns.index') }}" class="btn-outline">Annuler</a>
    </div>
</form>

<script>
    const channel = document.getElementById('channel');
    const subjectBox = document.getElementById('subject-box');
    const message = document.getElementById('message');
    const counter = document.getElementById('counter');

    function refresh() {
        const isEmail = channel.value === 'email';
        subjectBox.style.display = isEmail ? 'block' : 'none';
        if (isEmail) {
            counter.textContent = '';
        } else {
            const n = message.value.length;
            counter.textContent = n + ' caractères · ' + Math.max(1, Math.ceil(n / 160)) + ' SMS';
        }
    }
    channel.addEventListener('change', refresh);
    message.addEventListener('input', refresh);
    refresh();
</script>
@endsection
