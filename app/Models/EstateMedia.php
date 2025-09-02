<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstateMedia extends Model
{
    use HasFactory;

    protected $table = 'estate_media';

    protected $fillable = [
        'estate_id',
        'photos',
        'third_dimension_model_images',
        'third_dimension_model_video',
        'virtual_tour_video_url',
        'status',
    ];

    protected $casts = [
        'photos' => 'array',
        'third_dimension_model_images' => 'array',
    ];

    public function estate()
    {
        return $this->belongsTo(Estate::class);
    }
}
