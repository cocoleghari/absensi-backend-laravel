<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Lokasi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminAbsensiController extends Controller
{
    public function getAllAbsensi(Request $request){
        
        try {


            // ngecek admin atau bukan 
            if($request->user()->role !== 'admin'){
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak, hanya bisa diakses oleh admin'
                ], 403);
            }          

            $query = Absensi::with(['user', 'lokasi']);

            if ($request->has('user_id') && $request->user_id && $request->user_id != '') {
                $query->where('user_id', $request->user_id);
            }

            $absensis = $query->orderBy('waktu_absen', 'desc')->get();

            Log::info('getAllAbsensi - Total absensi ditemukan: ' . $absensis->count());    

            return response()->json([
                'success' => true,
                'data' => $absensis,
            ]);

        } catch (\Exception $e) {
            Log::error('Error get all absensi: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data absensi: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAllUsers(Request $request){
        try {

            // ngecek admin atau bukan 
            if($request->user()->role !== 'admin'){
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak, hanya bisa diakses oleh admin'
                ], 403);
            } 


            $users = User::select('id', 'name', 'email')
                        -> where('role', 'user')
                        -> orderBy('name')  
                        -> get();        

            Log::info('getAllUsers - Total users ditemukan: ' . $users->count());    

            return response()->json([
                'success' => true,
                'data' => $users,
            ]);

        } catch (\Exception $e) {
            Log::error('Error get all users: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data users: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteAbsensi($id){
        try {

            // ngecek admin atau bukan 
            if(auth()->user()->role !== 'admin'){
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak, hanya bisa diakses oleh admin'
                ], 403);
            } 


            $absensi = Absensi::find($id);

            if (!$absensi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Absensi tidak ditemukan',
                ], 404);
            }

            if ($absensi->foto_wajah) {
                try {

                    $url = $absensi->foto_wajah;

                    $pathParts= explode('/storage/foto_absensi', $url);
                    if (count($pathParts) > 1) {
                        $fileName = $pathParts[1];
                        $stroragePath = 'public/foto_absensi/' . $fileName;

                        if (Storage::exists($stroragePath)) {
                            Storage::delete($stroragePath);
                            Log::info('Foto absensi dihapus : ' . $stroragePath);
                        } 

                    }
                    
                    // Hapus data absensi
                    $absensi->delete();

                    Log::info('deleteAbsensi', [
                        "id" => $id,
                        "admin_id" => auth()->id,
                    ]);

                } catch (\Exception $e) {
                    Log::error('deleteAbsensi - Error saat menghapus foto absensi dengan ID ' . $id . ': ' . $e->getMessage());
                }
            }


            Log::info('deleteAbsensi - Absensi dengan ID ' . $id . ' berhasil dihapus');

            return response()->json([
                'success' => true,
                'message' => 'Absensi berhasil dihapus',
            ]);

        } catch (\Exception $e) {
            Log::error('Error delete absensi: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus absensi: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getStatistics(Request $request){
        try {

            // ngecek admin atau bukan 
            if($request->user()->role !== 'admin'){
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak, hanya bisa diakses oleh admin'
                ], 403);
            } 
            
            $today = now()->toDateString();

            $statistics = [
                'total_users' => User::where('role', 'user')->count(),
                'total_absensi' => Absensi::count(),
                'absensi_hari_ini' => Absensi::whereDate('waktu_absen' , $today)->count(),
                'absensi_masuk_hari_ini' => Absensi::whereDate('waktu_absen' , $today)->where('tipe_absen', 'masuk')->count(),
                'absensi_pulang_hari_ini' => Absensi::whereDate('waktu_absen' , $today)->where('tipe_absen', 'pulang')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $statistics,
            ]);

        } catch (\Exception $e) {
            Log::error('Error get statistics: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil statistik: ' . $e->getMessage()
            ], 500);
        }
    }
}
