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

    /** "Rakoto Jean" => "Rakoto J." (affichage public) */
    public function getNomPublicAttribute(): string
    {
        $parts = preg_split('/\s+/u', trim((string) $this->nom), -1, PREG_SPLIT_NO_EMPTY);

        if (! $parts) {
            return 'Client';
        }
        if (count($parts) === 1) {
            return $parts[0];
        }

        $last = end($parts);

        return $parts[0] . ' ' . mb_strtoupper(mb_substr($last, 0, 1)) . '.';
    }

    /** "Rakoto Jean" => "RJ" */
    public function getInitialesAttribute(): string
    {
        $parts = preg_split('/\s+/u', trim((string) $this->nom), -1, PREG_SPLIT_NO_EMPTY);

        if (! $parts) {
            return '?';
        }

        $i = mb_substr($parts[0], 0, 1);
        if (count($parts) > 1) {
            $i .= mb_substr(end($parts), 0, 1);
        }

        return mb_strtoupper($i);
    }
}