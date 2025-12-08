<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'referral_code'];

 public function agent()
{
    // the agent who referred someone
    return $this->belongsTo(User::class, 'user_id');
}

public function referredUser()
{
    // the person that was referred
    return $this->belongsTo(User::class, 'referred_user_id');
}
   
}
