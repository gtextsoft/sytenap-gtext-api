<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    use HasFactory;

    //agent_id is the user_id of the agent registered
    protected $fillable = ['user_id', 'first_name', 'last_name','email', 'referral_code', 'account_type'];

   
}
