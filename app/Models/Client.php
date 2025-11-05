<?php

namespace App\Models;

use App\Models\User;
use App\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Client extends Model {
    use HasFactory;

    protected $fillable = [
        'user_id', 'first_name', 'last_name', 'email', 'phone'
    ];

    public function user() {
        return $this->belongsTo( User::class );
    }

    public function documents() {
        return $this->hasMany( Document::class );
    }
}
