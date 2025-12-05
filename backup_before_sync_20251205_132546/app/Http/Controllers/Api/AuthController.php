<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'imei' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Email atau password salah',
            ], 401);
        }
        if ($user->status !== 'A') {
            return response()->json([
                'message' => 'User tidak aktif',
            ], 401);
        }
        // Cek IMEI/UUID device hanya jika request mengirimkan IMEI
        if ($request->filled('imei')) {
            if ($user->imei !== '00000' && $user->imei !== null) {
                if ($user->imei !== $request->imei) {
                    return response()->json([
                        'message' => 'Akun ini hanya bisa diakses dari perangkat yang terdaftar',
                    ], 401);
                }
            }
        }
        // Simpan last_seen, imei, device_info jika dikirim
        $user->last_seen = now();
        if ($request->has('imei')) $user->imei = $request->imei;
        if ($request->has('device_info')) $user->device_info = json_encode($request->device_info);
        $user->save();

        // Generate token manual dan simpan ke remember_token
        $token = bin2hex(random_bytes(32));
        $user->remember_token = $token;
        $user->save();
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'user' => $user
        ]);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        $this->appendUserInfo($user);
        return response()->json($user);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Berhasil logout']);
    }

    protected function appendUserInfo($user)
    {
        // Tambahkan informasi divisi dan jabatan
        if (method_exists($user, 'division') && $user->division) {
            $user->division_name = $user->division->nama_divisi;
        }
        if (method_exists($user, 'jabatan') && $user->jabatan) {
            $user->jabatan_name = $user->jabatan->nama_jabatan;
        }
        // Tambahkan informasi outlet
        if ($user->id_outlet) {
            $outlet = DB::table('tbl_data_outlet')
                ->where('id_outlet', $user->id_outlet)
                ->first();
            if ($outlet) {
                $user->outlet_name = $outlet->nama_outlet;
            }
        }
    }
} 