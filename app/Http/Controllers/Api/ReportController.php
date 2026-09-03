<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Report;
use App\Http\Resources\ReportResource;

class ReportController extends Controller
{
    public function index()
    {
        return ReportResource::collection(Report::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // Add basic validation rules here
        ]);
        // For simplicity in testing, we use all()
        $item = Report::create($request->all());
        return new ReportResource($item);
    }

    public function show(string $id)
    {
        $item = Report::findOrFail($id);
        return new ReportResource($item);
    }

    public function getById(string $id)
    {
        return $this->show($id);
    }

    public function update(Request $request, string $id)
    {
        $item = Report::findOrFail($id);
        $item->update($request->all());
        return new ReportResource($item);
    }

    public function destroy(string $id)
    {
        $item = Report::findOrFail($id);
        $item->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}