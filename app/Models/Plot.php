<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plot extends Model
{
    use HasFactory;

    protected $fillable = [
        'estate_id',
        'plot_id',
        'coordinate',
        'status',
    ];


    protected $hidden = [
        'geom', // hide the geometry column
    ];
    public function estate()
    {
        return $this->belongsTo(Estate::class);
    }
}
