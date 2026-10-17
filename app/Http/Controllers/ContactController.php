<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    /** En-têtes alternatifs (Excel, Facebook Lead Ads…) => colonne du CRM. */
    private const HEADER_ALIASES = [
        'full_name'     => 'name',
        'nom'           => 'name',
        'nom_complet'   => 'name',
        'phone_number'  => 'phone',
        'telephone'     => 'phone',
        'tel'           => 'phone',
        'mobile'        => 'phone',
        'numero'        => 'phone',
        'e_mail'        => 'email',
        'mail'          => 'email',
        'adresse_email' => 'email',
        'ville'         => 'city',
        'statut'        => 'status',
        'origine'       => 'source',
    ];

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
     * Import CSV (UTF-8, ANSI Excel ou UTF-16 Facebook ; séparateur , ; ou tabulation).
     * Colonnes reconnues : name (ou full_name, first_name + last_name), phone (ou phone_number),
     * email, source, status, city.
     *
     * - Les contacts existants (même téléphone OU même email) sont mis à jour.
     * - Une cellule vide n'écrase jamais une donnée existante.
     * - Un client n'est jamais rétrogradé.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'extensions:csv,txt'],
        ]);

        $raw = $this->toUtf8((string) file_get_contents($request->file('file')->getRealPath()));

        $firstLine = (string) strtok($raw, "\r\n");
        $counts = [
            ','  => substr_count($firstLine, ','),
            ';'  => substr_count($firstLine, ';'),
            "\t" => substr_count($firstLine, "\t"),
        ];
        arsort($counts);
        $delimiter = array_key_first($counts);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $raw);
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter) ?: [];
        $header = array_map(function ($h) {
            $key = Str::slug((string) $h, '_');

            return self::HEADER_ALIASES[$key] ?? $key;
        }, $header);

        $hasName = in_array('name', $header, true) || in_array('first_name', $header, true);
        $hasContact = in_array('phone', $header, true) || in_array('email', $header, true);

        if (!$hasName || !$hasContact) {
            fclose($handle);

            return back()->withErrors([
                'file' => 'Colonnes introuvables : il faut au minimum « name » et « phone » ou « email ». '
                    . 'Colonnes détectées : ' . (implode(', ', array_filter($header)) ?: 'aucune'),
            ]);
        }

        $created = 0;
        $updated = 0;
        $skippedLines = [];
        $line = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;

            // ligne vide (ou ",,,,")
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            // plus de colonnes que l'en-tête : fichier mal formé
            if (count($row) > count($header)) {
                $skippedLines[] = $line;
                continue;
            }

            // moins de colonnes (Excel supprime les vides de fin) : on complète
            $row = array_pad($row, count($header), '');
            $data = array_combine($header, array_map(fn ($v) => trim((string) $v), $row));

            $name = $data['name'] ?? '';
            if ($name === '') {
                $name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
            }
            $name = mb_substr($name, 0, 120);

            $phone = Contact::normalizePhone($data['phone'] ?? null);
            $email = strtolower($data['email'] ?? '');
            $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;

            if ($name === '' || (!$phone && !$email)) {
                $skippedLines[] = $line;
                continue;
            }

            $source = $data['source'] ?? '';
            $status = $data['status'] ?? '';
            $city = $data['city'] ?? '';

            // Seules les valeurs renseignées sont envoyées : le vide n'écrase rien
            $payload = array_filter([
                'name'   => $name,
                'phone'  => $phone,
                'email'  => $email,
                'city'   => $city !== '' ? mb_substr($city, 0, 100) : null,
                'source' => array_key_exists($source, Contact::SOURCES) ? $source : null,
                'status' => array_key_exists($status, Contact::STATUSES) ? $status : null,
            ], fn ($v) => $v !== null && $v !== '');

            try {
                $contact = Contact::query()
                    ->where(function ($q) use ($phone, $email) {
                        if ($phone) {
                            $q->orWhere('phone', $phone);
                        }
                        if ($email) {
                            $q->orWhere('email', $email);
                        }
                    })
                    ->first();

                if ($contact) {
                    if ($contact->status === 'client') {
                        unset($payload['status']); // ne jamais rétrograder un client
                    }
                    $contact->update($payload);
                    $updated++;
                } else {
                    Contact::create($payload + ['source' => 'other', 'status' => 'prospect']);
                    $created++;
                }
            } catch (QueryException $e) {
                $skippedLines[] = $line;
            }
        }
        fclose($handle);

        $message = "Import terminé : {$created} créés, {$updated} mis à jour, " . count($skippedLines) . ' ignorés';
        if ($skippedLines) {
            $message .= ' (lignes ' . implode(', ', array_slice($skippedLines, 0, 10))
                . (count($skippedLines) > 10 ? '…' : '') . ')';
        }

        return redirect()->route('contacts.index')->with('success', $message . '.');
    }

    /** Convertit le contenu du fichier en UTF-8 (UTF-16 Facebook, ANSI Excel) et retire le BOM. */
    private function toUtf8(string $raw): string
    {
        if (str_starts_with($raw, "\xFF\xFE")) {
            $raw = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($raw, "\xFE\xFF")) {
            $raw = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
        } elseif (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        return (string) preg_replace('/^\xEF\xBB\xBF/', '', $raw);
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