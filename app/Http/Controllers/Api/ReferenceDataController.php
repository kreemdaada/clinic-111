<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Lab;
use App\Models\Treatment;
use Illuminate\Http\JsonResponse;

class ReferenceDataController extends Controller
{
    public function doctors(): JsonResponse
    {
        $doctors = Doctor::query()
            ->with('defaultLab')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Doctor $doctor) => [
                'id' => $doctor->id,
                'name' => $doctor->name,
                'code' => $doctor->code,
                'commission_type' => $doctor->commission_type->value,
                'commission_percentage' => $doctor->commission_percentage,
                'default_lab' => $doctor->defaultLab ? [
                    'id' => $doctor->defaultLab->id,
                    'code' => $doctor->defaultLab->code,
                    'name' => $doctor->defaultLab->name,
                ] : null,
            ]);

        return response()->json(['data' => $doctors]);
    }

    public function treatments(): JsonResponse
    {
        $treatments = Treatment::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn (Treatment $treatment) => [
                'id' => $treatment->id,
                'code' => $treatment->code,
                'name' => $treatment->name,
                'has_lab_cost' => $treatment->has_lab_cost,
            ]);

        return response()->json(['data' => $treatments]);
    }

    public function labs(): JsonResponse
    {
        $labs = Lab::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Lab $lab) => [
                'id' => $lab->id,
                'code' => $lab->code,
                'name' => $lab->name,
            ]);

        return response()->json(['data' => $labs]);
    }
}
