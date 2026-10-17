<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — CRM</title>
    @include('layouts._styles')
</head>
<body class="min-h-screen bg-slate-950 antialiased">
<div class="relative flex min-h-screen items-center justify-center overflow-hidden p-4">
    <div class="pointer-events-none absolute -top-32 -left-32 h-96 w-96 rounded-full bg-blue-600/30 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-32 -right-32 h-96 w-96 rounded-full bg-blue-500/20 blur-3xl"></div>

    <form method="POST" action="{{ route('login.attempt') }}" class="relative w-full max-w-sm rounded-2xl bg-white p-8 shadow-2xl">
        @csrf

        <div class="mb-6 flex flex-col items-center text-center">
            <span class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600 text-xl font-bold text-white shadow-lg shadow-blue-600/30">C</span>
            <h1 class="text-xl font-bold text-slate-900">Connexion</h1>
            <p class="text-sm text-slate-500">Accédez à votre CRM</p>
        </div>

        @if($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="mb-4">
            <label class="label">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" class="input" required autofocus>
        </div>

        <div class="mb-4">
            <label class="label">Mot de passe</label>
            <input type="password" name="password" class="input" required>
        </div>

        <label class="mb-6 inline-flex items-center gap-2">
            <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-600">
            <span class="text-sm text-slate-600">Rester connecté</span>
        </label>

        <button class="btn-primary w-full">Se connecter</button>
    </form>
</div>
</body>
</html>
