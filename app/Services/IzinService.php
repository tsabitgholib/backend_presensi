<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\Izin;

class IzinService
{
    public function index()
    {
        return response()->json(Izin::all());
    }

    public function store(Request $request)
    {
        $izin = Izin::create(['jenis' => $request->jenis]);
        return response()->json($izin);
    }

    public function show(Request $request, $id)
    {
        $izin = Izin::findOrFail($id);
        return response()->json($izin);
    }

    public function update(Request $request, $id)
    {
        $izin = Izin::findOrFail($id);
        $izin->update(['jenis' => $request->jenis]);
        return response()->json($izin);
    }

    public function destroy(Request $request, $id)
    {
        $izin = Izin::findOrFail($id);
        $izin->delete();
        return response()->json(['message' => 'Izin deleted']);
    }
}
