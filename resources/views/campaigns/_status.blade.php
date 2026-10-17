@php
    $map = [
        'draft'     => ['Brouillon',  'bg-slate-200 text-slate-700'],
        'scheduled' => ['Programmée', 'bg-amber-100 text-amber-800'],
        'sending'   => ['En cours',   'bg-blue-100 text-blue-700'],
        'sent'      => ['Envoyée',    'bg-slate-900 text-white'],
        'cancelled' => ['Annulée',    'bg-white text-slate-500 ring-1 ring-inset ring-slate-300'],
    ];
    [$label, $classes] = $map[$status] ?? [$status, 'bg-slate-100 text-slate-700'];
@endphp
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $classes }}">{{ $label }}</span>