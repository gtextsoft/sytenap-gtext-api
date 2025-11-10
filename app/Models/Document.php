<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
use App\Models\Plot;
use App\Models\User;
use App\Models\Estate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Document extends Model {
    use HasFactory;

    protected $fillable = [
        'uploaded_by',
        'user_id',
        'plot_id',
        'estate_id',
        'title',
        'document_type',
        'file_url',
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
    public function uploader() {
        return $this->belongsTo( User::class, 'uploaded_by' );
    }

    public function client() {
        return $this->belongsTo( User::class, 'user_id' );
    }

    public function plot() {
        return $this->belongsTo( Plot::class );
    }

    public function estate() {
        return $this->belongsTo( Estate::class );
    }
}
