<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CRM')</title>
    @include('layouts._styles')
</head>
<body class="bg-gradient-to-br from-slate-100 via-slate-100 to-blue-50/60 text-slate-800 antialiased">

@php
    $nav = [
        ['route' => 'dashboard',          'match' => 'dashboard',   'label' => 'Tableau de bord', 'icon' => '<rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/>'],
        ['route' => 'contacts.index',     'match' => 'contacts.*',  'label' => 'Contacts',        'icon' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>'],
        ['route' => 'commandes.index',    'match' => ['commandes.index', 'commandes.create', 'commandes.edit', 'commandes.import.form'], 'label' => 'Commandes', 'icon' => '<path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 01-8 0"/>'],
        ['route' => 'commandes.classement', 'match' => 'commandes.classement', 'label' => 'Meilleurs clients', 'icon' => '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0V4z"/><path d="M17 5h3v2a3 3 0 01-3 3M7 5H4v2a3 3 0 003 3"/>'],
        ['route' => 'campaigns.index',    'match' => 'campaigns.*', 'label' => 'Campagnes',       'icon' => '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/>'],
        ['route' => 'password.edit',      'match' => 'password.*',  'label' => 'Mot de passe',    'icon' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>'],
    ];
    $user = auth()->user();
@endphp

<div class="min-h-screen lg:flex">

    {{-- Barre du haut (mobile) --}}
    <header class="lg:hidden flex items-center justify-between bg-slate-950 px-4 py-3 text-white">
        <div class="flex items-center gap-2">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 font-bold">C</span>
            <span class="font-semibold tracking-wide">CRM</span>
        </div>
        <button id="menu-btn" type="button" class="rounded-lg p-2 hover:bg-white/10" aria-label="Menu">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </header>

    {{-- Barre latérale --}}
    <aside id="sidebar" class="hidden lg:flex lg:flex-col lg:sticky lg:top-0 lg:h-screen lg:w-64 shrink-0 bg-slate-950 text-slate-300">
        <div class="hidden lg:flex items-center gap-3 px-6 py-6 border-b border-white/10">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-lg font-bold text-white shadow-lg shadow-blue-600/30">C</span>
            <div>
                <div class="font-semibold leading-tight text-white">CRM</div>
                <div class="text-xs text-slate-400">Clients &amp; prospects</div>
            </div>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-5">
            <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Menu</p>
            @foreach($nav as $item)
                @php
                    $active = request()->routeIs(...(array) $item['match']);
                @endphp
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                          {{ $active ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $item['icon'] !!}</svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="border-t border-white/10 p-4">
            <div class="mb-3 flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-sm font-semibold text-white">
                    {{ strtoupper(mb_substr($user->name ?? 'A', 0, 1)) }}
                </span>
                <div class="min-w-0">
                    <div class="truncate text-sm font-medium text-white">{{ $user->name }}</div>
                    <div class="truncate text-xs text-slate-400">{{ $user->email }}</div>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="flex w-full items-center justify-center gap-2 rounded-lg border border-white/15 px-3 py-2 text-sm text-slate-300 transition hover:bg-white/10 hover:text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
                    Déconnexion
                </button>
            </form>
        </div>
    </aside>

    {{-- Contenu --}}
    <main class="min-w-0 flex-1 p-4 sm:p-6 lg:p-10">
        <div class="mx-auto max-w-6xl">
            @if(session('success'))
                <div class="mb-5 flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 12l3 3 5-6"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc space-y-0.5 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</div>
@stack('scripts')
<script>
    document.getElementById('menu-btn').addEventListener('click', function () {
        document.getElementById('sidebar').classList.toggle('hidden');
    });
</script>
</body>
</html>