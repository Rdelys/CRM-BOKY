<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'name', 'phone', 'email', 'source', 'status', 'city', 'notes', 'unsubscribed',
        'website_user_id', 'interests', 'registered_at',
    ];

    protected $casts = [
        'unsubscribed' => 'boolean',
        'registered_at' => 'datetime',
    ];

    const STATUSES = [
        'prospect'  => 'Prospect',
        'contacted' => 'Contacté',
        'client'    => 'Client',
        'lost'      => 'Perdu',
    ];

    const SOURCES = [
        'website'  => 'Siteweb',
        'facebook' => 'Facebook',
        'whatsapp' => 'WhatsApp',
        'direct'   => 'Direct',
        'other'    => 'Autre',
    ];

    /**
     * Normalise les numéros malgaches : 034 12 345 67 => +261341234567
     * Gère aussi "p:+261…" (Facebook) et "341234567" (0 supprimé par Excel).
     */
    public static function normalizePhone(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $p = preg_replace('/[^\d+]/', '', $value);

        if ($p === '' || $p === '+') {
            return null;
        }

        if (str_starts_with($p, '00')) {
            $p = '+' . substr($p, 2);
        } elseif (str_starts_with($p, '+')) {
            // déjà au format international
        } elseif (str_starts_with($p, '261')) {
            $p = '+' . $p;
        } elseif (str_starts_with($p, '0')) {
            $p = '+261' . substr($p, 1);
        } elseif (strlen($p) === 9 && $p[0] === '3') {
            $p = '+261' . $p; // 341234567 : le 0 a été supprimé par Excel
        }

        return $p;
    }

    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = self::normalizePhone($value);
    }

    public function logs()
    {
        return $this->hasMany(MessageLog::class);
    }
}