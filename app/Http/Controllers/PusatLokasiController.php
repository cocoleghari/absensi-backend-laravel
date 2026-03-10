<?php

namespace App\Http\Controllers;

use App\Models\Pusat_Lokasi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PusatLokasiController extends Controller
{
    public function getAllLokasi(Request $request){
        try {

            $search = $request->input('search');

            $pusat_lokasi = Pusat_Lokasi::when($search, function($query) use ($search) {
                return $query->where('nama_lokasi', 'LIKE', '%' . $search . '%')
                            ->orWhere('keterangan_lokasi', 'LIKE', '%' . $search . '%');
            })->get();

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil ditampilkan',
                'data'     => $pusat_lokasi

            ], 201);
        } 
        
        catch (\Exception $e) {
            Log::error('Error get lokasi: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'error' => 'Gagal menammpilkan lokasi'
            ], 500);
        }
    }

    public function getAllLokasiByID($id){
        try {
            $pusat_lokasi = Pusat_Lokasi::find($id);

            return response()->json([
                'success' => true,
                'message' => 'Data Pusat Lokasi Per ID berhasil ditampilkan',
                'data'     => $pusat_lokasi

            ], 201);
        } 
        
        catch (\Exception $e) {
            Log::error('Error get lokasi: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'error' => 'Gagal Menampilkan Lokasi berdasarkan ID'
            ], 500);
        }
    }

    public function store(Request $request){
        try {
            $request ->validate([
                'nama_lokasi' => 'required|string',
                'titik_koordinat' => 'required|string',
                'keterangan_lokasi' => 'required|string',
            ]);

            $pusat_lokasi = Pusat_Lokasi::create([
                'nama_lokasi' => $request->nama_lokasi,
                'titik_koordinat' => $request->titik_koordinat,
                'keterangan_lokasi' => $request->keterangan_lokasi
            ]);

            Log::info('Lokasi created successfully: ', [
                'id' => $pusat_lokasi->id,
                'nama_lokasi' => $pusat_lokasi->nama_lokasi,
                'titik_koordinat' => $pusat_lokasi->titik_koordinat,
                'keterangan_lokasi' => $pusat_lokasi->keterangan_lokasi
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil ditambahkan',
                'data'     => $pusat_lokasi

            ], 201);
        } 
        
        catch (\Exception $e) {
            Log::error('Error store lokasi: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mnembahkan lokasi'
            ], 500);
        }
    }    

    public function update(Request $request, $id){
        try {
            $pusat_lokasi = Pusat_Lokasi::findOrFail($id);

            $request->validate([
                'nama_lokasi' => 'sometimes|string',
                'titik_koordinat' => 'required|string',
                'keterangan_lokasi' => 'required|string',
            ]);


            $pusat_lokasi->update($request->all());

            Log::info('Pusat Lokasi updated successfully: ', [
                'id' => $id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil diupdate',
                'data'     => $pusat_lokasi

            ], 201);
        } 
        
        catch (\Exception $e) {
            Log::error('Error update lokasi: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Data pusat lokasi tidak ditemukan'
            ], 500);
        }
    }

    public function destroy($id){
        try {
            $pusat_lokasi = Pusat_Lokasi::findOrFail($id);
            $pusat_lokasi->delete();

            Log::info('Lokasi deleted successfully: ', [
                'id' => $id
            ]);

            return response()->json([
                'message' => 'Lokasi berhasil dihapus'
            ]);
        } 
        
        catch (\Exception $e) {
            Log::error('Error delete lokasi: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus Pusat Lokasi'
            ], 500);
        }
    }
}
