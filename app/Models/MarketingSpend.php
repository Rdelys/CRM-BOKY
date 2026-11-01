<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingSpend extends Model
{
    const CHANNELS = [
        'facebook' => 'Facebook',
        'sms'      => 'SMS',
        'email'    => 'Email',
        'other'    => 'Autre',
    ];

    protected $fillable = ['spent_on', 'channel', 'amount', 'label'];

    protected $casts = ['spent_on' => 'date', 'amount' => 'integer'];
}