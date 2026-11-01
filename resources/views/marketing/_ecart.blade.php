@php
    $d = $k['diff'];
@endphp
@if($d === null)
    <span class="text-xs text-slate-400">Pas de comparaison</span>
@else
    @php
        $cls = $k['good'] === null
            ? 'bg-slate-100 text-slate-600'
            : ($k['good'] ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700');
        $arrow = $d > 0 ? '▲' : ($d < 0 ? '▼' : '■');
        $txt = $k['type'] === 'rate'
            ? number_format(abs($d), $k['decimals'] ?? 1, ',', ' ') . ' pt'
            : ($k['pct'] !== null ? number_format(abs($k['pct']), 0, ',', ' ') . ' %' : number_format(abs($d), 0, ',', ' '));
    @endphp
    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {{ $cls }}">{{ $arrow }} {{ $txt }}</span>
    <span class="text-xs text-slate-400">contre {{ $fmt($k['type'], $k['previous'], $k['decimals'] ?? 1) }} avant</span>
@endif