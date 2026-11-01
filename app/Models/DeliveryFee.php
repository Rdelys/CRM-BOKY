<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryFee extends Model
{
    protected $fillable = ['quartier', 'lieu_precis', 'frais', 'note', 'actif'];

    protected $casts = ['actif' => 'boolean', 'frais' => 'integer'];
}