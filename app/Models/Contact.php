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

    /** Normalise les numéros malgaches : 034 12 345 67 => +261341234567 */
    public static function normalizePhone(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $p = preg_replace('/[^\d+]/', '', $value);

        if ($p === '') {
            return null;
        }
        if (str_starts_with($p, '00')) {
            $p = '+' . substr($p, 2);
        } elseif (str_starts_with($p, '0')) {
            $p = '+261' . substr($p, 1);
        } elseif (str_starts_with($p, '261')) {
            $p = '+' . $p;
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
