<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HubspotContactBuffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'hubspot_id',
        'data'
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
