<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZohoCredential extends Model
{
    protected $fillable = [
        'refresh_token',
        'access_token',
        'expires_in',
    ];
}
