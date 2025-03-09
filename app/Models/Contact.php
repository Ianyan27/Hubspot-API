<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    // Set the primary key to match the HubSpot contact id
    protected $primaryKey = 'contact_id';

    // Disable auto-incrementing since HubSpot provides the id
    public $incrementing = false;

    // Specify the fillable attributes for mass assignment
    protected $fillable = [
        'contact_id',
        'first_name',
        'last_name',
        'email',
        'delete_flag',
    ];
}
