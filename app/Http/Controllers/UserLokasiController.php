<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Lokasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UserLokasiController extends Controller
{
    /**
     * untuk GET /api/lokasi/user/lokasi Ambil lokasi untuk user yang sedang login
     */

    public function getUserLokasi(Request $request){
        
        try {
            $user = $request->user();

            // Log untuk debugging
            Log::info('getLokasi - User ID: ' . $user->id);

            // Ambil lokasi berdasarkan user_id
            $lokasis = Lokasi::where('user_id', $user->id)
                                ->select('id', 'lokasi', 'koordinat')
                                ->orderBy('lokasi')
                                ->get();

            Log::info('Jumlah lokasi ditemukan: ' . $lokasis->count());

            return response()->json($lokasis);

        } catch (\Exception $e) {
            Log::error('Error get user lokasi: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil lokasi',
            ], 500);
        }
    }

    public function submitAbsensiOtomatis(Request $request){
        Log::info('-' .str_repeat('-', 50));
        Log::info('SUBMIT ABSENSI OTOMATIS');
        Log::info('Request data: ', $request->all());

        try{
            $user = $request->user();
            $tipe = $request->tipe_absen;
            $titikKoordinatKamu = $request->titik_koordinat_kamu;

            Log::info('User ID :'.$user->id);
            Log::info('Tipe Absen :'.$tipe);
            Log::info('Titik Koordinat :'.$titikKoordinatKamu);

            if (! $tipe || ! in_array($tipe, ['masuk', 'pulang'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tipe absen tidak valid' ,             
                ],422 );
            }

            if (! $titikKoordinatKamu) {
                return response()->json([
                    'success' => false,
                    'message' => 'Titik koordinat wajib diisi' ,             
                ],422 );
            }

            $sudahAbsen = Absensi:: where('user_id', $user->id)
                                    ->where('tipe_absen', $tipe)
                                    ->whereDate('waktu_absen', now()->toDateString())
                                    ->exists();

            if ($sudahAbsen) {
                return response()->json([
                    'success' => false,
                    'message' => "Anda sudah melakukan absen $tipe hari ini" ,             
                ],400 );
            }

            if ($tipe == 'pulang') {
                $sudahMasuk = Absensi::where('user_id', $user->id)
                                    ->where('tipe_absen', $tipe)
                                    ->whereDate('waktu_absen', now()->toDateString())
                                    ->exists();
                if (! $sudahMasuk) {
                    return response()->json([
                        'success' => false,
                        'message' => "Anda harus absen masuk terlebih dahulu" ,             
                    ],400 );
                }
            }

            $userPaths = explode(',', $titikKoordinatKamu);
            if (count($userPaths) != 2) {
                return response()->json([
                        'success' => false,
                        'message' => "Format titik koordinat tidak valid" ,             
                ],422 );
            }

            $userLat = floatval(trim($userPaths[0]));
            $userLng = floatval(trim($userPaths[1]));

            $lokasis = Lokasi::where('user_id', $user->id)->get();

            if ($lokasis->isEmpty()) {
                return response()->json([
                        'success' => false,
                        'message' => "Anda belum memiliki lokasi absnsi, hubungi admin" ,             
                ],404 );
            }

            $lokasiTerdekat = null;
            $jarakTerdekat = PHP_FLOAT_MAX;
            $lokasiDalamRadius = [];
            

            foreach($lokasis as $lokasi) {

                $lokasiParts = explode(',', $lokasi->koordinat);
                if (count($lokasiParts) != 2) {
                    continue;
                }

                $lokasiLat = floatval(trim($userPaths[0]));
                $lokasiLng = floatval(trim($userPaths[1]));    

                $jarak = $this->hitungJarak($userLat, $userLng, $lokasiLat, $lokasiLng);

                Log::info("Jarak ke {$lokasi->$lokasi} : {$jarak} meter");

                $lokasiData = [
                    'id' => $lokasi->id,
                    'lokasi' => $lokasi->lokasi,
                    'koordinat' => $lokasi->koordinat,
                    'jarak' => round($jarak, 2),
                    'dalam_radius' => $jarak <= 100,
                ];
                
                $lokasiDalamRadius[] = $lokasiData;

                if ($jarak < $jarakTerdekat) {
                    $jarakTerdekat = $jarak;
                    $lokasiTerdekat = $lokasiData;
                }
            }

            $lokasiDalamRadius = array_filter($lokasiDalamRadius, function ($item) {
                return $item['dalam_radius'];
            });

            if (empty($lokasiDalamRadius)) {
                Log::warning("Tidak ada lokasi dalam radius, jarak terdekat : {$jarakTerdekat} meter");

                return response()->json([
                    'success' => false,
                    'message' => 'Anda berada dalam jangkauan absen',
                    'data' => [
                        'jarak_terdekat' => round($jarakTerdekat, 2),
                        'batas_radius' => 100,
                        'lokasi_terdekat' => $lokasiTerdekat,
                    ],
                ], 403);
            }

            $lokasiTerpilih = $lokasiTerdekat;

            $fotoUrl = null;
            if ($request->hasFile('foto_wajah')) {
                $file = $request->file('foto_wajah');

                $validExtensions = ['jpg', 'jpeg', 'png'];
                $extension = $file->getClientOriginalExtension();

                if (!in_array(strtolower($extension), $validExtensions)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Format foto tidak valid. Hanya JPG, JPEG, PNG yang diperbolehkan.'
                    ], 422);
                }

                // Generate nama file unik
                $filename = $tipe . '_' . time() . '.' . $user->id . '.' . $extension;

                $path = $file->storeAs('public/foto_absensi', $filename);

                if ($path) {
                    $baseUrl = config('app.url');
                    $fotoUrl = $baseUrl . Storage::url($path);
                    Log::info('Foto wajah berhasil diunggah: ' . $fotoUrl);
                } else {
                    Log::error('Gagal menyimpan foto wajah');
                }

            }else{

                Log::warning('Tidakada foto wajah yang diunggah');

                return response()->json([
                    'success' => false,
                    'message' => 'Foto wajah wajib diunggah'
                ], 422);
            }

            DB::beginTransaction();
            
            try {
                $absensi = new Absensi;
                $absensi->user_id = $user->id;
                $absensi->lokasi_id = $lokasiTerpilih['id'];
                $absensi->titik_koordinat_lokasi = $lokasiTerpilih['id'];
                $absensi->titik_koordinat_kamu = $titikKoordinatKamu;
                $absensi->foto_wajah = $fotoUrl;
                $absensi->tipe_absen = $tipe;
                $absensi->waktu_absen = now();
                $absensi->jarak_absensi = $lokasiTerpilih['jarak'];
                $absensi->save();

                DB::commit();
                Log::info('Absensi ' . $tipe . ' berhasil, ID = ' . $absensi->id);

                return response()->json([
                    'success' => true,
                    'message' => 'Absensi ' . $tipe . ' berhasil disimpan',
                    'data' => [
                        'id' => $absensi->id,
                        'tipe_absen' => $absensi->tipe_absen,
                        'lokasi' => $lokasiTerpilih['lokasi'],
                        'jarak' => $lokasiTerpilih['jarak'],
                        'waktu_absen' => $absensi->waktu_absen->toDateTimeString(),
                        'foto_wajah' => $absensi->foto_wajah,
                    ],
                ], 201);
     

            } catch (\Exception $e) {
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error cek status absensi hari ini: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengecek status absensi: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getRiwayatAbsensi(Request $request){
        try {
            $userId = $request->user()->id;

            $absensis = Absensi::where('user_id', $userId)
                                ->with('lokasi')
                                ->orderBy('waktu_absen', 'desc')
                                ->get();

            return response()->json($absensis);

        } catch (\Exception $e) {
            Log::error('Error get riwayat absensi: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil riwayat absensi: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cekStatusHariIni(Request $request){
        try {
            $userId = $request->user()->id;

            $absensiMasuk = Absensi::where('user_id', $userId)
                                        ->where('tipe_absen', 'masuk')
                                        ->whereDate('waktu_absen', now()->toDateString())
                                        ->with('lokasi')
                                        ->first();

            $absensiPulang = Absensi::where('user_id', $userId)
                                        ->where('tipe_absen', 'pulang')
                                        ->whereDate('waktu_absen', now()->toDateString())
                                        ->with('lokasi')
                                        ->first();

            return response()->json([
                'success' => true,
                'tanggal' => now()->toDateString(),
                'sudah_masuk' => $absensiMasuk ? true : false,
                'sudah_pulang' => $absensiPulang ? true : false,
                'data_masuk' => $absensiMasuk,
                'data_pulang' => $absensiPulang,
            ]);

        } catch (\Exception $e) {
            Log::error('Error cek status absensi hari ini: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengecek status absensi: ' . $e->getMessage()
            ], 500);
        }
    }
}
