<?php

declare(strict_types=1);

namespace App\Support\Zone;

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
        ], $this->raw);
    }
}
