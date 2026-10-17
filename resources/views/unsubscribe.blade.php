<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Désinscription</title>
    @include('layouts._styles')
</head>
<body class="min-h-screen bg-slate-100 antialiased">
<div class="flex min-h-screen items-center justify-center p-4">
    <div class="card w-full max-w-md p-8 text-center">
        <h1 class="text-xl font-bold text-slate-900">Désinscription confirmée</h1>
        <p class="mt-2 text-sm text-slate-600">
            @if($name){{ $name }}, vous @else Vous @endif ne recevrez plus nos emails ni nos SMS d'offres.
        </p>
    </div>
</div>
</body>
</html>