<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LaporanProyek;
use Illuminate\Http\Request;

class LaporanProyekController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(\App\Models\LaporanProyek::with(['program'])->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return response()->json(\App\Models\LaporanProyek::with(['program'])->findOrFail($id));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LaporanProyek $laporanProyek)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LaporanProyek $laporanProyek)
    {
        //
    }
}
