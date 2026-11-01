<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DeliveryShareSetting extends Model
{
    protected $fillable = ['token', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public static function current(): self
    {
        return static::first() ?? static::create(['token' => Str::random(40), 'actif' => true]);
    }
}