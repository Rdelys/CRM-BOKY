<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FacebookService
{
    /** Message dans la fenêtre des 24 h (messaging_type RESPONSE). */
    public function send(string $psid, string $text): void
    {
        $cfg = config('crm.facebook');

        $response = Http::timeout(15)
            ->withToken($cfg['page_token'])
            ->post("{$cfg['graph']}/me/messages", [
                'messaging_type' => 'RESPONSE',
                'recipient'      => ['id' => $psid],
                'message'        => ['text' => $text],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Messenger refusé : HTTP ' . $response->status() . ' ' . $response->body());
        }
    }

    /** Nom du contact à partir de son PSID. */
    public function profileName(string $psid): ?string
    {
        $cfg = config('crm.facebook');

        $r = Http::timeout(15)->withToken($cfg['page_token'])
            ->get("{$cfg['graph']}/{$psid}", ['fields' => 'first_name,last_name']);

        if ($r->failed()) {
            return null;
        }

        $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));

        return $name !== '' ? $name : null;
    }
}