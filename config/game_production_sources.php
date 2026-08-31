<?php

declare(strict_types=1);

return [
    'sources' => [
        0 => ['source' => 'military_units', 'options' => ['elite' => false]],
        1 => ['source' => 'buff_pool', 'options' => []],
        2 => ['source' => 'skillpoints', 'options' => []],
        4 => ['source' => 'collections', 'options' => []],
        6 => ['source' => 'buff_group', 'options' => ['group' => 5]],
        7 => ['source' => 'combat3_units', 'options' => []],
        8 => ['source' => 'military_units', 'options' => ['elite' => true]],
        11 => ['source' => 'buff_group', 'options' => ['group' => 11]],

        // Default source for all other production types
        'default' => ['source' => 'explicit_list', 'options' => []],
    ],

    'stacks' => [
        // Ceilings from client
        'max_amount' => 25,
        'max_stacks' => 200,

        // Types where stacks are always 1
        'unsupported_types' => [
            2 => 'SkillProductionPanel.Order() is strictly amount=1, stacks is not set',
            4 => 'CollectionProductionOverlay does not have a stacks stepper',
            27 => 'Confirmed by product owner in game',
        ],

        // Hypothesis H-1: disable stacks for culturebuilding lists
        'disable_for_list_type' => ['culturebuilding'],
    ],
];
