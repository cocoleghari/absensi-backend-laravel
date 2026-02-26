<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Registrasi pengguna baru.
     */

    public function register(Request $request){
        //Log untuk debugging
        Log::info('='.str_repeat('=', 50));
        Log::info('REGISTER ATTEMPT');
        log::info('Request Data: ', $request->all());

        try {
            // Validasi input
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
                'role' => 'required|in:admin,user'
            ]);

            Log::info('Data tervalidasi: ', $validated);

            // Buat pengguna baru
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
            ]);

            Log::info('User registered successfully: ', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ]
            ], 201);

        } catch (ValidationException $e) {
            Log::warning('Validation failed during registration:', $e->errors());
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error during registration',$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server'
            ], 500);
        }
    }

    /**
     * Login pengguna.
     * 
     * Method ini menerima email dan password
     * mengecek kecocokan, dan mengembalikan token akses jika berhasil.
     */

    public function login(Request $request){
        
        log::info('='.str_repeat('=', 50));
        log::info('LOGIN ATTEMPT:', ['email' =>$request->email]);

        try{
            // Validasi input
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required'
            ]);

            // Cari pengguna berdasarkan email
            $user = User::where('email', $validated['email'])->first();

            if(!$user){
                log::warning('Login failed: User not found', ['email' => $validated['email']]);
                return response()->json([
                    'success' => false,
                    'message' => 'Email tidak ditemukan'
                ], 404);
            } 

            // Cek password 
            if (!$user || !Hash::check($validated['password'], $user->password)) {
                log::warning('Login failed: Incorrect password', ['email' => $validated['email']]);
                return response()->json([
                    'success' => false,
                    'message' => 'Password salah'
                ], 401);
            }

            //Hapus token lama
            $user->tokens()->delete();

            // Buat token akses
            $token = $user->createToken('auth_token')->plainTextToken;

            Log::info('Login successful', [
                'user_id' => $user->id,
                'role' => $user->role
            ]);

            return response()->json([
                'success' => true,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ]
            ]);

        } catch (ValidationException $e) {
            log::warning('Validation failed during login:', $e->errors());

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            log::error('Error during login:', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server'
            ], 500);
        }

    }

    /**
     * Logout pengguna.
     * 
     * Method ini menghapus token akses yang sedang digunakan.
     */

    public function logout(Request $request){

        try{
            //hapus semua token untuk pengguna ini
            $request->user()->tokens()->delete();

            Log::info('User logged out successfully', [
                'user_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);
        }catch (\Exception $e) {
            Log::error('Error during logout:', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal logout'
            ], 500);
        }
    }
}
