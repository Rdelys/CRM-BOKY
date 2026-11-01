@extends('layouts.app')
@section('title', 'Frais de livraison')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Frais de livraison</h1>
    <p class="text-sm text-slate-500">Gérez les quartiers (et lieux précis) avec leur frais de livraison.</p>
</div>

{{-- Lien partageable --}}
<div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-2 flex items-center justify-between gap-3">
        <h2 class="font-semibold text-slate-900">Lien à partager aux commerciaux</h2>
        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $share->actif ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
            {{ $share->actif ? 'Actif' : 'Désactivé' }}
        </span>
    </div>
    <p class="mb-3 text-sm text-slate-500">Cette page est publique : elle affiche uniquement les frais de livraison, sans accès au CRM.</p>

    <div class="flex flex-col gap-2 sm:flex-row">
        <input id="share-url" type="text" readonly value="{{ $shareUrl }}"
               class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700">
        <button type="button" id="copy-btn"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Copier</button>
        <a href="{{ $shareUrl }}" target="_blank"
           class="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-50">Ouvrir</a>
    </div>

    <div class="mt-3 flex flex-wrap gap-2">
        <form method="POST" action="{{ route('livraisons.share.toggle') }}">
            @csrf
            <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                {{ $share->actif ? 'Désactiver le lien' : 'Activer le lien' }}
            </button>
        </form>
        <form method="POST" action="{{ route('livraisons.share.regenerate') }}"
              onsubmit="return confirm('Générer un nouveau lien ? L\'ancien ne fonctionnera plus.')">
            @csrf
            <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                Générer un nouveau lien
            </button>
        </form>
    </div>
</div>

{{-- Formulaire ajout / modification --}}
<div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="mb-4 font-semibold text-slate-900">{{ $editing ? 'Modifier' : 'Ajouter' }} un frais de livraison</h2>

    <form method="POST" action="{{ $editing ? route('livraisons.update', $editing) : route('livraisons.store') }}"
          class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @csrf
        @if($editing) @method('PUT') @endif

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Quartier *</label>
            <input type="text" name="quartier" required value="{{ old('quartier', $editing->quartier ?? '') }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Lieu précis (optionnel)</label>
            <input type="text" name="lieu_precis" value="{{ old('lieu_precis', $editing->lieu_precis ?? '') }}"
                   placeholder="ex : près de l'école X"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Frais (Ar) *</label>
            <input type="number" name="frais" min="0" step="100" required value="{{ old('frais', $editing->frais ?? '') }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Note (optionnel)</label>
            <input type="text" name="note" value="{{ old('note', $editing->note ?? '') }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
        </div>

        <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-4">
            <input type="checkbox" id="actif" name="actif" value="1" class="h-4 w-4 rounded border-slate-300"
                   {{ old('actif', $editing->actif ?? true) ? 'checked' : '' }}>
            <label for="actif" class="text-sm text-slate-700">Visible sur le lien public</label>

            <div class="ml-auto flex gap-2">
                @if($editing)
                    <a href="{{ route('livraisons.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Annuler</a>
                @endif
                <button class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    {{ $editing ? 'Enregistrer' : 'Ajouter' }}
                </button>
            </div>
        </div>
    </form>
</div>

{{-- Liste --}}
<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <form method="GET" class="border-b border-slate-100 p-4">
        <input type="search" name="q" value="{{ $q }}" placeholder="Rechercher un quartier ou un lieu…"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm sm:max-w-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
    </form>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Quartier</th>
                    <th class="px-4 py-3">Lieu précis</th>
                    <th class="px-4 py-3">Frais</th>
                    <th class="px-4 py-3">Note</th>
                    <th class="px-4 py-3">Statut</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($fees as $fee)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $fee->quartier }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $fee->lieu_precis ?: '—' }}</td>
                        <td class="px-4 py-3 font-semibold text-slate-900">{{ number_format($fee->frais, 0, ',', ' ') }} Ar</td>
                        <td class="px-4 py-3 text-slate-500">{{ $fee->note ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $fee->actif ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $fee->actif ? 'Visible' : 'Masqué' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('livraisons.edit', $fee) }}" class="text-blue-600 hover:underline">Modifier</a>
                                <form method="POST" action="{{ route('livraisons.destroy', $fee) }}"
                                      onsubmit="return confirm('Supprimer ce frais de livraison ?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 hover:underline">Supprimer</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun frais de livraison pour le moment.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($fees->hasPages())
        <div class="border-t border-slate-100 p-4">{{ $fees->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('copy-btn').addEventListener('click', function () {
        const input = document.getElementById('share-url');
        input.select();
        navigator.clipboard.writeText(input.value).then(() => {
            this.textContent = 'Copié ✓';
            setTimeout(() => this.textContent = 'Copier', 2000);
        });
    });
</script>
@endpush