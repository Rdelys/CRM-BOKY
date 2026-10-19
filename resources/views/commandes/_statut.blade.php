@php
    $classes = [
        'confirmee'            => 'bg-blue-50 text-blue-700 ring-blue-600/20',
        'en_attente_livraison' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'annulee'              => 'bg-red-50 text-red-700 ring-red-600/20',
        'livree'               => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'reportee'             => 'bg-violet-50 text-violet-700 ring-violet-600/20',
    ];
@endphp
<span class="inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $classes[$statut] ?? 'bg-slate-100 text-slate-600 ring-slate-500/20' }}">
    {{ \App\Models\Commande::STATUTS[$statut] ?? $statut }}
</span>