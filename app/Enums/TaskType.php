<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskType: string
{
    case Buff = 'buff';
    case ApplyBuff = 'apply_buff';
    case Building = 'building';
    case StopProduction = 'stop_production';
    case StartProduction = 'start_production';
    case Specialist = 'specialist';
    case SendGeologist = 'send_geologist';
    case SendExplorer = 'send_explorer';
    case SendSpecialist = 'send_specialist';
    case CollectPickups = 'collect_pickups';
    case CollectBuilding = 'collect_building';
    case BuildMine = 'build_mine';
    case UpgradeMine = 'upgrade_mine';
    case ProduceBuff = 'produce_buff';
    case Sequence = 'sequence';
    case Trade = 'trade';

    public function isSequence(): bool
    {
        return $this === self::Sequence;
    }
}
