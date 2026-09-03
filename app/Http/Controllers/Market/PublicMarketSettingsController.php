<?php

declare(strict_types=1);

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Services\Market\MarketSettingsService;
use Illuminate\Http\JsonResponse;

/**
 * Public market configuration settings.
 */
final class PublicMarketSettingsController extends Controller
{
    public function __construct(
        private readonly MarketSettingsService $settings,
    ) {}

    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'combat_simulator_url' => $this->settings->combatSimulatorUrl(),
        ]);
    }
}
