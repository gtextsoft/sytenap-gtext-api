<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PasswordResetLog extends Model {
    use HasFactory;

    protected $fillable = [
        'client_id',
        'admin_id',
        'reset_at',
    ];

    public function admin() {
        return $this->belongsTo( User::class, 'admin_id' );
    }

    public function client() {
        return $this->belongsTo( User::class, 'client_id' );
    }
}
