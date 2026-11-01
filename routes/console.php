<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Synchro des acheteurs du siteweb
Schedule::command('crm:sync-buyers')->everyFifteenMinutes()->withoutOverlapping();

// Lance les campagnes programmées dont l'heure est arrivée
Schedule::command('campaigns:dispatch-due')->everyMinute()->withoutOverlapping();

// Récupère les nouvelles personnes qui ont écrit à la Page Facebook
Schedule::command('crm:sync-facebook')->everyFifteenMinutes()->withoutOverlapping();

// Visibilité de la Page Facebook (vues, personnes atteintes, interactions, abonnés)
Schedule::command('crm:sync-fb-insights --days=3')->hourly()->withoutOverlapping();

// Dépenses publicitaires (boosts) du compte publicitaire Facebook, pour le CAC et le ROI
Schedule::command('crm:sync-fb-spend --days=7')->hourly()->withoutOverlapping();