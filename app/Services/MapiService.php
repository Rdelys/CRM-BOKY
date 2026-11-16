<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MapiService
{
    /**
     * Normalise un numéro malgache au format local à 10 chiffres (0340000000).
     * Accepte +261 34 00 000 00, 261340000000, 340000000, etc. Retourne null si invalide.
     */
    public static function normalize(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $d = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($d, '00261')) {
            $d = substr($d, 2);
        }
        if (str_starts_with($d, '261') && strlen($d) === 12) {
            $d = '0' . substr($d, 3);
        } elseif (strlen($d) === 9 && str_starts_with($d, '3')) {
            $d = '0' . $d;
        }

        return preg_match('/^03\d{8}$/', $d) ? $d : null;
    }

    /** 0340012345 → 034 00 123 45 */
    public static function format(string $n): string
    {
        return trim(substr($n, 0, 3) . ' ' . substr($n, 3, 2) . ' ' . substr($n, 5, 3) . ' ' . substr($n, 8, 2));
    }

    /** Estimation du nombre de SMS consommés (160 car. en GSM, 70 si accents spéciaux/emoji). */
    public static function countSms(string $text): int
    {
        $len = mb_strlen($text);
        if ($len === 0) {
            return 0;
        }
        $unicode = (bool) preg_match('/[^\x20-\x7E\n\r€£¥èéùìòÇØøÅåÆæßÉÄÖÑÜ§¿äöñüà]/u', $text);
        $single  = $unicode ? 70 : 160;
        $multi   = $unicode ? 67 : 153;

        return $len <= $single ? 1 : (int) ceil($len / $multi);
    }

    /** @return array{ok: bool, error: ?string} */
    public function send(string $phone, string $message): array
    {
        $key = config('services.mapi.key');
        if (! $key) {
            return ['ok' => false, 'error' => 'Clé API Mapi non configurée (MAPI_API_KEY).'];
        }

        try {
            $res = Http::timeout(20)
                ->withHeaders(['Token' => $key])
                ->asMultipart()
                ->post($this->url('/msg/send'), [
                    'Recipient' => $phone,
                    'Message'   => $message,
                    'Channel'   => 'sms',
                ]);
        } catch (Throwable $e) {
            Log::error('Mapi injoignable : ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Mapi injoignable, réessayez dans un instant.'];
        }

        if ($res->successful() && $res->json('status') === true) {
            return ['ok' => true, 'error' => null];
        }

        Log::warning('Mapi SMS refusé', ['status' => $res->status(), 'body' => Str::limit($res->body(), 300)]);

        $error = match ($res->status()) {
            401 => 'Clé API Mapi invalide ou remplacée.',
            403 => 'Compte Mapi désactivé, contactez le support Mapi.',
            409 => 'Numéro invalide ou solde Mapi épuisé.',
            422 => 'Requête refusée par Mapi (paramètre invalide).',
            default => 'Erreur Mapi (' . $res->status() . ').',
        };

        return ['ok' => false, 'error' => $error];
    }

    /** Unités SMS restantes (null si indisponible). */
    public function balance(): ?int
    {
        $key = config('services.mapi.key');
        if (! $key) {
            return null;
        }

        try {
            $res = Http::timeout(10)->withHeaders(['Token' => $key])->get($this->url('/smsoffer/available'));

            return $res->successful() ? (int) $res->json('available_sms') : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function url(string $path): string
    {
        return rtrim(config('services.mapi.url'), '/') . $path;
    }
}