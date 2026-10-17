<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Envoie un SMS. Lance une exception en cas d'échec.
     *
     * Driver "log"  : n'envoie rien, écrit dans storage/logs/laravel.log (pour tester).
     * Driver "http" : appelle la passerelle SMS. ADAPTER le bloc ci-dessous
     *                 selon la documentation du fournisseur choisi par le client.
     */
    public function send(string $to, string $message): void
    {
        $cfg = config('crm.sms');

        if ($cfg['driver'] === 'log') {
            Log::info("[SMS TEST] à {$to} : {$message}");
            return;
        }

        // ---- À ADAPTER selon le fournisseur ----
        $response = Http::withToken($cfg['key'])
            ->timeout(15)
            ->post($cfg['url'], [
                'to'      => $to,
                'from'    => $cfg['sender'],
                'message' => $message,
            ]);
        // ----------------------------------------

        if ($response->failed()) {
            throw new \RuntimeException('SMS refusé : HTTP ' . $response->status() . ' ' . $response->body());
        }
    }
}
