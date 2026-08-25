<?php

declare(strict_types=1);

return [
    'error' => [
        'task_inactive' => 'Task #:id is inactive or paused.',
        'token_mismatch' => 'Execution token mismatch for task #:id. Expected: :expected, found: :found',
        'account_not_found' => 'Account for task #:id not found.',
        'friend_not_found' => 'Step skipped: player is no longer in friends list',
        'friend_zone_failed' => 'Failed to load friend\'s zone (server error code :err: :errMsg)',
        'friend_building_not_found' => 'Step failed: building Grid #:grid not found in zone :friendName',
        'server_error' => 'Server error code :errorCode: :errorMsg',
        'unknown_action_type' => 'Unknown action type: :taskType',
        'pickups_unavailable' => 'Zone response contains no collectibles list. Patch storage/app/parse_zone.py so it returns the pickups key.',
        'insufficient_buffs' => 'Insufficient buffs in star menu (available: :available, required: :required).',
        'buff_not_found' => 'Specified buff not found in star menu inventory.',
        'invalid_friend_id' => 'Invalid friend ID.',
        'friend_not_in_list' => 'Player is not in your friends list.',
        'friend_building_not_found_grid' => 'Building with Grid #:grid not found in friend zone.',
        'friend_zone_not_cached' => 'Friend zone is not loaded or cache has expired. Please refresh it in the interface.',
        'building_not_clickable' => 'Building :name is not clickable for collection',
    ],
    'step' => [
        'skipped' => 'Step :step [:type]: SKIPPED (already executed)',
        'ok' => 'Step :step [:type]: OK (:bytes bytes)',
        'error' => 'Step :step [:type]: ERROR - :error',
        'ok_short' => 'Step :step: OK',
        'error_short' => 'Step :step: ERROR - :error',
    ],
    'pickups' => [
        'summary' => 'Collectibles: :collected/:total collected',
        'skipped' => ':skipped skipped (:details)',
        'none_available' => 'Collectibles: nothing to collect',
    ],
    'building_collect' => [
        'collected' => 'Building: collected :name (grid :grid)',
        'gift_received' => 'Building: gift received :name (grid :grid)',
        'not_found' => 'Building: nothing to collect on grid :grid',
        'nothing_to_collect' => 'Building: reward not available yet :name',
    ],
    'build_mine' => [
        'built' => 'Mine: built :name (grid :grid)',
        'unknown_outcome' => 'Mine: build outcome unknown on grid :grid, check manually',
        'game_error' => 'Mine: build failed (:message)',
        'rejected' => [
            'no_deposit_at_grid' => 'Mine: skipped, no deposit found at grid :grid',
            'unknown_deposit_type' => 'Mine: skipped, deposit type :name is not supported',
            'deposit_empty' => 'Mine: skipped, deposit at grid :grid is depleted',
            'grid_occupied' => 'Mine: skipped, grid :grid is occupied by a building',
            'deposit_not_accessible' => 'Mine: skipped, deposit at grid :grid is not accessible',
            'build_queue_full' => 'Mine: skipped, build queue is full',
        ],
    ],
    'upgrade_mine' => [
        'upgraded' => 'Mine: upgraded :name to level :level (grid :grid)',
        'unknown_outcome' => 'Mine: upgrade outcome unknown on grid :grid, check manually',
        'game_error' => 'Mine: upgrade failed (:message)',
        'rejected' => [
            'no_building_at_grid' => 'Mine: skipped, no building found at grid :grid',
            'not_a_mine' => 'Mine: skipped, building :name is not a valid mine',
            'max_level_reached' => 'Mine: skipped, :name is already at max level :level',
            'upgrade_already_in_progress' => 'Mine: skipped, upgrade is already in progress at grid :grid',
            'production_inactive' => 'Mine: skipped, production is inactive at grid :grid',
            'build_queue_full' => 'Mine: skipped, build queue is full',
        ],
    ],
];
