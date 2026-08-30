<?php

declare(strict_types=1);

namespace App\Services\Market\Contracts;

/**
 * Resolves the localized display name of a game resource at read time.
 *
 * Extracted from `MarketAnalyticsController::resourceName()`, which the
 * controller called from six different places. Consumers now depend on this
 * narrow abstraction rather than on the whole translation subsystem.
 */
interface ResourceNameResolver
{
    public function resolve(?string $itemId, ?string $legacyName = null): string;
}
