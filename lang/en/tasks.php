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
    ],
    'step' => [
        'skipped' => 'Step :step [:type]: SKIPPED (already executed)',
        'ok' => 'Step :step [:type]: OK (:bytes bytes)',
    ],
    'log' => [
        'step_success' => 'Sequence task #:id step :step [:type] executed successfully.',
        'task_success' => 'Scheduled [:type] executed successfully. :result',
    ],
];
