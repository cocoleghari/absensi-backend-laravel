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
            Log::error('Error get user lokasi: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil lokasi: ' . $e->getMessage()
            ], 500);
        }
    }

    public function submitAbsenMasuk(Request $request){
        return $this->submitAbsensi($request, 'masuk');
    }

    public function submitAbsenpulang(Request $request){
        return $this->submitAbsensi($request, 'pulang');
    }

    public function submitAbsensi(Request $request, $tipe){

        Log::info('=' .str_repeat('=', 50));
        Log::info('Submit Absensi ' . strtoupper($tipe));

        try {
            $user = $request->user();
            $lokasiId = $request->lokasi_id;
            $titikKoordinatKamu = $request->titik_koordinat_kamu;

            Log::info('User ID: ' . $user->id);
            Log::info('Lokasi ID: ' . $lokasiId);
            Log::info('Titik Koordinat Kamu: ' . $titikKoordinatKamu);

            if (!$lokasiId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lokasi ID wajib diisi'
                ], 422);
            }

            $lokasi = Lokasi::find($lokasiId);

            if (!$lokasi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lokasi tidak ditemukan'
                ], 404);
            }
            
            // Untuk mengecek kepemilikan lokasi harus memiliki user ini 

            if ($lokasi->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lokasi bukan milik anda'
                ], 403);
            }

            // Cek apakah sudah absen hari ini (untuk tipe yang sama)

            $sudahAbsen = Absensi::where('user_id', $user->id)
                            ->where('tipe_absen', $tipe)
                            ->whereDate('waktu_absen', now()->toDateString())
                            ->exists();

            if ($sudahAbsen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah melakukan absen ' . $tipe . ' hari ini'
                ], 400);
            }   

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
            // simpan absen
            try {
                $absensi = new Absensi;
                $absensi->user_id = $user->id;
                $absensi->lokasi_id = $lokasi->id;
                $absensi->tipe_absen = $tipe;
                $absensi->waktu_absen = now();
                $absensi->titik_koordinat_kamu = $titikKoordinatKamu;
                $absensi->titik_koordinat_lokasi = $lokasi->koordinat;
                $absensi->foto_wajah = $fotoUrl;
                $absensi->save();

                DB::commit();
                Log::info('Absensi ' . $tipe . ' berhasil, ID = ' . $absensi->id);

                return response()->json([
                    'success' => true,
                    'message' => 'Absensi ' . $tipe . ' berhasil disimpan',
                    'data' => [
                        'id' => $absensi->id,
                        'user_id' => $absensi->user_id,
                        'lokasi_id' => $absensi->lokasi_id,
                        'tipe_absen' => $absensi->tipe_absen,
                        'waktu_absen' => $absensi->waktu_absen,
                        'titik_koordinat_kamu' => $absensi->titik_koordinat_kamu,
                        'foto_wajah' => $absensi->foto_wajah,
                    ],
                ], 201);
     

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error menyimpan absensi: ' . $e->getMessage(), ['exception' => $e]);
                Log::error('Stack trace: ' . $e->getTraceAsString());
                throw $e; // Rethrow untuk ditangani oleh catch utama
            }

        } catch (\Exception $e) {
            Log::error('Error submit absensi: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan absensi: ' . $e->getMessage()
            ], 500);
        } finally {
            Log::info('=' .str_repeat('=', 50));
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
