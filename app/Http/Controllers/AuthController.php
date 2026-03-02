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
        Log::info('Request Data: ', $request->all());

        try {
            // Validasi input
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
                'role' => 'required|in:admin,user'
            ],
            
            [
                'name.required' => 'Nama harus diisi',
                'email.required' => 'Email harus diisi',
                'email.email' => 'Format email tidak valid',
                'email.unique' => 'Email sudah terdaftar',
                'password.required' => 'Password harus diisi',
                'password.min' => 'Password minimal 6 karakter',
                'role.required' => 'Role harus diisi',
                'role.in' => 'Role harus admin atau user'
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
            Log::error('Error during registration', $e->getMessage());

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
        
        Log::info('='.str_repeat('=', 50));
        Log::info('LOGIN ATTEMPT:', ['email' =>$request->email]);

        try{
            // Validasi input
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required'
            ]);

            // Cari pengguna berdasarkan email
            $user = User::where('email', $validated['email'])->first();

            if(!$user){
                Log::warning('Login failed: User not found', ['email' => $validated['email']]);
                return response()->json([
                    'success' => false,
                    'message' => 'Email tidak ditemukan'
                ], 404);
            } 

            // Cek password 
            if (!$user || !Hash::check($validated['password'], $user->password)) {
                Log::warning('Login failed: Incorrect password', ['email' => $validated['email']]);
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
            Log::warning('Validation failed during login:', $e->errors());

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error during login:', $e->getMessage());

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

    public function getUser(Request $request){

        try{
            //cek apakah user adalah admin
            if ($request->user()->role !== 'admin') {

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // mengambil semua user
            $users = User::select('id', 'name', 'email', 'role')
                            ->orderBy('name')
                            ->get();

            return response()->json([
                'success' => true,
                'data' => $users    
            ]);
        }
        catch (\Exception $e) {
            Log::error('Get users error:', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data users'
            ], 500);
        }
    }

    // delete user khusus admin
    public function deleteUser($id){
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan'
                ], 404);
            }

            //agar tidak bisa menghapus dirinya sendiri
            if (auth()->id() == $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak dapat menghapus diri sendiri'
                ], 403);    
            }

            $user->delete();

            Log::info('User deleted: ', ['id' => $id]);
            
            return response()->json([
                'success' => true,
                'message' => 'User berhasil dihapus'
            ]);
        
        } catch (\Exception $e) {
            Log::error('Delete user error:', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus user'
            ], 500);
        }
    }
}
