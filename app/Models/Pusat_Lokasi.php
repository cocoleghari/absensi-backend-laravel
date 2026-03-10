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

    // Mendapatkan latitud dan lngitud
    public function getKoordinatArray(): array
    {
        if(empty($this->titik_koordinat)){
            return [];
        }

        $parts = explode(',', $this->titik_koordinat);

        if (count($parts) !== 2) {
            return [];
        }

        return [
            [
                'lat' => (float) trim($parts[0]),
                'lng' => (float) trim($parts[1]),
            ]
        ];
    }


    // Log Pencarian
    public function scopeSearch($query, $search){
        return $query->where(function($q) use ($search) {
            $q->where('nama_lokasi', 'LIKE', '%' . $search . '%')
            ->orWhere('keterangan_lokasi', 'LIKE', '%' . $search . '%');
        });
    }

}