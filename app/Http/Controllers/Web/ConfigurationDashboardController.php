<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Configuration\ConfigurationDashboardService;
use Illuminate\View\View;

/**
 * Admin configuration dashboard — single entry point for all configuration modules.
 */
class ConfigurationDashboardController extends Controller
{
    public function __construct(
        private readonly ConfigurationDashboardService $configurationDashboardService,
    ) {}

    public function index(): View
    {
        $dashboard = $this->configurationDashboardService->buildDashboard();

        return view('configuration.dashboard', [
            'modules' => $dashboard['modules'],
            'recentActivity' => $dashboard['recent_activity'],
            'healthWarnings' => $dashboard['health_warnings'],
        ]);
    }
}
