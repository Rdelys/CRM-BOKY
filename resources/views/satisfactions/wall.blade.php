<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Ils nous font confiance · Ny Herin'ny Boky</title>
    <meta name="description" content="Découvrez les avis de nos lecteurs sur Ny Herin'ny Boky.">
    @include('layouts._styles')
    <style>
        @keyframes fadeUp { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: none; } }
        @keyframes floaty { 0%,100% { transform: translateY(0) } 50% { transform: translateY(-12px) } }
        .fade-up { opacity: 0; animation: fadeUp .6s cubic-bezier(.2,.7,.2,1) forwards; }
        .floaty  { animation: floaty 7s ease-in-out infinite; }
        .glass   { background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.14); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); }
        .review  { transition: transform .25s ease, box-shadow .25s ease; }
        .review:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -18px rgba(37,99,235,.35); }
        @media (prefers-reduced-motion: reduce) {
            .fade-up { animation: none; opacity: 1; }
            .floaty  { animation: none; }
            .review, .review:hover { transition: none; transform: none; }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">

@php
    $palette = [
        'from-blue-500 to-indigo-600',
        'from-fuchsia-500 to-purple-600',
        'from-emerald-500 to-teal-600',
        'from-amber-500 to-orange-600',
        'from-rose-500 to-pink-600',
        'from-cyan-500 to-sky-600',
    ];
    $moyenneEmoji = $moyenne > 0 ? $notes[(int) round($moyenne)]['emoji'] : '📚';
    $pleines = (int) round($moyenne);
@endphp

{{-- ================= HERO ================= --}}
<header class="relative overflow-hidden bg-slate-950 text-white">
    <div class="pointer-events-none absolute inset-0">
        <div class="floaty absolute -left-24 -top-24 h-72 w-72 rounded-full bg-blue-600/40 blur-3xl sm:h-96 sm:w-96"></div>
        <div class="floaty absolute -right-24 top-10 h-72 w-72 rounded-full bg-fuchsia-600/30 blur-3xl sm:h-96 sm:w-96" style="animation-delay:-3s"></div>
        <div class="absolute bottom-0 left-1/2 h-64 w-[120%] -translate-x-1/2 bg-gradient-to-t from-slate-950 to-transparent"></div>
    </div>

    <div class="relative mx-auto max-w-6xl px-4 pb-24 pt-12 sm:px-6 sm:pb-32 sm:pt-20">
        <div class="mx-auto max-w-3xl text-center">
            <span class="fade-up glass inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-xs font-medium text-blue-100 sm:text-sm">
                <span class="text-base">📚</span> Ny Herin'ny Boky
            </span>

            <h1 class="fade-up mt-5 text-3xl font-extrabold leading-tight tracking-tight sm:text-5xl lg:text-6xl" style="animation-delay:.08s">
                Ce que nos lecteurs
                <span class="bg-gradient-to-r from-blue-400 via-violet-400 to-fuchsia-400 bg-clip-text text-transparent">disent de nous</span>
            </h1>

            <p class="fade-up mx-auto mt-4 max-w-xl text-sm text-slate-300 sm:text-lg" style="animation-delay:.16s">
                Des avis sincères, laissés par de vrais clients après leur commande.
            </p>

            <div class="fade-up mt-7 flex flex-col items-center justify-center gap-3 sm:flex-row" style="animation-delay:.24s">
                <a href="{{ route('satisfactions.public', ['src' => 'avis']) }}"
                   class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-semibold text-slate-900 shadow-lg shadow-blue-500/20 transition hover:scale-[1.03] hover:bg-blue-50 sm:w-auto">
                    ✍️ Donner mon avis
                </a>
                <a href="#avis" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-white/20 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10 sm:w-auto">
                    Lire les avis ↓
                </a>
            </div>
        </div>
    </div>
</header>

{{-- ================= STATS (chevauchent le hero) ================= --}}
<section class="relative z-10 mx-auto -mt-16 max-w-6xl px-4 sm:-mt-20 sm:px-6">
    <div class="grid gap-4 lg:grid-cols-3">

        {{-- Note moyenne --}}
        <div class="fade-up rounded-3xl bg-white p-6 shadow-xl shadow-slate-900/5 ring-1 ring-slate-100 sm:p-8" style="animation-delay:.1s">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Note moyenne</div>
            <div class="mt-3 flex items-end gap-3">
                <span class="text-5xl font-extrabold leading-none text-slate-900 sm:text-6xl">{{ $moyenne }}</span>
                <span class="pb-1 text-lg font-medium text-slate-400">/ 5</span>
                <span class="ml-auto text-4xl sm:text-5xl">{{ $moyenneEmoji }}</span>
            </div>
            <div class="mt-3 text-xl tracking-widest text-amber-400" aria-label="{{ $moyenne }} sur 5">
                @for($i = 1; $i <= 5; $i++)<span class="{{ $i <= $pleines ? '' : 'text-slate-200' }}">★</span>@endfor
            </div>
            <div class="mt-2 text-sm text-slate-500">Basée sur <strong class="text-slate-800">{{ $total }}</strong> avis</div>
        </div>

        {{-- Satisfaction --}}
        <div class="fade-up rounded-3xl bg-gradient-to-br from-blue-600 to-indigo-700 p-6 text-white shadow-xl shadow-blue-900/20 sm:p-8" style="animation-delay:.18s">
            <div class="text-xs font-semibold uppercase tracking-wider text-blue-100">Clients satisfaits</div>
            <div class="mt-3 text-5xl font-extrabold leading-none sm:text-6xl">{{ $taux }}<span class="text-3xl">%</span></div>
            <div class="mt-5 h-2.5 overflow-hidden rounded-full bg-white/20">
                <div class="h-full rounded-full bg-white" style="width: {{ $taux }}%"></div>
            </div>
            <div class="mt-3 text-sm text-blue-100">ont noté 🙂 ou 😍</div>
        </div>

        {{-- Répartition --}}
        <div class="fade-up rounded-3xl bg-white p-6 shadow-xl shadow-slate-900/5 ring-1 ring-slate-100 sm:p-8" style="animation-delay:.26s">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Répartition des notes</div>
            <div class="mt-4 space-y-2.5">
                @foreach([5,4,3,2,1] as $v)
                    @php
                        $c = $distribution[$v] ?? 0;
                        $pct = $total ? round($c / $total * 100) : 0;
                    @endphp
                    <div class="flex items-center gap-2.5">
                        <span class="w-6 text-xl">{{ $notes[$v]['emoji'] }}</span>
                        <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-gradient-to-r from-blue-500 to-violet-500" style="width: {{ $pct }}%"></div>
                        </div>
                        <span class="w-9 text-right text-xs font-medium text-slate-500">{{ $pct }}%</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ================= AVIS ================= --}}
<main id="avis" class="mx-auto max-w-6xl scroll-mt-6 px-4 pb-16 pt-12 sm:px-6 sm:pt-16">

    {{-- Filtres --}}
    <div class="mb-8 flex flex-col items-center gap-4 text-center">
        <h2 class="text-2xl font-bold text-slate-900 sm:text-3xl">Les avis de nos clients</h2>
        <nav class="-mx-4 flex max-w-full gap-2 overflow-x-auto px-4 pb-1" aria-label="Filtrer par note">
            <a href="{{ route('satisfactions.wall') }}#avis"
               class="shrink-0 rounded-full px-4 py-2 text-sm font-semibold transition
                      {{ $filtre === 0 ? 'bg-slate-900 text-white shadow' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100' }}">
                Tous
            </a>
            @foreach($notesPubliques as $v => $n)
                <a href="{{ route('satisfactions.wall', ['note' => $v]) }}#avis"
                   class="shrink-0 rounded-full px-4 py-2 text-sm font-semibold transition
                          {{ $filtre === $v ? 'bg-slate-900 text-white shadow' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100' }}">
                    {{ $n['emoji'] }} {{ $n['label'] }}
                </a>
            @endforeach
        </nav>
    </div>

    @if($avis->isEmpty())
        <div class="mx-auto max-w-md rounded-3xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-100">
            <div class="text-5xl">💬</div>
            <p class="mt-3 font-semibold text-slate-800">Aucun avis à afficher pour le moment.</p>
            <p class="mt-1 text-sm text-slate-500">Soyez le premier à partager votre expérience !</p>
            <a href="{{ route('satisfactions.public', ['src' => 'avis']) }}" class="mt-5 inline-flex rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Donner mon avis</a>
        </div>
    @else
        <div class="columns-1 gap-5 sm:columns-2 lg:columns-3">
            @foreach($avis as $i => $s)
                @php $grad = $palette[$s->id % count($palette)]; @endphp
                <article class="review fade-up mb-5 break-inside-avoid rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6"
                         style="animation-delay: {{ min($i, 8) * 0.06 }}s">

                    <div class="flex items-start justify-between gap-3">
                        <div class="text-lg tracking-widest text-amber-400" aria-label="{{ $s->note }} sur 5">
                            @for($k = 1; $k <= 5; $k++)<span class="{{ $k <= $s->note ? '' : 'text-slate-200' }}">★</span>@endfor
                        </div>
                        <span class="text-3xl leading-none" title="{{ $notes[$s->note]['label'] }}">{{ $notes[$s->note]['emoji'] }}</span>
                    </div>

                    <p class="mt-3 whitespace-pre-line break-words text-[15px] leading-relaxed text-slate-700">
                        <span class="mr-0.5 text-2xl font-black leading-none text-blue-200">“</span>{{ $s->commentaire }}
                    </p>

                    @if($s->livre)
                        <div class="mt-4 inline-flex max-w-full items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                            <span>📖</span><span class="truncate">{{ $s->livre }}</span>
                        </div>
                    @endif

                    <div class="mt-4 flex items-center gap-3 border-t border-slate-100 pt-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br {{ $grad }} text-sm font-bold text-white shadow">
                            {{ $s->initiales }}
                        </div>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-slate-900">{{ $s->nom_public }}</div>
                            <div class="text-xs text-slate-400">{{ $s->created_at->translatedFormat('d F Y') }}</div>
                        </div>
                        <span class="ml-auto inline-flex items-center gap-1 text-xs font-medium text-emerald-600" title="Avis client">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                            Client
                        </span>
                    </div>
                </article>
            @endforeach
        </div>

        @if($avis->hasPages())
            <div class="mt-8 flex justify-center">{{ $avis->fragment('avis')->links() }}</div>
        @endif
    @endif

    {{-- CTA final --}}
    <section class="relative mt-14 overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-slate-900 to-blue-950 p-8 text-center text-white sm:p-12">
        <div class="pointer-events-none absolute -right-10 -top-10 h-48 w-48 rounded-full bg-blue-500/30 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-10 -left-10 h-48 w-48 rounded-full bg-fuchsia-500/20 blur-3xl"></div>
        <div class="relative">
            <div class="text-4xl">💙</div>
            <h3 class="mt-3 text-xl font-bold sm:text-2xl">Vous avez acheté un livre chez nous ?</h3>
            <p class="mx-auto mt-2 max-w-md text-sm text-slate-300">Votre avis prend 30 secondes et aide d'autres lecteurs à choisir.</p>
            <a href="{{ route('satisfactions.public', ['src' => 'avis']) }}"
               class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-white px-6 py-3 text-sm font-semibold text-slate-900 transition hover:scale-[1.03] sm:w-auto">
                Laisser un avis
            </a>
        </div>
    </section>
</main>

<footer class="border-t border-slate-200 bg-white py-6 text-center text-xs text-slate-400">
    © {{ date('Y') }} Ny Herin'ny Boky · Tous droits réservés
</footer>

</body>
</html>