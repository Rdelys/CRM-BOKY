<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessage extends Model
{
    protected $fillable = [
        'contact_id', 'user_id', 'nom', 'telephone', 'message',
        'origine', 'statut', 'erreur', 'nb_sms',
    ];

    public const STATUTS = [
        'sent'   => 'Envoyé',
        'failed' => 'Échec',
    ];

    public const ORIGINES = [
        'contacts'   => 'Contacts',
        'classement' => 'Meilleurs clients',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}