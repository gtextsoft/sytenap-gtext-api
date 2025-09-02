<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstatePlotDetail extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'estate_plot_details';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'estate_id',
        'available_plot',
        'available_acre',
        'price_per_plot',
        'percentage_increase',
        'installment_plan',
        'promotion_price',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'available_plot' => 'integer',
        'available_acre' => 'decimal:2',
        'price_per_plot' => 'decimal:2',
        'percentage_increase' => 'decimal:2',
        'installment_plan' => 'array',
        'promotion_price' => 'decimal:2',
    ];

    /**
     * Get the estate that owns the plot detail.
     */
    public function estate(): BelongsTo
    {
        return $this->belongsTo(Estate::class);
    }

    /**
     * Scope to filter by available plots
     */
    public function scopeHasAvailablePlots($query)
    {
        return $query->where('available_plot', '>', 0);
    }

    /**
     * Scope to filter by estate
     */
    public function scopeByEstate($query, $estateId)
    {
        return $query->where('estate_id', $estateId);
    }

    /**
     * Get the effective price (promotion price if available, otherwise regular price)
     */
    public function getEffectivePriceAttribute()
    {
        return $this->promotion_price ?? $this->price_per_plot;
    }

    /**
     * Get formatted price per plot
     */
    public function getFormattedPriceAttribute()
    {
        return number_format($this->price_per_plot, 2);
    }

    /**
     * Get formatted promotion price
     */
    public function getFormattedPromotionPriceAttribute()
    {
        return $this->promotion_price ? number_format($this->promotion_price, 2) : null;
    }

    /**
     * Check if plot has promotion
     */
    public function getHasPromotionAttribute()
    {
        return !is_null($this->promotion_price) && $this->promotion_price < $this->price_per_plot;
    }

    /**
     * Get savings amount if promotion is active
     */
    public function getSavingsAmountAttribute()
    {
        if ($this->has_promotion) {
            return $this->price_per_plot - $this->promotion_price;
        }
        return 0;
    }

    /**
     * Get total value of all available plots
     */
    public function getTotalPlotValueAttribute()
    {
        return $this->available_plot * $this->effective_price;
    }

    //model property usage
    // Get estate with plot details
    // $estate = Estate::with('plotDetail')->find(1);

    // // Get available plot count
    // $availablePlots = $estate->plotDetail->available_plot;

    // // Get effective price (with promotion consideration)
    // $price = $estate->plotDetail->effective_price;

    // // Get estates with available plots
    // $estatesWithPlots = Estate::withAvailablePlots()->get();

    // // Get plot details for specific estate
    // $plotDetails = EstatePlotDetail::byEstate(1)->first();
}