<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Absensi extends Model
{
    use HasFactory;
    protected $table = 'absensis';
    protected $fillable = [
        'user_id',
        'lokasi_id',
        'titik_koordinat_lokasi',
        'titik_koordinat_kamu',
        'foto_wajah',
        'tipe_absen',
        'waktu_absen'
    ];

    protected $casts = [
        'waktu_absen'
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function lokasi(){
        return $this->belongsTo(Lokasi::class);
    }


}
