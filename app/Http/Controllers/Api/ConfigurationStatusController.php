<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Configuration\BusinessConfigurationService;
use Illuminate\Http\JsonResponse;

/**
 * Business configuration progress for the authenticated clinic (ADR-031).
 *
 * Route: GET /api/admin/configuration/status
 */
class ConfigurationStatusController extends Controller
{
    public function __construct(
        private readonly BusinessConfigurationService $businessConfigurationService,
    ) {}

    public function show(): JsonResponse
    {
        $status = $this->businessConfigurationService->status();

        return response()->json([
            'data' => [
                'progress_percentage' => $status['progress_percentage'],
                'ready_for_import' => $status['ready_for_import'],
                'missing_modules' => $status['missing_modules'],
                'current_step' => $status['current_step'],
                'steps' => collect($status['steps'])->map(fn (array $step) => [
                    'key' => $step['key'],
                    'label' => $step['label'],
                    'completed' => $step['completed'],
                    'required' => $step['required'],
                ])->values()->all(),
            ],
        ]);
    }
}
