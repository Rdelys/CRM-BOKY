<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageLog extends Model
{
    protected $fillable = ['campaign_id', 'contact_id', 'channel', 'status', 'error', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}