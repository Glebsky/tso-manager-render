/**
 * Task Sequence Action Deduplication Service
 *
 * Provides pure functions to generate deduplication keys for sequence actions
 * and filter duplicate actions while preserving the original sequence order.
 */

/**
 * Extracts a normalized, collision-free deduplication key for a sequence action.
 * Actions with identical keys represent conflicting/redundant operations
 * targeting the same resource, building, or specialist.
 *
 * @param {Object} action - The sequence action item
 * @returns {string|null} - Deduplication key string or null if action is invalid
 */
export function getActionDeduplicationKey(action) {
    if (!action || typeof action !== 'object') {
        return null;
    }

    const taskType = action.task_type;
    const payload = action.payload || {};

    switch (taskType) {
        case 'apply_buff': {
            const isFriend = payload.target_scope === 'friend' || Boolean(payload.target_player_id);
            const friendId = isFriend ? String(payload.target_player_id || '').trim() : '';
            const scope = isFriend ? `friend:${friendId}` : 'self';

            const rawGrid = payload.grid ?? action.meta?.building?.buildingGrid;
            if (rawGrid === undefined || rawGrid === null || rawGrid === '') {
                return `apply_buff:${scope}:raw:${JSON.stringify(payload)}`;
            }

            return `apply_buff:${scope}:${String(rawGrid).trim()}`;
        }

        case 'stop_production':
        case 'start_production':
        case 'collect_building':
        case 'build_mine':
        case 'upgrade_mine': {
            const rawGrid = payload.grid ?? action.meta?.building?.buildingGrid;
            if (rawGrid === undefined || rawGrid === null || rawGrid === '') {
                return `${taskType}:raw:${JSON.stringify(payload)}`;
            }

            return `${taskType}:${String(rawGrid).trim()}`;
        }

        case 'send_geologist':
        case 'send_explorer': {
            const rawSpecId = payload.unique_id1
                ?? action.meta?.specialist?.uniqueId1
                ?? action.meta?.specialist?.uniqueId;

            if (rawSpecId === undefined || rawSpecId === null || rawSpecId === '') {
                return `${taskType}:raw:${JSON.stringify(payload)}`;
            }

            return `${taskType}:${String(rawSpecId).trim()}`;
        }

        case 'collect_daily_login_reward':
            return 'collect_daily_login_reward:self';

        case 'collect_pickups': {
            const pickupType = String(payload.pickup_type || 'all').trim().toLowerCase();
            return `collect_pickups:${pickupType}`;
        }

        case 'produce_buff': {
            const grid = String(payload.grid ?? '').trim();
            const recipe = String(payload.recipe_name ?? action.meta?.recipe?.name ?? '').trim();
            const amount = Number(payload.amount || 1);
            const stacks = Number(payload.stacks || 1);
            return `produce_buff:${grid}:${recipe}:${amount}:${stacks}`;
        }

        default:
            return `${taskType}:${JSON.stringify(payload)}`;
    }
}

/**
 * Removes duplicate actions from a sequence, preserving the first occurrence of each unique action target.
 * Returns a new array with duplicates removed along with the count of removed items.
 *
 * @param {Array} actions - Array of sequence action objects
 * @returns {{ uniqueActions: Array, removedCount: number }}
 */
export function deduplicateSequenceActions(actions) {
    if (!Array.isArray(actions) || actions.length <= 1) {
        return {
            uniqueActions: Array.isArray(actions) ? [...actions] : [],
            removedCount: 0,
        };
    }

    const seen = new Set();
    const uniqueActions = [];

    for (const action of actions) {
        const key = getActionDeduplicationKey(action);
        if (key && seen.has(key)) {
            continue;
        }

        if (key) {
            seen.add(key);
        }
        uniqueActions.push(action);
    }

    return {
        uniqueActions,
        removedCount: actions.length - uniqueActions.length,
    };
}
