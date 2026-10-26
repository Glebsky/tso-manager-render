import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc';
import { getActionDeduplicationKey } from '../../resources/js/services/taskDeduplication.js';

const source = readFileSync(new URL('../../resources/js/views/Tasks.vue', import.meta.url), 'utf8');

test('daily login has an account-scoped dedup key independent of UI metadata', () => {
    assert.equal(getActionDeduplicationKey({task_type: 'collect_daily_login_reward', payload: {scope: 'self'}}), 'collect_daily_login_reward:self');
    assert.equal(getActionDeduplicationKey({task_type: 'collect_daily_login_reward', payload: {scope: 'self', task_id: 7}}), 'collect_daily_login_reward:self');
    assert.notEqual(getActionDeduplicationKey({task_type: 'collect_pickups', payload: {}}), 'collect_daily_login_reward:self');
});

test('constructor branch produces the executable nonempty home-zone payload', () => {
    const start = source.indexOf("            if (stepActionType.value === 'collect_daily_login_reward') {");
    const end = source.indexOf("            if (stepActionType.value === 'collect_pickups') {", start);
    assert.ok(start > 0 && end > start);
    const execute = new Function('stepActionType', 'sequenceActions', 'stepDelay', 'showToast', 't', source.slice(start, end));
    const sequence = {value: []};
    execute({value: 'collect_daily_login_reward'}, sequence, {value: 5}, () => {}, x => x);
    assert.deepEqual(sequence.value, [{task_type: 'collect_daily_login_reward', payload: {scope: 'self'}, delay_seconds: 5, meta: {}}]);
});

test('modified Tasks component compiles without template or script errors', () => {
    const {descriptor, errors} = parse(source, {filename: 'Tasks.vue'});
    assert.deepEqual(errors, []);
    assert.ok(compileScript(descriptor, {id: 'daily-login-test'}).content);
    const result = compileTemplate({source: descriptor.template.content, filename: 'Tasks.vue', id: 'daily-login-test'});
    assert.deepEqual(result.errors, []);
});

for (const locale of ['en', 'ru', 'uk']) {
    test(`built translation catalog ${locale} includes daily login labels`, () => {
        const catalog = JSON.parse(readFileSync(new URL(`../../resources/js/lang/generated/${locale}.json`, import.meta.url), 'utf8'));
        for (const key of ['tasks.daily_login_hint', 'tasks.type_label.collect_daily_login_reward']) {
            assert.equal(typeof catalog.ui[key], 'string');
            assert.ok(catalog.ui[key].length > 0);
            assert.notEqual(catalog.ui[key], key);
        }
    });
}

test('frontend build and dev always export backend translations first', () => {
    const pkg = JSON.parse(readFileSync(new URL('../../package.json', import.meta.url), 'utf8'));
    assert.equal(pkg.scripts.prebuild, 'php artisan tso:lang:export-frontend');
    assert.equal(pkg.scripts.predev, pkg.scripts.prebuild);
});
