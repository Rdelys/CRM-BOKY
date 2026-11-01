<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Frais de livraison</title>
    @include('layouts._styles')
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 via-slate-50 to-indigo-50 text-slate-800 antialiased">

<div class="mx-auto max-w-3xl px-4 py-10 sm:py-14">

    <header class="mb-8 text-center">
        <span class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-lg shadow-blue-600/30">
            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        </span>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">Frais de livraison</h1>
        <p class="mt-2 text-slate-500">Recherchez un quartier pour connaître le tarif de livraison.</p>
    </header>

    <div class="sticky top-3 z-10 mb-6">
        <div class="relative">
            <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/></svg>
            <input id="search" type="search" autocomplete="off" autofocus
                   placeholder="Rechercher un quartier ou un lieu…"
                   class="w-full rounded-2xl border border-slate-200 bg-white py-4 pl-12 pr-4 text-base shadow-lg shadow-slate-200/60 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100">
        </div>
        <p id="counter" class="mt-2 px-2 text-xs text-slate-400"></p>
    </div>

    <ul id="list" class="space-y-3">
        @foreach($fees as $fee)
            <li class="fee-item flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md"
                data-search="{{ \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($fee->quartier.' '.$fee->lieu_precis.' '.$fee->note)) }}">
                <div class="min-w-0">
                    <div class="truncate font-semibold text-slate-900">{{ $fee->quartier }}</div>
                    @if($fee->lieu_precis)
                        <div class="truncate text-sm text-slate-500">📍 {{ $fee->lieu_precis }}</div>
                    @endif
                    @if($fee->note)
                        <div class="mt-0.5 text-xs text-slate-400">{{ $fee->note }}</div>
                    @endif
                </div>
                <div class="shrink-0 rounded-xl bg-blue-50 px-4 py-2 text-right">
                    <div class="text-lg font-bold text-blue-700">{{ number_format($fee->frais, 0, ',', ' ') }}</div>
                    <div class="text-[10px] font-medium uppercase tracking-wide text-blue-500">Ariary</div>
                </div>
            </li>
        @endforeach
    </ul>

    <div id="empty" class="hidden rounded-2xl border border-dashed border-slate-300 bg-white/60 p-10 text-center text-slate-500">
        Aucun résultat pour cette recherche.
    </div>

    <footer class="mt-10 text-center text-xs text-slate-400">Tarifs susceptibles d'évoluer.</footer>
</div>

<script>
    const input   = document.getElementById('search');
    const items   = Array.from(document.querySelectorAll('.fee-item'));
    const empty   = document.getElementById('empty');
    const counter = document.getElementById('counter');

    // Normalise : minuscules + suppression des accents
    const norm = s => s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();

    function filter() {
        const words = norm(input.value).split(/\s+/).filter(Boolean);
        let visible = 0;

        items.forEach(li => {
            const text = li.dataset.search;
            const ok = words.every(w => text.includes(w));
            li.classList.toggle('hidden', !ok);
            if (ok) visible++;
        });

        empty.classList.toggle('hidden', visible > 0);
        counter.textContent = visible + ' résultat' + (visible > 1 ? 's' : '');
    }

    input.addEventListener('input', filter);
    filter();
</script>
</body>
</html>