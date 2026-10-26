// Presentation only: semantic outcomes take precedence over legacy summary text.
const styles = {
    completed: ['success', 'tasks.toast.run_success', 'tasks.success', 'badge-success'],
    skipped: ['warning', 'tasks.toast.action_skipped', 'tasks.toast.action_skipped', 'badge-warning'],
    partial: ['warning', 'tasks.partial', 'tasks.partial', 'badge-warning'],
    failed: ['error', 'tasks.toast.run_error', 'tasks.error', 'badge-danger'],
    cancelled: ['warning', 'tasks.status.cancelled', 'tasks.status.cancelled', 'badge-neutral'],
    unknown: ['warning', 'tasks.warning', 'tasks.warning', 'badge-warning'],
};

function legacyKind(summary) {
    // Never infer an outcome from translated prose or a word inside a reason.
    const prefix = typeof summary === 'string' ? summary.trimStart().match(/^([A-Z]+):/)?.[1] : null;
    return ({ OK: 'completed', SKIPPED: 'skipped', NOOP: 'skipped', ERROR: 'failed',
        FAILED: 'failed', PARTIAL: 'partial', WARNING: 'unknown' })[prefix] ?? null;
}

function presentation(kind, detail = '', action = false) {
    const [toastType, taskToastKey, badgeKey, badgeClass] = styles[kind];
    const toastKey = action && kind === 'completed' ? 'tasks.toast.action_completed'
        : action && kind === 'failed' ? 'tasks.toast.action_failed' : taskToastKey;
    return { kind, toastType, toastKey, badgeKey, badgeClass, detail };
}

function stepKind(step) {
    return ['completed', 'skipped', 'failed'].includes(step?.status) ? step.status : 'unknown';
}

function stepDetail(step) {
    return [step?.reason, step?.error, step?.message].find(value => typeof value === 'string' && value.length > 0) || '';
}

export function getStepResultPresentation(step, legacySummary = '') {
    const kind = step ? stepKind(step) : legacyKind(legacySummary) || 'unknown';
    return presentation(kind, stepDetail(step) || (typeof legacySummary === 'string' ? legacySummary : ''), true);
}

export function getTaskResultPresentation(task) {
    const summary = typeof task?.last_result === 'string' ? task.last_result : '';
    if (task?.status === 'cancelled') return presentation('cancelled', summary);
    if (task?.status === 'failed') return presentation('failed', summary);
    // While a run is active, stale summaries must not turn it green.
    if (['queued', 'running', 'pending'].includes(task?.status)) return presentation('unknown', summary);

    const rawSteps = task?.payload?.step_results;
    // PHP emits an object for sparse numeric keys after a manual single-step run.
    const steps = Array.isArray(rawSteps) ? Array.from(rawSteps)
        : rawSteps && typeof rawSteps === 'object' ? Object.values(rawSteps) : [];
    if (steps.length > 0) {
        const kinds = Array.from(steps, stepKind);
        const completed = kinds.includes('completed');
        const failed = kinds.includes('failed');
        const skipped = kinds.includes('skipped');
        const unknown = kinds.includes('unknown');
        const kind = failed ? (completed ? 'partial' : 'failed')
            : unknown ? 'unknown'
            : skipped ? (completed ? 'partial' : 'skipped') : 'completed';
        // Prefer semantic details too: an old OK summary must not contradict a skip.
        const detail = steps.map(stepDetail).filter(Boolean).join('; ');
        return presentation(kind, detail || (kind === 'completed' ? summary : ''));
    }

    const kind = legacyKind(summary) || (task?.status === 'completed' ? 'completed' : 'unknown');
    return presentation(kind, summary);
}
