import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc';
import { getTaskResultPresentation, getStepResultPresentation } from '../../resources/js/services/taskResultPresentation.js';

const source = readFileSync(new URL('../../resources/js/views/Tasks.vue', import.meta.url), 'utf8');
const task = (steps, last_result = 'OK: stale success') => ({status: 'completed', last_result, payload: {step_results: steps}});
const skipped = {status: 'skipped', reason: 'Insufficient resources'};
const completed = {status: 'completed', message: 'Queued'};
const failed = {status: 'failed', error: 'Unconfirmed'};

for (const prefix of ['SKIPPED', 'NOOP']) {
    test(`${prefix} legacy summaries are warning in task and manual-step presentation`, () => {
        for (const result of [getTaskResultPresentation(task(undefined, `${prefix}: no reward`)), getStepResultPresentation(null, `${prefix}: no reward`)]) {
            assert.equal(result.kind, 'skipped');
            assert.equal(result.toastType, 'warning');
            assert.equal(result.badgeClass, 'badge-warning');
        }
    });
}

test('semantic skipped overrides stale OK and preserves its reason', () => {
    const result = getTaskResultPresentation(task([skipped]));
    assert.equal(result.kind, 'skipped');
    assert.equal(result.detail, skipped.reason);
    assert.equal(result.toastKey, 'tasks.toast.action_skipped');
    assert.notEqual(result.badgeClass, 'badge-success');
});

test('semantic completed remains green even with stale legacy skip', () => {
    const result = getTaskResultPresentation(task([completed], 'SKIPPED: stale'));
    assert.equal(result.kind, 'completed');
    assert.equal(result.toastType, 'success');
    assert.equal(result.badgeClass, 'badge-success');
    assert.equal(result.detail, 'Queued');
    assert.equal(getStepResultPresentation(completed).toastKey, 'tasks.toast.action_completed');
});

test('mixed results are partial warning; all failures are errors', () => {
    for (const steps of [[completed, skipped], [completed, failed]]) {
        const result = getTaskResultPresentation(task(steps));
        assert.equal(result.kind, 'partial');
        assert.equal(result.toastType, 'warning');
        assert.equal(result.badgeClass, 'badge-warning');
    }
    assert.equal(getTaskResultPresentation(task([failed, skipped])).kind, 'failed');
    assert.equal(getStepResultPresentation(failed).toastType, 'error');
});

test('legacy OK, ERROR, FAILED, PARTIAL and WARNING keep stable meanings', () => {
    for (const [prefix, kind] of [['OK', 'completed'], ['ERROR', 'failed'], ['FAILED', 'failed'], ['PARTIAL', 'partial'], ['WARNING', 'unknown']]) {
        assert.equal(getTaskResultPresentation(task([], `  ${prefix}: detail`)).kind, kind);
    }
    assert.equal(getTaskResultPresentation(task([], 'old successful result')).kind, 'completed');
});

test('translated prose and embedded words never classify a result', () => {
    for (const summary of ['Пропущено', 'Skipped', 'OK: reason mentions SKIPPED: and ERROR']) {
        assert.equal(getTaskResultPresentation(task([], summary)).kind, 'completed');
    }
    assert.equal(getStepResultPresentation(null, 'Action completed').kind, 'unknown');
});

test('missing or malformed manual result cannot default to success', () => {
    for (const value of [undefined, null, {}, {status: 'unexpected'}]) {
        assert.equal(getStepResultPresentation(value).toastType, 'warning');
    }
    for (const steps of [[{}], [null], Array(1)]) {
        assert.notEqual(getTaskResultPresentation(task(steps)).badgeClass, 'badge-success');
    }
});

test('sparse PHP step_results object retains semantic skip instead of stale OK', () => {
    const value = task({3: skipped});
    const result = getTaskResultPresentation(value);
    assert.equal(result.kind, 'skipped');
    assert.equal(result.toastType, 'warning');
    assert.equal(result.detail, skipped.reason);
});

test('task lifecycle failure, cancellation and active state override old success', () => {
    for (const [status, kind] of [['failed', 'failed'], ['cancelled', 'cancelled'], ['queued', 'unknown'], ['running', 'unknown']]) {
        const result = getTaskResultPresentation({...task([completed]), status});
        assert.equal(result.kind, kind);
        assert.notEqual(result.toastType, 'success');
    }
});

function extract(start, end) {
    const a = source.indexOf(start);
    const b = source.indexOf(end, a);
    assert.ok(a >= 0 && b > a);
    return source.slice(a, b);
}

function emitTask(taskValue) {
    const calls = [];
    const fn = new Function('getTaskResultPresentation', 'showToast', 't', extract('        const showTaskExecutionResult = ', '        const pollTaskExecution = ') + '\nreturn showTaskExecutionResult;');
    fn(getTaskResultPresentation, (...args) => calls.push(args), key => key)(taskValue);
    return calls[0];
}

test('actual shared task toast and badge render skipped as warning and completed as success', () => {
    const badge = new Function('getTaskResultPresentation', 't', extract('        const getTaskLastResultBadge = ', '        const getNextRunDate = ') + '\nreturn getTaskLastResultBadge;')(getTaskResultPresentation, key => key);
    for (const steps of [[skipped], [completed, skipped]]) {
        assert.equal(emitTask(task(steps))[1], 'warning');
        assert.equal(badge(task(steps)).class, 'badge-warning');
    }
    assert.equal(emitTask(task([completed]))[1], 'success');
    assert.equal(badge(task([completed])).class, 'badge-success');
    assert.equal(badge(task([skipped], null)).class, 'badge-warning');
    assert.equal(badge(task({3: skipped}, null)).class, 'badge-warning');
    assert.equal(badge(null), null);
});

test('poll and synchronous manual task paths both call shared presentation', () => {
    const poll = extract('        const pollTaskExecution = ', '        const stopTaskNow = ');
    const manual = extract('        const runTaskNow = ', '        const currentTimeMs = ');
    assert.match(poll, /showTaskExecutionResult\(updatedTask\)/);
    assert.match(manual, /showTaskExecutionResult\(currentTask, res.data.message\)/);
    assert.doesNotMatch(poll + manual, /tasks\.toast\.run_success/);
});

test('actual manual-step path uses response or indexed semantic result, never whole-task old success', async () => {
    const code = extract('        const runSingleActionNow = ', '        const runTaskNow = ');
    const build = new Function('axios', 'tasks', 'executingActions', 'getStepResultPresentation', 'showToast', 't', code + '\nreturn runSingleActionNow;');
    for (const response of [
        {step_result: skipped},
        {task: {id: 1, payload: {step_results: [completed, skipped]}}},
        {task: {id: 1, payload: {step_results: {1: skipped}}}},
        {message: 'NOOP: unavailable'},
        {task: {id: 1, last_result: 'OK: earlier run'}},
        {step_result: completed},
        {step_result: failed},
    ]) {
        const calls = [];
        const run = build({post: async () => ({data: response})}, {value: []}, {value: {}}, getStepResultPresentation, (...args) => calls.push(args), key => key);
        await run({task: {id: 1}, actionIndex: 1});
        assert.equal(calls.length, 1);
        assert.equal(calls[0][1], response.step_result === completed ? 'success' : response.step_result === failed ? 'error' : 'warning');
    }
});

test('Tasks component still compiles without template or script errors', () => {
    const {descriptor, errors} = parse(source, {filename: 'Tasks.vue'});
    assert.deepEqual(errors, []);
    assert.ok(compileScript(descriptor, {id: 'task-result-presentation'}).content);
    assert.deepEqual(compileTemplate({source: descriptor.template.content, filename: 'Tasks.vue', id: 'task-result-presentation'}).errors, []);
});
