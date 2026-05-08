<?php

namespace App\Http\Controllers\Api;

use App\Services\Api\AuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AuthRequest;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $service) {}

    public function me(Request $request)
    {
        return $this->service->me($request);
    }

    public function registerUser(AuthRequest $request)
    {
        return $this->service->registerUser($request);
    }

    public function login(AuthRequest $request)
    {
        return $this->service->login($request);
    }

    public function logout(Request $request)
    {
        return $this->service->logout($request);
    }
}
