<?php

namespace App\Models;

use DateTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pusat_Lokasi extends Model
{
    use HasFactory;

    protected $table = 'pusat_lokasis';   
    protected $fillable=[
        'nama_lokasi',
        'titik_koordinat',
        'keterangan_lokasi',
    ];

    protected $casts = ['created_at' => 'datetime', 'updated_at' => 'datetime'];

}
