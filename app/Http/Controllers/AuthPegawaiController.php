<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AuthPegawaiService;

class AuthPegawaiController extends Controller
{
    public function __construct(
        protected AuthPegawaiService $authPegawaiService
    ) {}

    public function login(Request $request)
    {
        $request->validate([
            'no_ktp' => 'required',
            'password' => 'required',
        ]);

        return $this->authPegawaiService->login($request);
    }

    public function me(Request $request)
    {
        return $this->authPegawaiService->me($request);
    }

    public function checkDevice(Request $request)
    {
        $request->validate([
            'unique_device_id' => 'required|string'
                ]);

        return $this->authPegawaiService->checkDevice($request);
    }
}
