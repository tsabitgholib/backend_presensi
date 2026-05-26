<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\Sakit;

class SakitService
{
    public function index()
    {
        return response()->json(Sakit::all());
    }

    public function store(Request $request)
    {
        $sakit = Sakit::create(['jenis' => $request->jenis]);
        return response()->json($sakit);
    }

    public function show(Request $request, $id)
    {
        $sakit = Sakit::findOrFail($id);
        return response()->json($sakit);
    }

    public function update(Request $request, $id)
    {
        $sakit = Sakit::findOrFail($id);
        $sakit->update(['jenis' => $request->jenis]);
        return response()->json($sakit);
    }

    public function destroy(Request $request, $id)
    {
        $sakit = Sakit::findOrFail($id);
        $sakit->delete();
        return response()->json(['message' => 'Sakit deleted']);
    }
}
