<?php

declare(strict_types=1);

namespace App\Support\Zone;

use App\Services\Game\Production\ProductionCatalogInterface;
use App\Services\Game\Production\ProductionQueueState;
use Throwable;

final readonly class ZoneSnapshot
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        private array $raw = []
    ) {}

    /**
     * Parse raw zone data (array, string, null, or malformed) into a clean snapshot object.
     */
    public static function fromData(mixed $data): self
    {
        if (is_array($data)) {
            return new self($data);
        }

        if (is_string($data) && $data !== '') {
            try {
                $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    return new self($decoded);
                }
            } catch (Throwable) {
                // Return empty snapshot on invalid json
            }
        }

        return new self([]);
    }

    public function avatarId(): ?int
    {
        return isset($this->raw['avatarId']) ? (int) $this->raw['avatarId'] : null;
    }

    public function buildingCount(): ?int
    {
        $buildings = $this->buildings();

        return $buildings !== [] ? count($buildings) : null;
    }

    public function serverName(): ?string
    {
        return isset($this->raw['gameWorldName']) && is_string($this->raw['gameWorldName']) && $this->raw['gameWorldName'] !== ''
            ? $this->raw['gameWorldName']
            : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildings(): array
    {
        return isset($this->raw['buildings']) && is_array($this->raw['buildings'])
            ? $this->raw['buildings']
            : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function specialists(): array
    {
        return isset($this->raw['specialists']) && is_array($this->raw['specialists'])
            ? $this->raw['specialists']
            : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buffs(): array
    {
        if (isset($this->raw['buffs']) && is_array($this->raw['buffs'])) {
            return $this->raw['buffs'];
        }

        if (isset($this->raw['availableBuffs']) && is_array($this->raw['availableBuffs'])) {
            return $this->raw['availableBuffs'];
        }

        return [];
    }

    /**
     * @return array<string, int>
     */
    public function resources(): array
    {
        if (! isset($this->raw['resources']) || ! is_array($this->raw['resources'])) {
            return [];
        }

        $result = [];
        foreach ($this->raw['resources'] as $res) {
            if (! is_array($res)) {
                continue;
            }
            $name = (string) ($res['name_string'] ?? $res['name'] ?? '');
            if ($name !== '') {
                $result[$name] = (int) ($res['amount'] ?? 0);
            }
        }

        return $result;
    }

    /**
     * @return list<ProductionQueueState>|null null = unparseable/unavailable, [] = available but empty
     */
    public function productionQueues(): ?array
    {
        if (! array_key_exists('production_queues', $this->raw)) {
            return null;
        }

        $rawQueues = $this->raw['production_queues'];
        if ($rawQueues === null) {
            return null;
        }

        if (! is_array($rawQueues)) {
            return null;
        }

        $result = [];
        foreach ($rawQueues as $q) {
            if (! is_array($q) || ! isset($q['production_type'])) {
                continue;
            }

            /** @var list<array{type_string: string, amount: int, produced_items: int, collected_time: float, stacks: int, index: int}> $orders */
            $orders = isset($q['orders']) && is_array($q['orders']) ? $q['orders'] : [];

            $result[] = new ProductionQueueState(
                productionType: (int) $q['production_type'],
                orders: $orders,
            );
        }

        return $result;
    }

    /**
     * Search by orders[0].productionType / queue state productionType.
     * Returns empty queue state if queue of this type has no orders.
     * Returns null ONLY if productionQueues() === null.
     */
    public function productionQueueFor(int $productionType): ?ProductionQueueState
    {
        $queues = $this->productionQueues();
        if ($queues === null) {
            return null;
        }

        foreach ($queues as $state) {
            if ($state->productionType === $productionType) {
                return $state;
            }
        }

        return ProductionQueueState::empty($productionType);
    }

    /**
     * @return list<array{
     *     grid: int,
     *     building_name: string,
     *     production_type: int,
     *     upgrade_level: int|null,
     *     upgrade_in_progress: bool,
     *     production_active: bool
     * }>
     */
    public function producerBuildings(?ProductionCatalogInterface $catalog = null): array
    {
        $catalog ??= app(ProductionCatalogInterface::class);
        $producers = [];

        foreach ($this->buildings() as $b) {
            $grid = (int) ($b['buildingGrid'] ?? $b['grid'] ?? 0);
            if ($grid <= 0) {
                continue;
            }

            $rawName = (string) ($b['buildingName_string'] ?? $b['buildingName'] ?? $b['name'] ?? '');
            if ($rawName === '') {
                continue;
            }

            $productionType = $catalog->productionTypeFor($rawName);
            if ($productionType === null || $productionType < 0) {
                continue;
            }

            $level = isset($b['upgradeLevel']) ? (int) $b['upgradeLevel'] : (isset($b['level']) ? (int) $b['level'] : null);
            $inProgress = (bool) ($b['upgradeIsInProgress'] ?? false);
            $active = (bool) ($b['isProductionActive'] ?? true);

            $producers[] = [
                'grid' => $grid,
                'building_name' => $rawName,
                'production_type' => $productionType,
                'upgrade_level' => $level,
                'upgrade_in_progress' => $inProgress,
                'production_active' => $active,
            ];
        }

        return $producers;
    }

    /**
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge([
            'gameWorldName' => $this->serverName(),
            'avatarId' => $this->avatarId(),
            'buildings' => $this->buildings(),
            'specialists' => $this->specialists(),
            'buffs' => $this->buffs(),
            'production_queues' => $this->raw['production_queues'] ?? null,
        ], $this->raw);
    }
}
