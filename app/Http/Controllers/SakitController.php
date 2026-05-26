<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSuperAdmin;
use Illuminate\Http\Request;
use App\Services\SakitService;

class SakitController extends Controller
{
    use AuthorizesSuperAdmin;

    public function __construct(
        protected SakitService $sakitService
    ) {}

    public function index()
    {
        return $this->sakitService->index();
    }

    public function store(Request $request)
    {
        $this->authorizeSuperAdmin($request);
        $request->validate(['jenis' => 'required|string']);

        return $this->sakitService->store($request);
    }

    public function show(Request $request, $id)
    {
        $this->authorizeSuperAdmin($request);

        return $this->sakitService->show($request, $id);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeSuperAdmin($request);
        $request->validate(['jenis' => 'required|string']);

        return $this->sakitService->update($request, $id);
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeSuperAdmin($request);

        return $this->sakitService->destroy($request, $id);
    }
}
