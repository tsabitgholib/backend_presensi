<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSuperAdmin;
use Illuminate\Http\Request;
use App\Services\IzinService;

class IzinController extends Controller
{
    use AuthorizesSuperAdmin;

    public function __construct(
        protected IzinService $izinService
    ) {}

    public function index()
    {
        return $this->izinService->index();
    }

    public function store(Request $request)
    {
        $this->authorizeSuperAdmin($request);
        $request->validate(['jenis' => 'required|string']);

        return $this->izinService->store($request);
    }

    public function show(Request $request, $id)
    {
        $this->authorizeSuperAdmin($request);

        return $this->izinService->show($request, $id);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeSuperAdmin($request);
        $request->validate(['jenis' => 'required|string']);

        return $this->izinService->update($request, $id);
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeSuperAdmin($request);

        return $this->izinService->destroy($request, $id);
    }
}
