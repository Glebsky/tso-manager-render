import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc';
import { compile } from '@vue/compiler-dom';
import * as Vue from 'vue';
import { renderToString } from '@vue/server-renderer';
import { getTaskActionsList, isInvalidTaskSequence } from '../../resources/js/services/taskActionList.js';

const source = readFileSync(new URL('../../resources/js/views/Tasks.vue', import.meta.url), 'utf8');
const card = readFileSync(new URL('../../resources/js/components/tasks/TaskCard.vue', import.meta.url), 'utf8');
const actions = [
    { task_type: 'send_geologist', payload: { specialist_name: 'Geo' }, meta: { specialist: { name: 'Geo' } } },
    { task_type: 'send_explorer', payload: { specialist_name: 'Explorer' }, meta: { specialist: { name: 'Explorer' } } },
];
const sequence = payload => ({ id: 14, task_type: 'sequence', status: 'pending', schedule_type: 'daily', payload });
const malformed = [14, 24, 25, null, undefined, 'broken', '"double encoded"', {}, { actions: {} },
    { actions: { 0: actions[0] } }, { actions: JSON.stringify(actions) }, { actions: [] },
    { actions: [null] }, { actions: [1] }, { actions: [{}] }, { actions: [{ task_type: 'sequence' }] },
    { actions: Array(1) }];

test('valid sequences preserve exact actions, specialist metadata and order without mutation', () => {
    const task = sequence({ actions });
    const before = JSON.stringify(task);
    assert.equal(getTaskActionsList(task), actions);
    assert.equal(isInvalidTaskSequence(task), false);
    assert.equal(JSON.stringify(task), before);
    for (const status of ['queued', 'running', 'completed', 'failed']) {
        assert.equal(getTaskActionsList({ ...task, status, last_result: 'ERROR: old', payload: { actions, step_results: { 3: { status: 'skipped' } } } }), actions);
    }
});

test('invalid/empty sequences never become fake sequence steps or normalize numeric objects', () => {
    for (const payload of malformed) {
        const task = sequence(payload);
        assert.equal(isInvalidTaskSequence(task), true);
        assert.deepEqual(getTaskActionsList(task), []);
        assert.equal(task.payload, payload);
    }
});

test('single task and absent task keep their existing behavior', () => {
    const task = { task_type: 'send_geologist', payload: { specialist_name: 'Geo' } };
    assert.equal(isInvalidTaskSequence(task), false);
    assert.deepEqual(getTaskActionsList(task), [{ ...task, delay_seconds: 0 }]);
    assert.deepEqual(getTaskActionsList(null), []);
});

function extract(start, end) {
    const a = source.indexOf(start);
    const b = source.indexOf(end, a);
    assert.ok(a >= 0 && b > a);
    return source.slice(a, b);
}

test('Tasks and TaskCard use the shared helper and warning/disabled bindings', () => {
    assert.match(source, /import \{ getTaskActionsList, isInvalidTaskSequence \} from '..\/services\/taskActionList'/);
    assert.doesNotMatch(source, /const getTaskActionsList\s*=/);
    assert.match(source, /:getTaskActionsList="getTaskActionsList"/);
    assert.match(card, /import \{ isInvalidTaskSequence \} from '..\/..\/services\/taskActionList'/);
    const computedSource = card.match(/const invalidSequence = computed\(\(\) => (.*)\);/)[1];
    const actualPredicate = new Function('props', 'isInvalidTaskSequence', `return ${computedSource};`);
    assert.equal(actualPredicate({ task: sequence(14) }, isInvalidTaskSequence), true);
    assert.equal(actualPredicate({ task: sequence({ actions }) }, isInvalidTaskSequence), false);
    for (const event of ['execute', 'edit']) {
        assert.match(card, new RegExp(`@click="\\$emit\\('${event}', task\\)" :disabled="invalidSequence"`));
    }
    assert.match(card, /:disabled="invalidSequence \|\| isRunning \|\| isActionExecuting/);
    assert.match(card, /v-if="invalidSequence" role="alert"/);
});

test('actual edit/manual-run/run handlers reject invalid sequence before state changes or HTTP', async () => {
    for (const payload of malformed) {
        const task = sequence(payload);
        for (const [name, end, arg] of [
            ['editTask', '        const scheduleTask = ', task],
            ['runSingleActionNow', '        const runTaskNow = ', { task, actionIndex: 0 }],
            ['runTaskNow', '        const currentTimeMs = ', task],
        ]) {
            const warnings = [];
            const fn = new Function('isInvalidTaskSequence', 'showToast', 't', 'tasks',
                extract(`        const ${name} = `, end) + `\nreturn ${name};`)(
                isInvalidTaskSequence, (...args) => warnings.push(args), key => key, { value: [task] });
            await fn(arg);
            assert.deepEqual(warnings, [['tasks.invalid_sequence', 'warning']]);
        }
    }
});

test('editor save rejects a sequence invalidated after opening without saving empty actions', async () => {
    const start = source.indexOf('        const scheduleTask = ');
    const end = source.indexOf('            scheduling.value = true;', start);
    assert.ok(start >= 0 && end > start);
    const code = source.slice(start, end) + '\n}; return scheduleTask;';
    for (const payload of malformed) {
        const warnings = [];
        const fn = new Function('editingTaskId', 'tasks', 'isInvalidTaskSequence', 'showToast', 't', code)(
            { value: 14 }, { value: [sequence(payload)] }, isInvalidTaskSequence,
            (...args) => warnings.push(args), key => key);
        await fn();
        assert.deepEqual(warnings, [['tasks.invalid_sequence', 'warning']]);
    }
});

const template = parse(card).descriptor.template.content;
const render = new Function('Vue', compile(template, { mode: 'function', prefixIdentifiers: true }).code)(Vue);
async function renderCard(task) {
    const context = {
        task, isDragging: false, isDragOver: false, dropPosition: null, canDrag: false,
        isRunning: false, isExpanded: true, isStopping: false, invalidSequence: isInvalidTaskSequence(task),
        typeLabels: {}, taskPrimaryIcon: 'icon', typeIcons: {}, t: key => key,
        getTaskActionsList, getTaskLastResultBadge: () => null,
        getTaskNextRunText: () => ({ nextRunTime: '' }), formatDateTime: () => '',
        formatInterval: () => '', utcTimeToLocal: () => '',
        getSpecialistInfo: (_task, action) => ({ name: action.payload.specialist_name, icon: null, subTaskLabel: '' }),
        getActionStepStatus: () => 'pending', getActionStepError: () => '', isActionExecuting: () => false,
        onDragStart: () => {}, onDragEnd: () => {},
    };
    return renderToString(Vue.createSSRApp({ render, setup: () => context }));
}

test('actual TaskCard template renders specialists for valid data and alert/no fake play for invalid', async () => {
    const valid = await renderCard(sequence({ actions }));
    assert.match(valid, /Geo/);
    assert.match(valid, /Explorer/);
    assert.doesNotMatch(valid, /role="alert"/);
    assert.equal((valid.match(/tasks.run_single_action/g) || []).length, 2);
    for (const payload of malformed) {
        const html = await renderCard(sequence(payload));
        assert.match(html, /role="alert"[^>]*>\s*tasks.invalid_sequence/);
        assert.doesNotMatch(html, /tasks.run_single_action/);
        assert.doesNotMatch(html, />sequence</);
        assert.equal((html.match(/disabled/g) || []).length >= 2, true);
    }
});

test('both actual Vue SFCs compile', () => {
    for (const [filename, content] of [['Tasks.vue', source], ['TaskCard.vue', card]]) {
        const { descriptor, errors } = parse(content, { filename });
        assert.deepEqual(errors, []);
        assert.ok(compileScript(descriptor, { id: filename }).content);
        assert.deepEqual(compileTemplate({ source: descriptor.template.content, filename, id: filename }).errors, []);
    }
});

test('warning key exists exactly once in en/ru/uk UI dictionaries', () => {
    for (const locale of ['en', 'ru', 'uk']) {
        const php = readFileSync(new URL(`../../lang/${locale}/ui.php`, import.meta.url), 'utf8');
        assert.equal((php.match(/'tasks.invalid_sequence'\s*=>/g) || []).length, 1);
        const message = php.match(/'tasks.invalid_sequence'\s*=>\s*'([^']+)'/)[1];
        assert.doesNotMatch(message, /\?{3}|\uFFFD/);
        assert.ok(message.length > 30);
    }
});
