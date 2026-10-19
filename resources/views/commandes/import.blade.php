@extends('layouts.crm')
@section('title', 'Importer des commandes')

@section('content')
<div class="mb-6">
    <a href="{{ route('commandes.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">← Retour aux commandes</a>
    <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">Importer des commandes</h1>
    <p class="mt-1 text-sm text-slate-500">Importez votre fichier Excel / Google Sheets enregistré au format CSV.</p>
</div>

<div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
    <div class="card p-6">
        <form method="POST" action="{{ route('commandes.import') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="label" for="file">Fichier CSV</label>
                <input type="file" id="file" name="file" accept=".csv,.txt" class="input" required>
                <p class="mt-2 text-xs text-slate-400">Dans Excel ou Google Sheets : Fichier → Enregistrer sous / Télécharger → CSV. Taille maximale : 5 Mo.</p>
            </div>
            <button type="submit" class="btn-primary">Lancer l'import</button>
        </form>

        <ul class="mt-6 list-disc space-y-1.5 pl-5 text-sm text-slate-600">
            <li>Le client est retrouvé par son téléphone (ou email) ; s'il n'existe pas, il est créé.</li>
            <li>Chaque client importé avec une commande non annulée passe en statut « Client ».</li>
            <li>Vous pouvez réimporter le même fichier : une commande déjà importée (même client et même horodateur) est mise à jour, pas dupliquée.</li>
            <li>Une cellule vide n'écrase jamais une donnée existante.</li>
        </ul>
    </div>

    <div class="card p-6">
        <h2 class="text-base font-bold text-slate-900">Colonnes reconnues</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead>
                    <tr>
                        <th class="th !px-3">Dans votre fichier</th>
                        <th class="th !px-3">Dans le CRM</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach([
                        ['Horodateur', 'Horodateur'],
                        ['Anarana', 'Nom'],
                        ['Laharana finday', 'Téléphone'],
                        ['Adiresy mazava hanaterana ny colis', 'Adresse de livraison'],
                        ['Titre ny boky natao commande…', 'Livre(s) commandé(s)'],
                        ['Paiement', 'Paiement'],
                        ['Ny daty hanaterana ny colis', 'Date de livraison'],
                        ['Ny fotoana hanaterana ny colis…', 'Heure de livraison'],
                        ['RESPONSABLE', 'Responsable'],
                        ['Farito ny ora…', 'Précision de l\'heure'],
                        ['Montant total', 'Montant total'],
                        ['Statut de la commande', 'Statut'],
                    ] as [$source, $cible])
                        <tr>
                            <td class="px-3 py-2 text-slate-600">{{ $source }}</td>
                            <td class="px-3 py-2 font-medium text-slate-900">{{ $cible }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="mt-4 text-xs text-slate-500">
            Les colonnes en français (Nom, Téléphone, Email, Ville, Adresse de livraison, Livres, Paiement, Date de livraison,
            Heure de livraison, Responsable, Montant total, Statut) sont aussi reconnues.
        </p>

        <div class="mt-4 flex flex-wrap gap-2">
            @foreach(\App\Models\Commande::STATUTS as $key => $label)
                @include('commandes._statut', ['statut' => $key])
            @endforeach
        </div>
    </div>
</div>
@endsection