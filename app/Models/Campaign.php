<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $fillable = [
        'name', 'channel', 'subject', 'message',
        'segment_status', 'segment_source',
        'status', 'sent_count', 'failed_count', 'sent_at',
    ];

    protected $casts = ['sent_at' => 'datetime'];

    /** Contacts ciblés par cette campagne (hors désinscrits). */
    public function recipients(): Builder
    {
        $q = Contact::query()->where('unsubscribed', false);

        if ($this->channel === 'sms') {
            $q->whereNotNull('phone');
        } else {
            $q->whereNotNull('email');
        }

        if ($this->segment_status) {
            $q->where('status', $this->segment_status);
        }
        if ($this->segment_source) {
            $q->where('source', $this->segment_source);
        }

        return $q;
    }

    public function logs()
    {
        return $this->hasMany(MessageLog::class);
    }
}
