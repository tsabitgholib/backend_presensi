<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\Cuti;

class CutiService
{
    public function index()
    {
        return response()->json(Cuti::all());
    }

    public function store(Request $request)
    {
        $cuti = Cuti::create(['jenis' => $request->jenis]);
        return response()->json($cuti);
    }

    public function show(Request $request, $id)
    {
        $cuti = Cuti::findOrFail($id);
        return response()->json($cuti);
    }

    public function update(Request $request, $id)
    {
        $cuti = Cuti::findOrFail($id);
        $cuti->update(['jenis' => $request->jenis]);
        return response()->json($cuti);
    }

    public function destroy(Request $request, $id)
    {
        $cuti = Cuti::findOrFail($id);
        $cuti->delete();
        return response()->json(['message' => 'Cuti deleted']);
    }
}
