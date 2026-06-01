<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin;
use App\Helpers\JWT;

class AuthAdminService
{
    public function login(Request $request)
    {
        $admin = Admin::where('email', $request->email)->first();
        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json(['message' => 'Email atau password salah'], 401);
        }


        $payload = [
            'sub' => $admin->id,
            'email' => $admin->email,
            'role' => $admin->role,
            'tenant_schema' => env('DB_SCHEMA'),
            'iat' => time(),
            'exp' => time() + 86400

        ];
        $token = JWT::encode($payload, env('JWT_SECRET'));

        return response()->json([
            'message' => 'Login berhasil',
            'token' => $token
        ]);
    }

    public function me(Request $request)
    {
        $admin = $request->get('admin');
        return response()->json([
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => $admin->role,
            'unit_id' => $admin->unit_id,
            'status' => $admin->status,
            'unit' => $admin->unit ?? null,
            'tenant_schema' => env('DB_SCHEMA')
        ]);
    }
}
