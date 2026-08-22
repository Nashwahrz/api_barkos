<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Testimoni;
use Illuminate\Http\Request;

class TestimoniController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(['data' => Testimoni::all()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string',
            'pekerjaan' => 'required|string',
            'jenis_kelamin' => 'nullable|in:laki-laki,perempuan',
            'tanggal_lahir' => 'required|date',
        ]);

        $testimoni = Testimoni::create($request->only(
            ['nama', 'pekerjaan', 'jenis_kelamin', 'tanggal_lahir']));

        return response()->json(['message' => 'Testimoni created successfully', 'data' => $testimoni], 201);
        
    }

    /**
     * Display the specified resource.
     */
    public function show(Testimoni $testimoni)
    {
        return response()->json(['data' => $testimoni]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Testimoni $testimoni)
    {
        $request->validate([
            'nama' => 'sometimes|required|string',
            'pekerjaan' => 'sometimes|required|string',
            'jenis_kelamin' => 'nullable|in:laki-laki,perempuan',
            'tanggal_lahir' => 'sometimes|required|date',
        ]);
        $testimoni->update($request->only(
            ['nama', 'pekerjaan', 'jenis_kelamin', 'tanggal_lahir']));

            return response()->json(['message' => 'Testimoni updated successfully', 'data' => $testimoni]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Testimoni $testimoni)
    {
        $testimoni->delete();
        
        return response()->json(['message' => 'Testimoni deleted successfully']);
    }
}
