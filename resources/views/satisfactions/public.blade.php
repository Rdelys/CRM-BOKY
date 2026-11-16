<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Votre avis nous intéresse</title>
    @include('layouts._styles')
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-100 via-slate-100 to-blue-50 text-slate-800 antialiased">
<main class="mx-auto flex min-h-screen max-w-lg items-center px-4 py-8 sm:py-12">
    <div class="card w-full p-5 sm:p-8">

        <div class="mb-6 text-center">
            <div class="text-5xl">📚</div>
            <h1 class="mt-3 text-xl font-bold text-slate-900 sm:text-2xl">Votre avis nous intéresse</h1>
            <p class="mt-1 text-sm text-slate-500">Dites-nous ce que vous avez pensé de votre livre. Cela prend 30 secondes.</p>
        </div>

        @if($errors->any())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc space-y-0.5 pl-5">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('satisfactions.store') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="source" value="{{ old('source', $source) }}">
            {{-- Champ piège (les humains ne le voient pas) --}}
            <div class="hidden" aria-hidden="true">
                <input type="text" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div>
                <label for="nom" class="label">Votre nom <span class="text-red-500">*</span></label>
                <input id="nom" name="nom" type="text" class="input" value="{{ old('nom') }}"
                       placeholder="Ex : Rakoto Jean" autocomplete="name" required>
            </div>

            <div>
                <label for="livre" class="label">Titre du livre <span class="text-xs font-normal text-slate-400">(facultatif)</span></label>
                <input id="livre" name="livre" type="text" class="input" value="{{ old('livre') }}"
                       placeholder="Le livre que vous avez acheté">
            </div>

            <div>
                <span class="label">Votre note <span class="text-red-500">*</span></span>
                <div class="grid grid-cols-5 gap-1.5 sm:gap-2">
                    @foreach($notes as $value => $n)
                        <label class="cursor-pointer">
                            <input type="radio" name="note" value="{{ $value }}" class="peer sr-only"
                                   @checked((int) old('note') === $value) required>
                            <div class="flex flex-col items-center gap-1 rounded-xl border-2 border-slate-200 bg-white px-1 py-3 text-center transition
                                        hover:border-blue-300 peer-checked:scale-105 peer-checked:border-blue-600 peer-checked:bg-blue-50
                                        peer-focus-visible:ring-2 peer-focus-visible:ring-blue-600/40">
                                <span class="text-3xl sm:text-4xl">{{ $n['emoji'] }}</span>
                                <span class="text-[10px] font-medium leading-tight text-slate-600 sm:text-xs">{{ $n['label'] }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label for="commentaire" class="label">Votre commentaire <span class="text-xs font-normal text-slate-400">(facultatif)</span></label>
                <textarea id="commentaire" name="commentaire" rows="4" maxlength="2000" class="input"
                          placeholder="Qu'avez-vous aimé ? Que pouvons-nous améliorer ?">{{ old('commentaire') }}</textarea>
            </div>

            <button type="submit" class="btn-primary w-full py-3 text-base">Envoyer mon avis</button>
        </form>
    </div>
</main>
</body>
</html>