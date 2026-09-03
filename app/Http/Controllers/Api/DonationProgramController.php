<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DonationProgram;
use App\Http\Resources\DonationProgramResource;

class DonationProgramController extends Controller
{
    public function index()
    {
        return DonationProgramResource::collection(
            DonationProgram::with(['seeds.specifications', 'donations', 'plantedSeeds', 'kth', 'analysisResultZone.fieldValidations', 'analysisResultZone.result.project'])->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'jenis_bibit' => 'nullable|array',
            'jenis_bibit.*' => 'exists:seeds,id',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'start_date' => 'nullable|date', 
            'end_date' => 'nullable|date',
            'analysis_result_id' => 'nullable|integer',
            // Add basic validation rules here
        ]);
        
        $data = $request->all();
        $data['status'] = 'Menunggu Verifikasi';

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('donation_programs', 'public');
            $data['image'] = $path;
        }
        
        $item = DonationProgram::create($data);
        
        if ($request->has('jenis_bibit') && is_array($request->jenis_bibit)) {
            $item->seeds()->sync($request->jenis_bibit);
        }
        
        $item->load('seeds');
        return new DonationProgramResource($item);
    }

    public function show(string $id)
    {
        $program = DonationProgram::with(['seeds.specifications', 'donations', 'plantedSeeds', 'kth', 'analysisResultZone.fieldValidations', 'analysisResultZone.result.project'])->findOrFail($id);
        return new DonationProgramResource($program);
    }

    public function getById(string $id)
    {
        return response()->json(
            DonationProgram::with(['seeds', 'donations.user', 'kth', 'analysisResultZone.fieldValidations', 'analysisResultZone.result.project'])->findOrFail($id)
        );
    }

    public function update(Request $request, string $id)
    {
        $item = DonationProgram::findOrFail($id);
        $item->update($request->all());
        $item->load('seeds');
        return new DonationProgramResource($item);
    }

    public function destroy(string $id)
    {
        $item = DonationProgram::findOrFail($id);
        $item->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}