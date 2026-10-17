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
                <option value="email" @selected(old('channel', 'email') === 'email')>Email</option>
                <option value="sms" @selected(old('channel') === 'sms')>SMS</option>
                <option value="messenger" @selected(old('channel') === 'messenger')>Messenger (Facebook)</option>
            </select>
        </div>

        <div class="md:col-span-2" id="subject-box">
            <label class="label">Objet de l'email</label>
            <input type="text" name="subject" value="{{ old('subject') }}" class="input">
        </div>
        <div class="md:col-span-2 hidden" id="messenger-note">
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Le message part uniquement aux contacts Facebook qui ont écrit à la Page dans les <strong>dernières 24 h</strong>.
                Les autres sont ignorés (règle de Meta).
            </div>
        </div>

        <div class="md:col-span-2">
            <label class="label">Message *</label>
            <textarea name="message" id="message" rows="7" class="input" required>{{ old('message') }}</textarea>
            <div class="mt-1.5 flex flex-wrap justify-between gap-2 text-xs text-slate-500">
                <span>Astuce : écrivez <code class="rounded bg-slate-100 px-1 py-0.5 text-slate-800">{nom}</code> pour insérer le nom du contact. Les liens (https://…) deviennent cliquables.</span>
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

        <div>
            <label class="label">Cadence d'envoi (messages par minute) *</label>
            <input type="number" name="per_minute" value="{{ old('per_minute', 30) }}" min="1" max="600" class="input" required>
            <p class="mt-1.5 text-xs text-slate-500">Respectez la limite de votre fournisseur SMTP (par minute et par jour).</p>
        </div>
        <div>
            <label class="label">Date et heure d'envoi (facultatif)</label>
            <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" class="input">
            <p class="mt-1.5 text-xs text-slate-500">Vous confirmerez la programmation sur la page suivante.</p>
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

    const messengerNote = document.getElementById('messenger-note');

    function refresh() {
        const ch = channel.value;
        subjectBox.style.display = ch === 'email' ? 'block' : 'none';
        messengerNote.classList.toggle('hidden', ch !== 'messenger');

        if (ch === 'sms') {
            const n = message.value.length;
            counter.textContent = n + ' caractères · ' + Math.max(1, Math.ceil(n / 160)) + ' SMS';
        } else {
            counter.textContent = '';
        }
    }
    channel.addEventListener('change', refresh);
    message.addEventListener('input', refresh);
    refresh();
</script>
@endsection