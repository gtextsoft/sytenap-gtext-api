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
    ];

    protected $casts = [
        'plots' => 'array',
        'payment_schedule' => 'array',
    ];

    public function estate()
    {
        return $this->belongsTo(Estate::class);
    }
}
