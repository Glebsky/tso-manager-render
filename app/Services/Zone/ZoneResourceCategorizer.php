<?php

declare(strict_types=1);

namespace App\Services\Zone;

/**
 * Categorizes game resources into warehouse tabs based on resource names.
 */
class ZoneResourceCategorizer
{
    /**
     * Get the warehouse tab category for a resource name.
     */
    public function getCategory(string $name): string
    {
        if (empty($name)) {
            return 'Other';
        }

        $lowerName = strtolower(trim($name));

        if (str_contains($lowerName, 'balloon') ||
            str_contains($lowerName, 'egg') ||
            str_contains($lowerName, 'gift') ||
            str_contains($lowerName, 'pumpkin') ||
            str_contains($lowerName, 'present')) {
            return 'WarehouseTab6';
        }

        if (str_starts_with($lowerName, 'collectible') ||
            str_contains($lowerName, 'collectible')) {
            return 'WarehouseTab7';
        }

        $mapping = [
            // WarehouseTab1 (CL1) — Basic
            'tree' => 'WarehouseTab1',
            'wood' => 'WarehouseTab1',
            'plank' => 'WarehouseTab1',
            'stone' => 'WarehouseTab1',
            'fish' => 'WarehouseTab1',
            'population' => 'WarehouseTab1',
            'token' => 'WarehouseTab1',
            'crystalshard' => 'WarehouseTab1',
            'crystal' => 'WarehouseTab1',
            'mappart' => 'WarehouseTab1',
            'guildcoins' => 'WarehouseTab1',
            'starfallstardust' => 'WarehouseTab1',
            'starfallstarshards' => 'WarehouseTab1',

            // WarehouseTab2 (CL2) — Advanced
            'coal' => 'WarehouseTab2',
            'bronzeore' => 'WarehouseTab2',
            'bronze' => 'WarehouseTab2',
            'tool' => 'WarehouseTab2',
            'tools' => 'WarehouseTab2',
            'water' => 'WarehouseTab2',
            'corn' => 'WarehouseTab2',
            'beer' => 'WarehouseTab2',
            'flour' => 'WarehouseTab2',
            'bread' => 'WarehouseTab2',
            'bronzesword' => 'WarehouseTab2',
            'bow' => 'WarehouseTab2',
            'simplepaper' => 'WarehouseTab2',
            'nib' => 'WarehouseTab2',
            'manuscript' => 'WarehouseTab2',
            'adventuretale' => 'WarehouseTab2',
            'copper' => 'WarehouseTab2',
            'copperore' => 'WarehouseTab2',
            'grain' => 'WarehouseTab2',
            'brew' => 'WarehouseTab2',

            // WarehouseTab3 (CL3) — Superior
            'realwood' => 'WarehouseTab3',
            'realplank' => 'WarehouseTab3',
            'ironore' => 'WarehouseTab3',
            'iron' => 'WarehouseTab3',
            'steel' => 'WarehouseTab3',
            'goldore' => 'WarehouseTab3',
            'gold' => 'WarehouseTab3',
            'coin' => 'WarehouseTab3',
            'coins' => 'WarehouseTab3',
            'coinage' => 'WarehouseTab3',
            'marble' => 'WarehouseTab3',
            'meat' => 'WarehouseTab3',
            'sausage' => 'WarehouseTab3',
            'ironsword' => 'WarehouseTab3',
            'steelsword' => 'WarehouseTab3',
            'longbow' => 'WarehouseTab3',
            'pike' => 'WarehouseTab3',
            'compositebow' => 'WarehouseTab3',
            'expeditioncrossbow' => 'WarehouseTab3',
            'battlelance' => 'WarehouseTab3',
            'saber' => 'WarehouseTab3',
            'spikedmace' => 'WarehouseTab3',
            'horse' => 'WarehouseTab3',
            'horses' => 'WarehouseTab3',
            'intermediatepaper' => 'WarehouseTab3',
            'letter' => 'WarehouseTab3',
            'tome' => 'WarehouseTab3',

            // WarehouseTab4 (CL4) — Expert
            'exoticwood' => 'WarehouseTab4',
            'exoticplank' => 'WarehouseTab4',
            'titaniumore' => 'WarehouseTab4',
            'titanium' => 'WarehouseTab4',
            'salpeter' => 'WarehouseTab4',
            'gunpowder' => 'WarehouseTab4',
            'granite' => 'WarehouseTab4',
            'grout' => 'WarehouseTab4',
            'wheel' => 'WarehouseTab4',
            'carriage' => 'WarehouseTab4',
            'titaniumsword' => 'WarehouseTab4',
            'crossbow' => 'WarehouseTab4',
            'cannon' => 'WarehouseTab4',
            'starcoin' => 'WarehouseTab4',
            'magicbean' => 'WarehouseTab4',
            'magicbeanstalk' => 'WarehouseTab4',
            'advancedpaper' => 'WarehouseTab4',
            'bookfitting' => 'WarehouseTab4',
            'codex' => 'WarehouseTab4',
            'valorpoint' => 'WarehouseTab4',
            'oil' => 'WarehouseTab4',
            'advancedtools' => 'WarehouseTab4',
            'oilseed' => 'WarehouseTab4',
            'seed' => 'WarehouseTab4',
            'damascenesword' => 'WarehouseTab4',

            // WarehouseTab8 (CL5) — Elite
            'mahoganywood' => 'WarehouseTab8',
            'mahoganyplank' => 'WarehouseTab8',
            'platinumore' => 'WarehouseTab8',
            'platinum' => 'WarehouseTab8',
            'obsidianore' => 'WarehouseTab8',
            'battlehorse' => 'WarehouseTab8',
            'wagon' => 'WarehouseTab8',
            'wool' => 'WarehouseTab8',
            'cloth' => 'WarehouseTab8',
            'saddlecloth' => 'WarehouseTab8',
            'platinumsword' => 'WarehouseTab8',
            'archebuse' => 'WarehouseTab8',
            'mortar' => 'WarehouseTab8',

            // WarehouseTab6 (Event) — Event
            'rednose' => 'WarehouseTab6',
            'eventresource' => 'WarehouseTab6',
            'emeventresource' => 'WarehouseTab6',
            'halloweenresource' => 'WarehouseTab6',
            'christmasresource' => 'WarehouseTab6',
            'candles' => 'WarehouseTab6',
            'cakedough' => 'WarehouseTab6',
            'valentinesflower' => 'WarehouseTab6',
            'guildfesttoken' => 'WarehouseTab6',
            'guildfestcommendation' => 'WarehouseTab6',
            'adventurerelics' => 'WarehouseTab6',

            // WarehouseTab5 (DEF_MODE / Military) — Troops
            'defensepoint' => 'WarehouseTab5',
        ];

        return $mapping[$lowerName] ?? 'Other';
    }
}
