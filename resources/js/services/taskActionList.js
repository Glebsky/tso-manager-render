// Keep malformed sequences distinct from ordinary single-action tasks.
export function isInvalidTaskSequence(task) {
    if (task?.task_type !== 'sequence') return false;
    const actions = task.payload?.actions;
    return !Array.isArray(actions) || actions.length === 0 || !Array.from(actions).every(action =>
        action && typeof action === 'object' && !Array.isArray(action)
        && typeof action.task_type === 'string' && action.task_type.trim().length > 0
        && action.task_type !== 'sequence');
}

export function getTaskActionsList(task) {
    if (!task) return [];
    if (task.task_type === 'sequence') {
        return isInvalidTaskSequence(task) ? [] : task.payload.actions;
    }
    return [{ task_type: task.task_type, payload: task.payload || {}, delay_seconds: 0 }];
}
