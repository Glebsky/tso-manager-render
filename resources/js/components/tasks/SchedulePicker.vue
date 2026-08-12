<template>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5 border-t border-white/5 pt-4">
        <!-- 1. Ежедневный запуск (Время) -->
        <div v-if="scheduleType === 'daily'">
            <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('tasks.run_time_daily') }}</label>
            <input type="time" required :value="runAtTime" @input="$emit('update:runAtTime', $event.target.value)" class="glass-input w-full">
        </div>

        <!-- 2. Одноразовый запуск (Дата и время) -->
        <div v-if="scheduleType === 'once'">
            <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('tasks.run_datetime') }}</label>
            <input type="datetime-local" required :value="runAtDatetime" @input="$emit('update:runAtDatetime', $event.target.value)" class="glass-input w-full">
        </div>

        <!-- 3. Интервальный запуск (Каждые X часов Y минут) -->
        <div v-if="scheduleType === 'interval'" class="col-span-2">
            <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">{{ t('tasks.run_every') }}</label>
            <div class="grid grid-cols-2 gap-3">
                <div class="flex items-center gap-2">
                    <input type="number" min="0" max="23" required :value="intervalHours" @input="$emit('update:intervalHours', Number($event.target.value))" class="glass-input w-full text-center">
                    <span class="text-xs text-white/50">{{ t('tasks.hours') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <input type="number" min="0" max="59" required :value="intervalMinutes" @input="$emit('update:intervalMinutes', Number($event.target.value))" class="glass-input w-full text-center">
                    <span class="text-xs text-white/50">{{ t('tasks.minutes') }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { t } from '../../lang';

defineProps({
    scheduleType: { type: String, required: true },
    runAtTime: { type: String, default: '' },
    runAtDatetime: { type: String, default: '' },
    intervalHours: { type: Number, default: 0 },
    intervalMinutes: { type: Number, default: 30 }
});

defineEmits(['update:runAtTime', 'update:runAtDatetime', 'update:intervalHours', 'update:intervalMinutes']);
</script>
