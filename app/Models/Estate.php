<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estate extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'town_or_city',
        'state',
        'cordinates',
        'zoning',
        'size',
        'direction',
        'description',
        'map_background_image',
        'preview_display_image',
        'has_cerificate_of_occupancy',
        'amenities',
        'rating',
        'status',
    ];

    protected $casts = [
        'has_cerificate_of_occupancy' => 'boolean',
        'amenities' => 'array',
        'rating' => 'integer',
    ];

    public function media()
    {
       return $this->hasOne(EstateMedia::class);
    }

   
}
