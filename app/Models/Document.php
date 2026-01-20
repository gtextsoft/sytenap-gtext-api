<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{

    protected $fillable = [
        'uploaded_by',
        'user_id',
        'plot_id',
        'estate_id',
        'title',
        'document_type',
        'file_url',
        'comment',
        'public_id',
        'extension',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function plot()
    {
        return $this->belongsTo(Plot::class);
    }

    public function estate()
    {
        return $this->belongsTo(Estate::class);
    }
}
