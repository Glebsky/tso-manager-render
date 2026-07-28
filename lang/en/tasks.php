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
        'insufficient_buffs' => 'Insufficient buffs in star menu (available: :available, required: :required).',
        'buff_not_found' => 'Specified buff not found in star menu inventory.',
        'invalid_friend_id' => 'Invalid friend ID.',
        'friend_not_in_list' => 'Player is not in your friends list.',
        'friend_building_not_found_grid' => 'Building with Grid #:grid not found in friend zone.',
        'friend_zone_not_cached' => 'Friend zone is not loaded or cache has expired. Please refresh it in the interface.',
    ],
    'step' => [
        'skipped' => 'Step :step [:type]: SKIPPED (already executed)',
        'ok' => 'Step :step [:type]: OK (:bytes bytes)',
        'error' => 'Step :step [:type]: ERROR - :error',
        'ok_short' => 'Step :step: OK',
        'error_short' => 'Step :step: ERROR - :error',
    ],
];
