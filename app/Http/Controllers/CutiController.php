<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSuperAdmin;
use Illuminate\Http\Request;
use App\Services\CutiService;

class CutiController extends Controller
{
    use AuthorizesSuperAdmin;

    public function __construct(
        protected CutiService $cutiService
    ) {}

    public function index()
    {
        return $this->cutiService->index();
    }

    public function store(Request $request)
    {
        $this->authorizeSuperAdmin($request);
        $request->validate(['jenis' => 'required|string']);

        return $this->cutiService->store($request);
    }

    public function show(Request $request, $id)
    {
        $this->authorizeSuperAdmin($request);

        return $this->cutiService->show($request, $id);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeSuperAdmin($request);
        $request->validate(['jenis' => 'required|string']);

        return $this->cutiService->update($request, $id);
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeSuperAdmin($request);

        return $this->cutiService->destroy($request, $id);
    }
}
