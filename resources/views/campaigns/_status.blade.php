@php
    $map = [
        'draft'   => ['Brouillon', 'bg-slate-200 text-slate-700'],
        'sending' => ['En cours',  'bg-blue-100 text-blue-700'],
        'sent'    => ['Envoyée',   'bg-slate-900 text-white'],
    ];
    [$label, $classes] = $map[$status] ?? [$status, 'bg-slate-100 text-slate-700'];
@endphp
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $classes }}">{{ $label }}</span>
