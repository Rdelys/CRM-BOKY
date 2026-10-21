@php
    $mode = in_array(old('mode_contact', $modeContact), ['existant', 'nouveau'], true) ? old('mode_contact', $modeContact) : 'existant';
    $statutActuel = old('statut', $commande->statut ?: 'confirmee');
    $horodateur = old('date_commande', optional($commande->date_commande)->format('Y-m-d\TH:i'));
    $livraison = old('date_livraison', optional($commande->date_livraison)->format('Y-m-d'));
    $contactId = old('contact_id', $contact?->id);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-5">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    {{-- Client --}}
    <div class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-slate-900">Client</h2>
                <p class="text-sm text-slate-500">Le statut du client passe automatiquement à « Client » dès que la commande n'est pas annulée.</p>
            </div>
            <div class="seg">
                <label id="lbl-existant" class="seg-btn cursor-pointer {{ $mode === 'existant' ? 'is-active' : '' }}">
                    <input type="radio" name="mode_contact" value="existant" class="sr-only" @checked($mode === 'existant')>
                    Client existant
                </label>
                <label id="lbl-nouveau" class="seg-btn cursor-pointer {{ $mode === 'nouveau' ? 'is-active' : '' }}">
                    <input type="radio" name="mode_contact" value="nouveau" class="sr-only" @checked($mode === 'nouveau')>
                    Nouveau client
                </label>
            </div>
        </div>

        {{-- Client existant --}}
        <div id="bloc-existant" class="mt-5 {{ $mode === 'existant' ? '' : 'hidden' }}">
            <input type="hidden" name="contact_id" id="contact_id" value="{{ $contactId }}">

            <div id="contact-selected" class="{{ $contact ? 'flex' : 'hidden' }} items-center justify-between rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">
                <div class="min-w-0">
                    <div id="sel-nom" class="truncate text-sm font-semibold text-slate-900">{{ $contact?->name }}</div>
                    <div id="sel-info" class="truncate text-xs text-slate-500">{{ $contact ? implode(' · ', array_filter([$contact->phone, $contact->email, \App\Models\Contact::STATUSES[$contact->status] ?? null])) : '' }}</div>
                </div>
                <button type="button" id="contact-change" class="text-sm font-medium text-blue-600 hover:text-blue-800">Changer</button>
            </div>

            <div id="contact-picker" class="relative {{ $contact ? 'hidden' : '' }}">
                <label class="label" for="contact-search">Rechercher un client (nom, téléphone, email)</label>
                <input type="text" id="contact-search" class="input" autocomplete="off" placeholder="Tapez pour rechercher…">
                <ul id="contact-results" class="absolute z-20 mt-1 hidden max-h-64 w-full overflow-auto rounded-xl border border-slate-200 bg-white shadow-lg"></ul>
            </div>
            <div class="mt-4 sm:max-w-sm">
                <label class="label" for="contact_telephone">Téléphone du client</label>
                <input type="text" id="contact_telephone" name="contact_telephone"
                    value="{{ old('contact_telephone', $contact?->phone) }}"
                    class="input" maxlength="30" placeholder="032 12 345 67">
                <p class="mt-1 text-xs text-slate-400">Ajoutez ou corrigez le numéro : il sera enregistré sur la fiche du client.</p>
            </div>
        </div>

        {{-- Nouveau client --}}
        <div id="bloc-nouveau" class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 {{ $mode === 'nouveau' ? '' : 'hidden' }}">
            <div>
                <label class="label" for="nouveau_nom">Nom</label>
                <input type="text" id="nouveau_nom" name="nouveau_nom" value="{{ old('nouveau_nom') }}" class="input" maxlength="120">
            </div>
            <div>
                <label class="label" for="nouveau_telephone">Téléphone</label>
                <input type="text" id="nouveau_telephone" name="nouveau_telephone" value="{{ old('nouveau_telephone') }}" class="input" maxlength="30" placeholder="032 12 345 67">
            </div>
            <div>
                <label class="label" for="nouveau_email">Email</label>
                <input type="email" id="nouveau_email" name="nouveau_email" value="{{ old('nouveau_email') }}" class="input" maxlength="150">
            </div>
            <div>
                <label class="label" for="nouveau_ville">Ville</label>
                <input type="text" id="nouveau_ville" name="nouveau_ville" value="{{ old('nouveau_ville') }}" class="input" maxlength="100">
            </div>
            <div>
                <label class="label" for="nouveau_source">Origine</label>
                <select id="nouveau_source" name="nouveau_source" class="input">
                    @foreach(\App\Models\Contact::SOURCES as $key => $label)
                        <option value="{{ $key }}" @selected(old('nouveau_source', 'direct') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <p class="self-end text-xs text-slate-400">Si ce téléphone ou cet email existe déjà, la commande est rattachée au contact existant.</p>
        </div>
    </div>

    {{-- Commande --}}
    <div class="card p-6">
        <h2 class="text-base font-bold text-slate-900">Commande</h2>

        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="date_commande">Horodateur</label>
                <input type="datetime-local" id="date_commande" name="date_commande" value="{{ $horodateur }}" class="input">
            </div>
            <div>
                <label class="label" for="statut">Statut de la commande</label>
                <select id="statut" name="statut" class="input">
                    @foreach(\App\Models\Commande::STATUTS as $key => $label)
                        <option value="{{ $key }}" @selected($statutActuel === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-2">
                <label class="label" for="livres">Livre(s) commandé(s)</label>
                <textarea id="livres" name="livres" rows="3" class="input" maxlength="2000" placeholder="Un titre par ligne (ou séparés par « / »)">{{ old('livres', $commande->livres) }}</textarea>
            </div>

            <div>
                <label class="label" for="montant_total">Montant total (Ar)</label>
                <input type="number" id="montant_total" name="montant_total" value="{{ old('montant_total', $commande->montant_total) }}" min="0" step="100" class="input">
            </div>
            <div>
                <label class="label" for="mode_paiement">Paiement</label>
                <input type="text" id="mode_paiement" name="mode_paiement" value="{{ old('mode_paiement', $commande->mode_paiement) }}" list="liste-paiements" class="input" maxlength="60" placeholder="En espèce">
                <datalist id="liste-paiements">
                    @foreach(\App\Models\Commande::PAIEMENTS as $p)
                        <option value="{{ $p }}"></option>
                    @endforeach
                </datalist>
            </div>

            <div class="sm:col-span-2">
                <label class="label" for="adresse_livraison">Adresse de livraison</label>
                <textarea id="adresse_livraison" name="adresse_livraison" rows="2" class="input" maxlength="1000">{{ old('adresse_livraison', $commande->adresse_livraison) }}</textarea>
            </div>

            <div>
                <label class="label" for="date_livraison">Date de livraison</label>
                <input type="date" id="date_livraison" name="date_livraison" value="{{ $livraison }}" class="input">
            </div>
            <div>
                <label class="label" for="heure_livraison">Heure de livraison</label>
                <input type="text" id="heure_livraison" name="heure_livraison" value="{{ old('heure_livraison', $commande->heure_livraison) }}" class="input" maxlength="30" placeholder="15:00">
            </div>

            <div>
                <label class="label" for="precision_heure">Précision de l'heure</label>
                <input type="text" id="precision_heure" name="precision_heure" value="{{ old('precision_heure', $commande->precision_heure) }}" class="input" maxlength="255">
            </div>
            <div>
                <label class="label" for="responsable">Responsable</label>
                <input type="text" id="responsable" name="responsable" value="{{ old('responsable', $commande->responsable) }}" class="input" maxlength="120">
            </div>

            <div class="sm:col-span-2">
                <label class="label" for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="2" class="input" maxlength="2000">{{ old('notes', $commande->notes) }}</textarea>
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <a href="{{ route('commandes.index') }}" class="btn-outline">Annuler</a>
        <button type="submit" class="btn-primary">{{ $bouton }}</button>
    </div>
</form>

@push('scripts')
<script>
(function () {
    const URL_RECHERCHE = @json(route('commandes.contacts.recherche'));
    const $ = id => document.getElementById(id);
    let minuteur = null;

    function modeCourant() {
        return document.querySelector('input[name="mode_contact"]:checked').value;
    }

    function majMode() {
        const m = modeCourant();
        $('bloc-existant').classList.toggle('hidden', m !== 'existant');
        $('bloc-nouveau').classList.toggle('hidden', m !== 'nouveau');
        $('lbl-existant').classList.toggle('is-active', m === 'existant');
        $('lbl-nouveau').classList.toggle('is-active', m === 'nouveau');
    }

    document.querySelectorAll('input[name="mode_contact"]').forEach(r => r.addEventListener('change', majMode));

    function choisir(c) {
        $('contact_id').value = c.id;
        $('sel-nom').textContent = c.name;
        $('contact_telephone').value = c.phone || '';   // ← ajout
        $('sel-info').textContent = [c.phone, c.email, c.status].filter(Boolean).join(' · ');
        $('contact-selected').classList.remove('hidden');
        $('contact-selected').classList.add('flex');
        $('contact-picker').classList.add('hidden');
        $('contact-results').classList.add('hidden');
    }

    async function chercher() {
        const q = $('contact-search').value.trim();
        const ul = $('contact-results');

        try {
            const res = await fetch(URL_RECHERCHE + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const liste = await res.json();

            ul.innerHTML = '';

            if (!liste.length) {
                const li = document.createElement('li');
                li.className = 'px-4 py-3 text-sm text-slate-500';
                li.textContent = 'Aucun client trouvé. Passez sur « Nouveau client » pour le créer.';
                ul.appendChild(li);
            }

            liste.forEach(c => {
                const li = document.createElement('li');
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'block w-full px-4 py-2.5 text-left hover:bg-slate-50';

                const nom = document.createElement('div');
                nom.className = 'text-sm font-medium text-slate-900';
                nom.textContent = c.name;

                const info = document.createElement('div');
                info.className = 'text-xs text-slate-500';
                info.textContent = [c.phone, c.email, c.status].filter(Boolean).join(' · ');

                b.appendChild(nom);
                b.appendChild(info);
                b.addEventListener('click', () => choisir(c));
                li.appendChild(b);
                ul.appendChild(li);
            });

            ul.classList.remove('hidden');
        } catch (e) {
            ul.classList.add('hidden');
        }
    }

    $('contact-search').addEventListener('input', () => {
        clearTimeout(minuteur);
        minuteur = setTimeout(chercher, 250);
    });
    $('contact-search').addEventListener('focus', chercher);

    $('contact-change').addEventListener('click', () => {
        $('contact_id').value = '';
        $('contact_telephone').value = '';              // ← ajout
        $('contact-selected').classList.add('hidden');
        $('contact-selected').classList.remove('flex');
        $('contact-picker').classList.remove('hidden');
        $('contact-search').value = '';
        $('contact-search').focus();
    });

    document.addEventListener('click', e => {
        if (!$('contact-picker').contains(e.target)) {
            $('contact-results').classList.add('hidden');
        }
    });

    majMode();
})();
</script>
@endpush