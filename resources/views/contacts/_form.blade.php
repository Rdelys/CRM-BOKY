@php($c = $contact ?? null)

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div>
        <label class="label">Nom *</label>
        <input type="text" name="name" value="{{ old('name', $c?->name) }}" class="input" required>
    </div>
    <div>
        <label class="label">Ville</label>
        <input type="text" name="city" value="{{ old('city', $c?->city) }}" class="input">
    </div>
    <div>
        <label class="label">Téléphone</label>
        <input type="text" name="phone" value="{{ old('phone', $c?->phone) }}" placeholder="034 12 345 67" class="input">
    </div>
    <div>
        <label class="label">Email</label>
        <input type="email" name="email" value="{{ old('email', $c?->email) }}" placeholder="nom@exemple.com" class="input">
    </div>
    <div>
        <label class="label">Source</label>
        <select name="source" class="input">
            @foreach(\App\Models\Contact::SOURCES as $key => $label)
                <option value="{{ $key }}" @selected(old('source', $c?->source ?? 'direct') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label">Statut</label>
        <select name="status" class="input">
            @foreach(\App\Models\Contact::STATUSES as $key => $label)
                <option value="{{ $key }}" @selected(old('status', $c?->status ?? 'prospect') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="label">Notes</label>
        <textarea name="notes" rows="4" class="input">{{ old('notes', $c?->notes) }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
            <input type="checkbox" name="unsubscribed" value="1" @checked(old('unsubscribed', $c?->unsubscribed))
                   class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-600">
            <span class="text-sm text-slate-700">
                <span class="font-medium">Ne plus contacter</span><br>
                <span class="text-slate-500">Ce contact ne recevra plus de SMS ni d'emails d'offres.</span>
            </span>
        </label>
    </div>
</div>
