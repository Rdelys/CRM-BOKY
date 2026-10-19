<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Contact;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CommandeController extends Controller
{
    private const CHAMPS = [
        'date_commande', 'adresse_livraison', 'livres', 'mode_paiement', 'date_livraison',
        'heure_livraison', 'precision_heure', 'responsable', 'montant_total', 'statut', 'notes',
    ];

    /** En-têtes du fichier Excel / Google Forms => colonne du CRM (comparaison exacte). */
    private const ALIAS_EXACT = [
        'horodateur' => 'date_commande', 'timestamp' => 'date_commande', 'date_commande' => 'date_commande', 'date' => 'date_commande',
        'anarana' => 'nom', 'nom' => 'nom', 'name' => 'nom', 'full_name' => 'nom', 'nom_complet' => 'nom', 'client' => 'nom',
        'laharana_finday' => 'telephone', 'telephone' => 'telephone', 'tel' => 'telephone', 'phone' => 'telephone',
        'phone_number' => 'telephone', 'mobile' => 'telephone', 'numero' => 'telephone',
        'email' => 'email', 'e_mail' => 'email', 'mail' => 'email', 'adresse_email' => 'email',
        'ville' => 'ville',
        'adresse_livraison' => 'adresse_livraison', 'adresse' => 'adresse_livraison',
        'livres' => 'livres', 'livre' => 'livres', 'titre' => 'livres', 'titres' => 'livres',
        'paiement' => 'mode_paiement', 'mode_paiement' => 'mode_paiement',
        'date_livraison' => 'date_livraison', 'heure_livraison' => 'heure_livraison',
        'precision_heure' => 'precision_heure', 'precision_de_l_heure' => 'precision_heure',
        'responsable' => 'responsable',
        'montant_total' => 'montant_total', 'montant' => 'montant_total', 'total' => 'montant_total',
        'statut' => 'statut', 'statut_de_la_commande' => 'statut',
        'notes' => 'notes',
    ];

    /** En-têtes longs/tronqués (début de la phrase) => colonne du CRM. */
    private const ALIAS_PREFIXE = [
        'adiresy'       => 'adresse_livraison',
        'titre_ny_boky' => 'livres',
        'ny_daty'       => 'date_livraison',
        'ny_fotoana'    => 'heure_livraison',
        'farito_ny_ora' => 'precision_heure',
        'laharana'      => 'telephone',
        'statut_de'     => 'statut',
    ];

    private const MOIS_FR = [
        'janvier' => 1, 'fevrier' => 2, 'février' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6,
        'juillet' => 7, 'aout' => 8, 'août' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11,
        'decembre' => 12, 'décembre' => 12,
    ];

    /** Dates ambiguës du type 7/8/2025 : true = mois/jour (Google Forms, comme votre fichier), false = jour/mois. */
    private const AMBIGU_MOIS_JOUR = false;

    /* ------------------------------------------------------------------ */
    /*  Liste, création, modification                                      */
    /* ------------------------------------------------------------------ */

    public function index(Request $request)
    {
        $commandes = Commande::query()
            ->with('contact')
            ->when($request->q, function ($q, $terme) {
                $tel = strlen(preg_replace('/\D/', '', $terme)) >= 6 ? Contact::normalizePhone($terme) : null;

                $q->where(function ($w) use ($terme, $tel) {
                    $w->where('livres', 'like', "%{$terme}%")
                        ->orWhere('responsable', 'like', "%{$terme}%")
                        ->orWhere('adresse_livraison', 'like', "%{$terme}%")
                        ->orWhereHas('contact', function ($c) use ($terme, $tel) {
                            $c->where('name', 'like', "%{$terme}%")
                                ->orWhere('phone', 'like', "%{$terme}%")
                                ->orWhere('email', 'like', "%{$terme}%");
                            if ($tel) {
                                $c->orWhere('phone', 'like', "%{$tel}%");
                            }
                        });
                });
            })
            ->when($request->statut, fn ($q, $v) => $q->where('statut', $v))
            ->when($request->du, fn ($q, $v) => $q->whereDate('date_commande', '>=', $v))
            ->when($request->au, fn ($q, $v) => $q->whereDate('date_commande', '<=', $v))
            ->orderByDesc('date_commande')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $comptes = Commande::query()
            ->selectRaw('statut, COUNT(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut')
            ->all();

        return view('commandes.index', compact('commandes', 'comptes'));
    }

    public function create(Request $request)
    {
        $commande = new Commande(['statut' => 'confirmee', 'date_commande' => now()]);

        $source = $request->filled('depuis') ? Commande::find($request->query('depuis')) : null;
        $contactId = $request->old('contact_id') ?? $request->query('contact_id') ?? $source?->contact_id;
        $contact = $contactId ? Contact::find($contactId) : null;

        if ($source) {
            // « Recommander » : on reprend les informations de la commande précédente
            $commande->fill($source->only(['adresse_livraison', 'livres', 'mode_paiement', 'responsable', 'montant_total']));
        } elseif ($contact) {
            $derniere = $contact->commandes()->latest('date_commande')->first();
            if ($derniere) {
                $commande->fill($derniere->only(['adresse_livraison', 'mode_paiement', 'responsable']));
            } elseif ($contact->city) {
                $commande->adresse_livraison = $contact->city;
            }
        }

        $modeContact = $request->old('mode_contact', 'existant');

        return view('commandes.create', compact('commande', 'contact', 'modeContact'));
    }

    public function store(Request $request)
    {
        $v = $this->valider($request);

        $commande = DB::transaction(function () use ($v) {
            $contact = $this->resoudreContact($v);

            return Commande::create($this->champs($v) + ['contact_id' => $contact->id]);
        });

        return redirect()->route('commandes.index')
            ->with('success', 'Commande enregistrée. ' . $this->messageStatutClient($commande));
    }

    public function edit(Request $request, Commande $commande)
    {
        $contact = Contact::find($request->old('contact_id') ?? $commande->contact_id);
        $modeContact = $request->old('mode_contact', 'existant');

        return view('commandes.edit', compact('commande', 'contact', 'modeContact'));
    }

    public function update(Request $request, Commande $commande)
    {
        $v = $this->valider($request);
        $ancienContactId = (int) $commande->contact_id;

        DB::transaction(function () use ($v, $commande) {
            $contact = $this->resoudreContact($v);

            $donnees = $this->champs($v, $commande);
            $commande->update($donnees + ['contact_id' => $contact->id]);
        });

        // L'ancien client peut ne plus avoir de commande active
        if ($ancienContactId !== (int) $commande->contact_id) {
            Contact::find($ancienContactId)?->where('statut', 'livree');
        }

        return redirect()->route('commandes.index')
            ->with('success', 'Commande mise à jour. ' . $this->messageStatutClient($commande));
    }

    public function destroy(Commande $commande)
    {
        $commande->delete();

        return redirect()->route('commandes.index')->with('success', 'Commande supprimée.');
    }

    /** Changement rapide de statut depuis la liste. */
    public function statut(Request $request, Commande $commande)
    {
        $data = $request->validate(
            ['statut' => ['required', Rule::in(array_keys(Commande::STATUTS))]],
            [],
            ['statut' => 'statut']
        );

        $commande->update($data);

        return back()->with('success', 'Statut mis à jour : ' . Commande::STATUTS[$data['statut']] . '. ' . $this->messageStatutClient($commande));
    }

    /** Recherche de clients (autocomplétion du formulaire). */
    public function rechercheContacts(Request $request): JsonResponse
    {
        $terme = trim((string) $request->query('q'));
        $tel = strlen(preg_replace('/\D/', '', $terme)) >= 6 ? Contact::normalizePhone($terme) : null;

        $contacts = Contact::query()
            ->when($terme !== '', function ($q) use ($terme, $tel) {
                $q->where(function ($w) use ($terme, $tel) {
                    $w->where('name', 'like', "%{$terme}%")
                        ->orWhere('phone', 'like', "%{$terme}%")
                        ->orWhere('email', 'like', "%{$terme}%");
                    if ($tel) {
                        $w->orWhere('phone', 'like', "%{$tel}%");
                    }
                });
            })
            ->when($terme === '', fn ($q) => $q->latest(), fn ($q) => $q->orderBy('name'))
            ->limit(10)
            ->get(['id', 'name', 'phone', 'email', 'status']);

        return response()->json($contacts->map(fn ($c) => [
            'id'     => $c->id,
            'name'   => $c->name,
            'phone'  => $c->phone,
            'email'  => $c->email,
            'status' => Contact::STATUSES[$c->status] ?? $c->status,
        ])->values());
    }

    /* ------------------------------------------------------------------ */
    /*  Classement des clients                                             */
    /* ------------------------------------------------------------------ */

    public function classement(Request $request)
    {
        $periode = in_array($request->query('periode'), ['30', '90', '365'], true) ? $request->query('periode') : 'tout';
        $tri = $request->query('tri') === 'montant' ? 'montant' : 'commandes';
        $depuis = $periode === 'tout' ? null : now()->subDays((int) $periode)->startOfDay();

        $base = fn () => Commande::query()
            ->where('statut', '!=', 'annulee')
            ->when($depuis, fn ($q) => $q->where('date_commande', '>=', $depuis));

        $resume = $base()
            ->selectRaw('COUNT(*) as commandes, COALESCE(SUM(montant_total), 0) as total, COUNT(DISTINCT contact_id) as clients')
            ->first();

        $clients = $base()
            ->selectRaw('contact_id, COUNT(*) as nb_commandes, COALESCE(SUM(montant_total), 0) as total, MAX(date_commande) as derniere')
            ->groupBy('contact_id')
            ->when(
                $tri === 'commandes',
                fn ($q) => $q->orderByDesc('nb_commandes')->orderByDesc('total'),
                fn ($q) => $q->orderByDesc('total')->orderByDesc('nb_commandes')
            )
            ->orderByDesc('derniere')
            ->with('contact')
            ->paginate(20)
            ->withQueryString();

        $livres = Commande::topLivres($depuis, 10);

        return view('commandes.classement', compact('clients', 'resume', 'livres', 'periode', 'tri'));
    }

    /* ------------------------------------------------------------------ */
    /*  Import CSV                                                         */
    /* ------------------------------------------------------------------ */

    public function importForm()
    {
        return view('commandes.import');
    }

    /**
     * Import du fichier de commandes (CSV UTF-8, ANSI Excel ou UTF-16 ; séparateur , ; ou tabulation).
     * - Le client est retrouvé par téléphone ou email, sinon créé.
     * - Une commande déjà importée (même client + même horodateur) est mise à jour, pas dupliquée.
     * - Une cellule vide n'écrase jamais une donnée existante.
     */
    public function import(Request $request)
    {
        $request->validate(
            ['file' => ['required', 'file', 'max:5120', 'extensions:csv,txt']],
            [
                'file.required'   => 'Choisissez un fichier CSV.',
                'file.max'        => 'Le fichier ne doit pas dépasser 5 Mo.',
                'file.extensions' => 'Le fichier doit être au format CSV.',
            ]
        );

        $brut = $this->toUtf8((string) file_get_contents($request->file('file')->getRealPath()));

        $premiereLigne = (string) strtok($brut, "\r\n");
        $comptes = [
            ','  => substr_count($premiereLigne, ','),
            ';'  => substr_count($premiereLigne, ';'),
            "\t" => substr_count($premiereLigne, "\t"),
        ];
        arsort($comptes);
        $separateur = array_key_first($comptes);

        $flux = fopen('php://temp', 'r+');
        fwrite($flux, $brut);
        rewind($flux);

        $entete = fgetcsv($flux, 0, $separateur) ?: [];
        $entete = array_map(fn ($h) => $this->colonne((string) $h), $entete);

        if (!in_array('nom', $entete, true) || (!in_array('telephone', $entete, true) && !in_array('email', $entete, true))) {
            fclose($flux);

            return back()->withErrors([
                'file' => 'Colonnes introuvables : il faut au minimum le nom (« Anarana » ou « Nom ») et le téléphone (« Laharana finday » ou « Téléphone »). '
                    . 'Colonnes détectées : ' . (implode(', ', array_filter($entete)) ?: 'aucune'),
            ]);
        }

        $crees = 0;
        $misAJour = 0;
        $contactsCrees = 0;
        $ignorees = [];
        $ligne = 1;

        while (($row = fgetcsv($flux, 0, $separateur)) !== false) {
            $ligne++;

            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            if (count($row) > count($entete)) {
                $ignorees[] = $ligne;
                continue;
            }

            $row = array_pad($row, count($entete), '');
            $d = array_combine($entete, array_map(fn ($v) => trim((string) $v), $row));

            $nom = mb_substr($d['nom'] ?? '', 0, 120);
            $phone = Contact::normalizePhone($this->premierNumero($d['telephone'] ?? ''));
            $email = strtolower($d['email'] ?? '');
            $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;

            if ($nom === '' || (!$phone && !$email)) {
                $ignorees[] = $ligne;
                continue;
            }

            try {
                DB::transaction(function () use ($d, $nom, $phone, $email, &$crees, &$misAJour, &$contactsCrees) {
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

                    $ville = trim($d['ville'] ?? '');

                    if (!$contact) {
                        $contact = Contact::create([
                            'name'   => $nom,
                            'phone'  => $phone,
                            'email'  => $email,
                            'city'   => $ville !== '' ? mb_substr($ville, 0, 100) : null,
                            'source' => 'other',
                            'status' => 'prospect',
                        ]);
                        $contactsCrees++;
                    } elseif ($ville !== '' && !$contact->city) {
                        $contact->update(['city' => mb_substr($ville, 0, 100)]);
                    }

                    $horodateur = $this->lireDate($d['date_commande'] ?? '');
                    $livraison = $this->lireDate($d['date_livraison'] ?? '');
                    $paiement = trim($d['mode_paiement'] ?? '');
                    $responsable = trim($d['responsable'] ?? '');
                    $precision = trim($d['precision_heure'] ?? '');

                    $attributs = array_filter([
                        'date_commande'     => $horodateur,
                        'adresse_livraison' => $d['adresse_livraison'] ?? null,
                        'livres'            => $d['livres'] ?? null,
                        'mode_paiement'     => $paiement !== '' ? mb_substr($paiement, 0, 60) : null,
                        'date_livraison'    => $livraison?->toDateString(),
                        'heure_livraison'   => $this->lireHeure($d['heure_livraison'] ?? ''),
                        'precision_heure'   => $precision !== '' ? mb_substr($precision, 0, 255) : null,
                        'responsable'       => $responsable !== '' ? mb_substr($responsable, 0, 120) : null,
                        'montant_total'     => $this->lireMontant($d['montant_total'] ?? ''),
                        'statut'            => $this->lireStatut($d['statut'] ?? ''),
                        'notes'             => $d['notes'] ?? null,
                    ], fn ($v) => $v !== null && $v !== '');

                    $requete = Commande::where('contact_id', $contact->id);
                    if ($horodateur) {
                        $requete->where('date_commande', $horodateur);
                    } else {
                        $requete->where('livres', $attributs['livres'] ?? '');
                    }
                    $commande = $requete->first();

                    if ($commande) {
                        $commande->update($attributs);
                        $misAJour++;
                    } else {
                        Commande::create($attributs + [
                            'contact_id'    => $contact->id,
                            'date_commande' => now(),
                            'statut'        => 'confirmee',
                            'montant_total' => 0,
                        ]);
                        $crees++;
                    }
                });
            } catch (QueryException $e) {
                $ignorees[] = $ligne;
            }
        }
        fclose($flux);

        $message = "Import terminé : {$crees} commande(s) créée(s), {$misAJour} mise(s) à jour, "
            . "{$contactsCrees} nouveau(x) contact(s), " . count($ignorees) . ' ligne(s) ignorée(s)';
        if ($ignorees) {
            $message .= ' (lignes ' . implode(', ', array_slice($ignorees, 0, 10)) . (count($ignorees) > 10 ? '…' : '') . ')';
        }

        return redirect()->route('commandes.index')->with('success', $message . '.');
    }

    /* ------------------------------------------------------------------ */
    /*  Outils                                                             */
    /* ------------------------------------------------------------------ */

    private function valider(Request $request): array
    {
        $regles = [
            'mode_contact'      => ['required', Rule::in(['existant', 'nouveau'])],
            'date_commande'     => 'nullable|date',
            'adresse_livraison' => 'nullable|string|max:1000',
            'livres'            => 'required|string|max:2000',
            'mode_paiement'     => 'nullable|string|max:60',
            'date_livraison'    => 'nullable|date',
            'heure_livraison'   => 'nullable|string|max:30',
            'precision_heure'   => 'nullable|string|max:255',
            'responsable'       => 'nullable|string|max:120',
            'montant_total'     => 'nullable|integer|min:0',
            'statut'            => ['required', Rule::in(array_keys(Commande::STATUTS))],
            'notes'             => 'nullable|string|max:2000',
        ];

        if ($request->input('mode_contact') === 'nouveau') {
            $regles += [
                'nouveau_nom'       => 'required|string|max:120',
                'nouveau_telephone' => 'nullable|string|max:30|required_without:nouveau_email',
                'nouveau_email'     => 'nullable|email|max:150|required_without:nouveau_telephone',
                'nouveau_ville'     => 'nullable|string|max:100',
                'nouveau_source'    => ['required', Rule::in(array_keys(Contact::SOURCES))],
            ];
        } else {
            $regles['contact_id'] = 'required|exists:contacts,id';
        }

        return $request->validate($regles, [
            'required'         => 'Le champ « :attribute » est obligatoire.',
            'required_without' => 'Renseignez « :attribute » ou « :values ».',
            'email'            => 'Le champ « :attribute » doit être une adresse email valide.',
            'exists'           => 'Le client sélectionné est introuvable.',
            'in'               => 'La valeur du champ « :attribute » est invalide.',
            'date'             => 'Le champ « :attribute » n\'est pas une date valide.',
            'integer'          => 'Le champ « :attribute » doit être un nombre entier.',
            'min.numeric'      => 'Le champ « :attribute » doit être positif.',
            'max.string'       => 'Le champ « :attribute » ne doit pas dépasser :max caractères.',
        ], [
            'mode_contact'      => 'type de client',
            'contact_id'        => 'client',
            'nouveau_nom'       => 'nom du client',
            'nouveau_telephone' => 'téléphone',
            'nouveau_email'     => 'email',
            'nouveau_ville'     => 'ville',
            'nouveau_source'    => 'origine',
            'date_commande'     => 'horodateur',
            'adresse_livraison' => 'adresse de livraison',
            'livres'            => 'livre(s) commandé(s)',
            'mode_paiement'     => 'paiement',
            'date_livraison'    => 'date de livraison',
            'heure_livraison'   => 'heure de livraison',
            'precision_heure'   => 'précision de l\'heure',
            'responsable'       => 'responsable',
            'montant_total'     => 'montant total',
            'statut'            => 'statut',
            'notes'             => 'notes',
        ]);
    }

    /** Champs de la commande prêts à être enregistrés. */
    private function champs(array $v, ?Commande $existante = null): array
    {
        $c = Arr::only($v, self::CHAMPS);

        if (!empty($c['date_commande'])) {
            $c['date_commande'] = Carbon::parse($c['date_commande']);
        } else {
            $c['date_commande'] = $existante?->date_commande ?? now();
        }

        $c['montant_total'] = (int) ($c['montant_total'] ?? 0);

        return $c;
    }

    /** Client existant, ou nouveau client (réutilise un contact identique si le téléphone/email existe déjà). */
    private function resoudreContact(array $v): Contact
    {
        if ($v['mode_contact'] === 'existant') {
            return Contact::findOrFail($v['contact_id']);
        }

        $phone = Contact::normalizePhone($v['nouveau_telephone'] ?? null);
        $email = !empty($v['nouveau_email']) ? strtolower($v['nouveau_email']) : null;

        if ($phone || $email) {
            $existant = Contact::query()
                ->where(function ($q) use ($phone, $email) {
                    if ($phone) {
                        $q->orWhere('phone', $phone);
                    }
                    if ($email) {
                        $q->orWhere('email', $email);
                    }
                })
                ->first();

            if ($existant) {
                return $existant;
            }
        }

        return Contact::create([
            'name'   => $v['nouveau_nom'],
            'phone'  => $phone,
            'email'  => $email,
            'city'   => $v['nouveau_ville'] ?? null,
            'source' => $v['nouveau_source'] ?? 'direct',
            'status' => 'prospect',
        ]);
    }

    private function messageStatutClient(Commande $commande): string
    {
        $contact = Contact::find($commande->contact_id);

        return $contact
            ? 'Statut de ' . $contact->name . ' : ' . (Contact::STATUSES[$contact->status] ?? $contact->status) . '.'
            : '';
    }

    private function colonne(string $entete): string
    {
        $cle = Str::slug($entete, '_');

        if (isset(self::ALIAS_EXACT[$cle])) {
            return self::ALIAS_EXACT[$cle];
        }

        foreach (self::ALIAS_PREFIXE as $prefixe => $colonne) {
            if (str_starts_with($cle, $prefixe)) {
                return $colonne;
            }
        }

        return $cle;
    }

    /** Convertit le contenu du fichier en UTF-8 (UTF-16, ANSI Excel) et retire le BOM. */
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

    /** « 032 59 760 01 / 034 … » => premier numéro. */
    private function premierNumero(string $valeur): string
    {
        $parts = preg_split('/\s*(?:\/|;|,|\bou\b|\bet\b)\s*/iu', trim($valeur)) ?: [];

        return trim($parts[0] ?? '');
    }

    /** Accepte « 13/07/2025 20:07:33 », « 7/13/2025 20:07:33 », « 14 juillet 2025 », « 2025-07-14 ». */
    private function lireDate(?string $valeur): ?Carbon
    {
        $v = trim(str_replace("\xC2\xA0", ' ', (string) $valeur));
        if ($v === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2})(?:er)?\s+(\p{L}+)\.?\s+(\d{4})(?:\D+(\d{1,2})\s*[:h]\s*(\d{2})(?::(\d{2}))?)?/u', $v, $m)) {
            $mois = self::MOIS_FR[mb_strtolower($m[2])] ?? null;
            if ($mois) {
                return $this->creerDate((int) $m[3], $mois, (int) $m[1], (int) ($m[4] ?? 0), (int) ($m[5] ?? 0), (int) ($m[6] ?? 0));
            }
        }

        if (preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})(?:[ T]+(\d{1,2})\s*[:h]\s*(\d{2})(?::(\d{2}))?)?#', $v, $m)) {
            $a = (int) $m[1];
            $b = (int) $m[2];

            if ($a > 12) {
                [$jour, $mois] = [$a, $b];
            } elseif ($b > 12) {
                [$jour, $mois] = [$b, $a];
            } elseif (self::AMBIGU_MOIS_JOUR) {
                [$jour, $mois] = [$b, $a];
            } else {
                [$jour, $mois] = [$a, $b];
            }

            return $this->creerDate((int) $m[3], $mois, $jour, (int) ($m[4] ?? 0), (int) ($m[5] ?? 0), (int) ($m[6] ?? 0));
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) {
            try {
                return Carbon::parse($v);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function creerDate(int $annee, int $mois, int $jour, int $h = 0, int $i = 0, int $s = 0): ?Carbon
    {
        if (!checkdate($mois, $jour, $annee) || $h > 23 || $i > 59 || $s > 59) {
            return null;
        }

        return Carbon::create($annee, $mois, $jour, $h, $i, $s);
    }

    /** « 15:00 », « 15h », « 15h30 », « 15 » => 15:00 ; sinon le texte tel quel. */
    private function lireHeure(?string $valeur): ?string
    {
        $v = trim((string) $valeur);
        if ($v === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2})\s*[:hH.]\s*(\d{2})?(?::\d{2})?$/', $v, $m) || preg_match('/^(\d{1,2})$/', $v, $m)) {
            $h = (int) $m[1];
            $i = (int) ($m[2] ?? 0);
            if ($h < 24 && $i < 60) {
                return sprintf('%02d:%02d', $h, $i);
            }
        }

        return mb_substr($v, 0, 30);
    }

    /** « 15 000 Ar », « 15.000 », « 15000,50 » => entier en Ariary. */
    private function lireMontant(?string $valeur): ?int
    {
        $s = preg_replace('/[^\d,.]/u', '', trim((string) $valeur));
        if ($s === null || $s === '') {
            return null;
        }

        if (preg_match('/^\d{1,3}([.,]\d{3})+$/', $s)) {
            $s = preg_replace('/[.,]/', '', $s);
        } else {
            $s = str_replace(',', '.', $s);
        }

        return (int) round((float) $s);
    }

    private function lireStatut(?string $valeur): ?string
    {
        $k = Str::slug((string) $valeur, '_');
        if ($k === '') {
            return null;
        }

        return match (true) {
            str_contains($k, 'annul')    => 'annulee',
            str_contains($k, 'report')   => 'reportee',
            str_contains($k, 'attente'), str_contains($k, 'a_livrer') => 'en_attente_livraison',
            str_starts_with($k, 'livr')  => 'livree',
            default                      => 'confirmee',
        };
    }
}