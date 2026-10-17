<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $contacts = Contact::query()
            ->when($request->q, function ($q, $term) {
                $q->where(function ($w) use ($term) {
                    $w->where('name', 'like', "%{$term}%")
                      ->orWhere('phone', 'like', "%{$term}%")
                      ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->source, fn ($q, $v) => $q->where('source', $v))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('contacts.index', compact('contacts'));
    }

    public function create()
    {
        return view('contacts.create');
    }

    public function store(Request $request)
    {
        Contact::create($this->validated($request));

        return redirect()->route('contacts.index')->with('success', 'Contact ajouté.');
    }

    public function edit(Contact $contact)
    {
        return view('contacts.edit', compact('contact'));
    }

    public function update(Request $request, Contact $contact)
    {
        $contact->update($this->validated($request));

        return redirect()->route('contacts.index')->with('success', 'Contact mis à jour.');
    }

    public function destroy(Contact $contact)
    {
        $contact->delete();

        return redirect()->route('contacts.index')->with('success', 'Contact supprimé.');
    }

    public function importForm()
    {
        return view('contacts.import');
    }

    /**
     * Import CSV. Colonnes attendues (1ère ligne) : name,phone,email,source,status,city
     * Séparateur , ou ;  — les doublons (même téléphone ou email) sont mis à jour.
     */
    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $firstLine = fgets($handle);
        rewind($handle);

        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', (string) $firstLine);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $header = fgetcsv($handle, 0, $delimiter);
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header ?: []);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($row) !== count($header)) {
                $skipped++;
                continue;
            }

            $data = array_combine($header, array_map('trim', $row));
            $phone = Contact::normalizePhone($data['phone'] ?? null);
            $email = !empty($data['email']) ? strtolower($data['email']) : null;

            if (empty($data['name']) || (!$phone && !$email)) {
                $skipped++;
                continue;
            }

            $contact = Contact::query()
                ->when($phone, fn ($q) => $q->where('phone', $phone))
                ->when(!$phone && $email, fn ($q) => $q->where('email', $email))
                ->first();

            $payload = [
                'name'   => $data['name'],
                'phone'  => $phone,
                'email'  => $email,
                'source' => array_key_exists($data['source'] ?? '', Contact::SOURCES) ? $data['source'] : 'other',
                'status' => array_key_exists($data['status'] ?? '', Contact::STATUSES) ? $data['status'] : 'prospect',
                'city'   => $data['city'] ?? null,
            ];

            if ($contact) {
                $contact->update($payload);
                $updated++;
            } else {
                Contact::create($payload);
                $created++;
            }
        }
        fclose($handle);

        return redirect()->route('contacts.index')
            ->with('success', "Import terminé : {$created} créés, {$updated} mis à jour, {$skipped} ignorés.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'   => 'required|string|max:120',
            'phone'  => 'nullable|string|max:30|required_without:email',
            'email'  => 'nullable|email|max:150|required_without:phone',
            'source' => ['required', Rule::in(array_keys(Contact::SOURCES))],
            'status' => ['required', Rule::in(array_keys(Contact::STATUSES))],
            'city'   => 'nullable|string|max:100',
            'notes'  => 'nullable|string|max:2000',
        ]);

        $data['unsubscribed'] = $request->boolean('unsubscribed');

        return $data;
    }
}
