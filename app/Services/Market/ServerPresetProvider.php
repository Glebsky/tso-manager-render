<?php

declare(strict_types=1);

namespace App\Services\Market;

/**
 * Supplies the known server connection presets.
 *
 * The list used to be a hard-coded private method of the HTTP controller,
 * which meant the presentation layer owned region/locale knowledge. It now
 * comes from config/market.php through this narrow collaborator.
 */
final class ServerPresetProvider
{
    /**
     * @return list<array{server_id: string, locale: string, display_name: string}>
     */
    public function all(): array
    {
        /** @var list<array{server_id: string, locale: string, display_name: string}> $presets */
        $presets = (array) config('market.presets', []);

        return array_values($presets);
    }
}
