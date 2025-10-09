<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlotPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'estate_id',
        'user_id',
        'plots',
        'total_price',
        'installment_months',
        'monthly_payment',
        'payment_schedule',
        'payment_reference',
        'payment_link',
        'payment_status',
        'acquisition_status',

    ];

    protected $casts = [
        'plots' => 'array',
        'payment_schedule' => 'array',
        'payment_verified_at' => 'datetime',
    ];

    public function estate()
    {
        return $this->belongsTo(Estate::class);
    }

    public function plot()
    {
        return $this->belongsTo(Plot::class);
    }

   

}
