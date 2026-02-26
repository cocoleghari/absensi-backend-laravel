<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lokasi extends Model
{
    use HasFactory;

    protected $filable=[
        'user_id',
        'lokasi',
        'koordinat'
    ];

    public function user(){
        return $this->belongsTo(User::class);

    }

    public function absensis(){
        return $this->hasMany(Absensi::class);
    }
}
