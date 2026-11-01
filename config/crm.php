<?php

return [
    // Jeton secret pour le formulaire du siteweb (POST /leads)
    'lead_token' => env('LEAD_API_TOKEN'),

    // Synchronisation des acheteurs depuis le siteweb
    'website' => [
        'url'   => env('WEBSITE_API_URL'),
        'token' => env('WEBSITE_API_TOKEN'),
    ],

    // SMS : "log" (test) ou "http" (vraie passerelle)
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'url'    => env('SMS_API_URL'),
        'key'    => env('SMS_API_KEY'),
        'sender' => env('SMS_SENDER', 'CRM'),
    ],

    'facebook' => [
        'verify_token' => env('FB_VERIFY_TOKEN'),
        'app_secret'   => env('FB_APP_SECRET'),
        'page_token'   => env('FB_PAGE_TOKEN'),
        'page_id'      => env('FB_PAGE_ID'),
        'graph'        => 'https://graph.facebook.com/v21.0',

        // Dépenses publicitaires (API Marketing) : boosts et publicités
        // Le token doit avoir la permission ads_read (token utilisateur système conseillé).
        'ads_token'     => env('FB_ADS_TOKEN'),
        'ad_account_id' => env('FB_AD_ACCOUNT_ID'),   // avec ou sans le préfixe « act_ »
        // Multiplicateur vers l'Ariary : 1 si le compte publicitaire est déjà en MGA,
        // sinon par ex. 4500 si le compte est en USD (1 USD ≈ 4 500 Ar).
        'ads_rate'      => (float) env('FB_ADS_RATE', 1),

        // Métriques de visibilité (Page Insights)
        'insight_metrics' => [
            'views'       => env('FB_METRIC_VIEWS', 'page_media_view'),
            'reach'       => env('FB_METRIC_REACH', 'page_total_media_view_unique'),
            'engagements' => env('FB_METRIC_ENGAGEMENTS', 'page_post_engagements'),
            'follows'     => env('FB_METRIC_FOLLOWS', 'page_daily_follows'),
        ],
    ],
];