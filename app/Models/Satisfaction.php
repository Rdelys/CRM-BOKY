<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Satisfaction extends Model
{
    protected $fillable = ['nom', 'livre', 'note', 'commentaire', 'source'];

    public const NOTES = [
        1 => ['emoji' => '😡', 'label' => 'Très déçu'],
        2 => ['emoji' => '😕', 'label' => 'Déçu'],
        3 => ['emoji' => '😐', 'label' => 'Moyen'],
        4 => ['emoji' => '🙂', 'label' => 'Satisfait'],
        5 => ['emoji' => '😍', 'label' => 'Adoré'],
    ];

    // Sources proposées pour générer les liens (n'importe quel autre ?src=xxx fonctionne aussi)
    public const SOURCES = [
        'whatsapp'  => 'WhatsApp',
        'sms'       => 'SMS',
        'messenger' => 'Messenger',
        'facebook'  => 'Facebook',
        'email'     => 'Email',
        'direct'    => 'Direct / autre',
    ];
}