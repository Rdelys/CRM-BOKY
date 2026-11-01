<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacebookInsight extends Model
{
    protected $fillable = ['date', 'views', 'reach', 'engagements', 'follows'];

    protected $casts = ['date' => 'date'];
}