<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardOverviewService;
use Illuminate\Http\JsonResponse;

/**
 * RESTful entry point for system dashboard statistics.
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardOverviewService $dashboardOverviewService,
    ) {}

    public function index(): JsonResponse
    {
        return new JsonResponse($this->dashboardOverviewService->getOverview());
    }
}
