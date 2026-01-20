<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'estate_admin',
    ];

    protected $casts = [
        'has_cerificate_of_occupancy' => 'boolean',
        'amenities' => 'array',
        'rating' => 'integer',
        'estate_admin' => 'array',
    ];

    public function media()
    {
       return $this->hasOne(EstateMedia::class);
    }

    /**
     * Get the plot detail for the estate.
     */
    public function plotDetail(): HasOne
    {
        return $this->hasOne(EstatePlotDetail::class);
    }

    /**
     * Get all plot details for the estate (if an estate can have multiple plot detail records).
     * Use this if you plan to have multiple plot detail records per estate
     */
    public function plotDetails(): HasMany
    {
        return $this->hasMany(EstatePlotDetail::class);
    }

    /**
     * Scope to get estates with available plots
     */
    public function scopeWithAvailablePlots($query)
    {
        return $query->whereHas('plotDetail', function ($q) {
            $q->where('available_plot', '>', 0);
        });
    }

    /**
     * Scope to get estates with promotions
     */
    public function scopeWithPromotions($query)
    {
        return $query->whereHas('plotDetail', function ($q) {
            $q->whereNotNull('promotion_price');
        });
    }

    /**
     * Get total available plots across all plot details
     */
    public function getTotalAvailablePlotsAttribute()
    {
        return $this->plotDetails()->sum('available_plot');
    }

    /**
     * Get minimum price per plot
     */
    public function getMinimumPriceAttribute()
    {
        return $this->plotDetails()->min('price_per_plot');
    }

    /**
     * Get maximum price per plot
     */
    public function getMaximumPriceAttribute()
    {
        return $this->plotDetails()->max('price_per_plot');
    }

    public function plots()
    {
        return $this->hasMany(Plot::class, 'estate_id');
    }


   
}
