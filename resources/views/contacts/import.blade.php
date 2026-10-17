@extends('layouts.crm')
@section('title', 'Importer des contacts')

@section('content')
<div class="mb-6">
    <a href="{{ route('contacts.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">← Retour aux contacts</a>
    <h1 class="mt-1 text-2xl font-bold text-slate-900">Importer des contacts</h1>
    <p class="text-sm text-slate-500">Depuis un fichier CSV (Excel, Facebook Lead Ads…)</p>
</div>

<div class="card max-w-3xl p-6">
    <p class="mb-2 text-sm text-slate-600">
        La première ligne du fichier doit contenir ces colonnes (séparateur virgule ou point-virgule) :
    </p>
    <pre class="mb-4 overflow-x-auto rounded-lg bg-slate-950 px-4 py-3 text-sm text-blue-300">name,phone,email,source,status,city</pre>

    <ul class="mb-6 space-y-1 text-sm text-slate-600">
        <li><span class="font-semibold text-slate-800">source</span> : website, facebook, whatsapp, direct, other</li>
        <li><span class="font-semibold text-slate-800">status</span> : prospect, contacted, client, lost</li>
        <li>Les contacts déjà présents (même téléphone ou email) sont mis à jour.</li>
    </ul>

    <form method="POST" action="{{ route('contacts.import') }}" enctype="multipart/form-data">
        @csrf
        <input type="file" name="file" accept=".csv,.txt" required
               class="mb-6 block w-full rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600
                      file:mr-4 file:rounded-lg file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-700">
        <div class="flex gap-2">
            <button class="btn-primary">Importer</button>
            <a href="{{ route('contacts.index') }}" class="btn-outline">Annuler</a>
        </div>
    </form>
</div>
@endsection
