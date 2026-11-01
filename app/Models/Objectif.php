<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Objectif extends Model
{
    protected $fillable = ['metric', 'period', 'ref', 'target'];

    protected $casts = ['target' => 'float'];

    /**
     * Tous les indicateurs du tableau de bord pour lesquels on peut fixer un objectif.
     *
     * type        : count | money | rate
     * cumulative  : la valeur grandit au fil de la période (CA, commandes…) → on compare au rythme attendu
     * inverse     : plus bas = mieux (CAC, dépenses) → l'objectif est un plafond à ne pas dépasser
     */
    const METRICS = [
        // Ventes
        'ca'              => ['group' => 'Ventes',     'label' => "Chiffre d'affaires",      'type' => 'money', 'cumulative' => true],
        'commandes'       => ['group' => 'Ventes',     'label' => 'Commandes',               'type' => 'count', 'cumulative' => true],
        'livrees'         => ['group' => 'Ventes',     'label' => 'Commandes livrées',       'type' => 'count', 'cumulative' => true],
        'panier'          => ['group' => 'Ventes',     'label' => 'Panier moyen',            'type' => 'money', 'cumulative' => false],

        // Clients & contacts
        'nouveaux_contacts' => ['group' => 'Clients',  'label' => 'Nouveaux contacts',       'type' => 'count', 'cumulative' => true],
        'nouveaux_clients'  => ['group' => 'Clients',  'label' => 'Nouveaux clients',        'type' => 'count', 'cumulative' => true],
        'conversion'      => ['group' => 'Clients',    'label' => 'Taux de conversion',      'type' => 'rate',  'cumulative' => false, 'decimals' => 1],

        // Marketing
        'views'           => ['group' => 'Marketing',  'label' => 'Vues Facebook',           'type' => 'count', 'cumulative' => true],
        'reach'           => ['group' => 'Marketing',  'label' => 'Personnes atteintes',     'type' => 'count', 'cumulative' => true],
        'engagements'     => ['group' => 'Marketing',  'label' => 'Interactions Facebook',   'type' => 'count', 'cumulative' => true],
        'follows'         => ['group' => 'Marketing',  'label' => 'Nouveaux abonnés',        'type' => 'count', 'cumulative' => true],
        'prospection'     => ['group' => 'Marketing',  'label' => 'Taux de prospection',     'type' => 'rate',  'cumulative' => false, 'decimals' => 2],
        'roi'             => ['group' => 'Marketing',  'label' => 'ROI Facebook',            'type' => 'rate',  'cumulative' => false, 'decimals' => 1],
        'cac'             => ['group' => 'Marketing',  'label' => "CAC (coût d'acquisition)", 'type' => 'money', 'cumulative' => false, 'inverse' => true],
        'depenses'        => ['group' => 'Marketing',  'label' => 'Dépenses marketing (budget max)', 'type' => 'money', 'cumulative' => true, 'inverse' => true],

        // Campagnes
        'emails'          => ['group' => 'Campagnes',  'label' => 'Emails envoyés',          'type' => 'count', 'cumulative' => true],
        'sms'             => ['group' => 'Campagnes',  'label' => 'SMS envoyés',             'type' => 'count', 'cumulative' => true],
        'messenger'       => ['group' => 'Campagnes',  'label' => 'Messages Messenger envoyés', 'type' => 'count', 'cumulative' => true],
    ];
}