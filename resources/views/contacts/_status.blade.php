@php
    $map = [
        'prospect'  => 'bg-blue-100 text-blue-700',
        'contacted' => 'bg-slate-200 text-slate-700',
        'client'    => 'bg-slate-900 text-white',
        'lost'      => 'bg-white text-slate-500 ring-1 ring-inset ring-slate-300',
    ];
@endphp
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $map[$status] ?? 'bg-slate-100 text-slate-700' }}">
    {{ \App\Models\Contact::STATUSES[$status] ?? $status }}
</span>
