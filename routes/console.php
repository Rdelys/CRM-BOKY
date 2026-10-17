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