<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TidakAbsenService;

class TidakAbsenController extends Controller
{
    public function __construct(
        protected TidakAbsenService $tidakAbsenService
    ) {}

    public function generateAbsentToday(Request $request)
    {
        return $this->tidakAbsenService->generateAbsentToday($request);
    }
}
