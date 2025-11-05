<?php

namespace App\Models;

use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Document extends Model {
    use HasFactory;

    protected $fillable = [
        'client_id', 'title', 'file_url'
    ];

    public function client() {
        return $this->belongsTo( Client::class );
    }
}
