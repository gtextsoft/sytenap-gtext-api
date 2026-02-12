<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    //
    protected $fillable = [
        'user_id',
        'invoice_number',
        'amount',
        'payment_status',
    ];

    // Relation to User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
