<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AuthAdminService;

class AuthAdminController extends Controller
{
    public function __construct(
        protected AuthAdminService $authAdminService
    ) {}

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        return $this->authAdminService->login($request);
    }

    public function me(Request $request)
    {
        return $this->authAdminService->me($request);
    }
}
