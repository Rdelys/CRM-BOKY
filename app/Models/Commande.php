<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class Commande extends Model
{
    protected $table = 'commandes';

    const STATUTS = [
        'confirmee'            => 'Confirmée',
        'en_attente_livraison' => 'En attente de livraison',
        'annulee'              => 'Annulée',
        'livree'               => 'Livrée',
        'reportee'             => 'Reportée',
    ];

    /** Commandes pas encore livrées et non annulées. */
    const STATUTS_A_LIVRER = ['confirmee', 'en_attente_livraison', 'reportee'];

    const COULEURS = [
        'confirmee'            => '#2563eb',
        'en_attente_livraison' => '#f59e0b',
        'annulee'              => '#ef4444',
        'livree'               => '#10b981',
        'reportee'             => '#8b5cf6',
    ];

    const PAIEMENTS = ['En espèce', 'Mvola', 'Orange Money', 'Airtel Money', 'Virement bancaire'];

    protected $fillable = [
        'contact_id', 'date_commande', 'adresse_livraison', 'livres', 'mode_paiement',
        'date_livraison', 'heure_livraison', 'precision_heure', 'responsable',
        'montant_total', 'statut', 'notes',
    ];

    protected $casts = [
        'date_commande'  => 'datetime',
        'date_livraison' => 'date',
        'montant_total'  => 'integer',
    ];

    protected static function booted(): void
    {
        // Le statut du contact suit automatiquement ses commandes
        static::saved(function (Commande $commande) {
            Contact::find($commande->contact_id)?->synchroniserStatutClient();
        });

        static::deleted(function (Commande $commande) {
            Contact::find($commande->contact_id)?->synchroniserStatutClient();
        });
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    /** Sépare « Livre A / Livre B » (retour à la ligne, /, ;, +, |) en liste de titres. */
    public static function decouperLivres(?string $texte): array
    {
        $morceaux = preg_split('/[\r\n;+|\/]+/u', (string) $texte) ?: [];

        $titres = array_map(
            fn ($p) => (string) preg_replace('/^[\s\-,.]+|[\s\-,.]+$/u', '', $p),
            $morceaux
        );

        return array_values(array_filter($titres, fn ($t) => $t !== ''));
    }

    /** @return array<int, array{titre: string, quantite: int}> */
    public static function topLivres(?CarbonInterface $depuis = null, int $limite = 5): array
    {
        $requete = static::query()
            ->where('statut', '!=', 'annulee')
            ->whereNotNull('livres');

        if ($depuis) {
            $requete->where('date_commande', '>=', $depuis);
        }

        $compte = [];
        $noms = [];

        foreach ($requete->pluck('livres') as $texte) {
            foreach (self::decouperLivres($texte) as $titre) {
                $cle = mb_strtolower($titre);
                $compte[$cle] = ($compte[$cle] ?? 0) + 1;
                $noms[$cle] ??= $titre;
            }
        }

        arsort($compte);

        $top = [];
        foreach (array_slice($compte, 0, $limite, true) as $cle => $quantite) {
            $top[] = ['titre' => $noms[$cle], 'quantite' => $quantite];
        }

        return $top;
    }
}