<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerProperty extends Model
{
    //

    protected $fillable = [
        'user_id',
        'estate_id',
        'plots',
        'total_price',
        'installment_months',
        'payment_status',
        'acquisition_status',
        'payment_verified_at',
    ];

    protected $casts = [
        'plots' => 'array',
        'payment_verified_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function estate()
    {
        return $this->belongsTo(Estate::class);
    }

}
