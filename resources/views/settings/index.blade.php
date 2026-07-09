@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-white">Settings</h1>
            <p class="text-white/40 mt-1">Configure your TSO Manager preferences</p>
        </div>
    </div>

    <div class="space-y-6">
        {{-- General Settings --}}
        <div class="glass-card p-6">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-white">General</h2>
            </div>

            <form action="{{ route('settings.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="space-y-5">
                    {{-- Sync Interval --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <div>
                            <label class="block text-sm font-medium text-white/60">Auto-Sync Interval</label>
                            <p class="text-xs text-white/30 mt-0.5">How often to automatically sync account data</p>
                        </div>
                        <div class="md:col-span-2">
                            <div class="relative max-w-xs">
                                <select name="sync_interval" class="glass-select w-full">
                                    <option value="5" class="bg-dark-900" {{ ($settings['sync_interval'] ?? 30) == 5 ? 'selected' : '' }}>Every 5 minutes</option>
                                    <option value="15" class="bg-dark-900" {{ ($settings['sync_interval'] ?? 30) == 15 ? 'selected' : '' }}>Every 15 minutes</option>
                                    <option value="30" class="bg-dark-900" {{ ($settings['sync_interval'] ?? 30) == 30 ? 'selected' : '' }}>Every 30 minutes</option>
                                    <option value="60" class="bg-dark-900" {{ ($settings['sync_interval'] ?? 30) == 60 ? 'selected' : '' }}>Every hour</option>
                                    <option value="0" class="bg-dark-900" {{ ($settings['sync_interval'] ?? 30) == 0 ? 'selected' : '' }}>Manual only</option>
                                </select>
                                <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                    <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-white/5"></div>

                    {{-- Log Retention --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <div>
                            <label class="block text-sm font-medium text-white/60">Log Retention</label>
                            <p class="text-xs text-white/30 mt-0.5">How long to keep activity logs</p>
                        </div>
                        <div class="md:col-span-2">
                            <div class="relative max-w-xs">
                                <select name="log_retention_days" class="glass-select w-full">
                                    <option value="7" class="bg-dark-900" {{ ($settings['log_retention_days'] ?? 30) == 7 ? 'selected' : '' }}>7 days</option>
                                    <option value="14" class="bg-dark-900" {{ ($settings['log_retention_days'] ?? 30) == 14 ? 'selected' : '' }}>14 days</option>
                                    <option value="30" class="bg-dark-900" {{ ($settings['log_retention_days'] ?? 30) == 30 ? 'selected' : '' }}>30 days</option>
                                    <option value="90" class="bg-dark-900" {{ ($settings['log_retention_days'] ?? 30) == 90 ? 'selected' : '' }}>90 days</option>
                                    <option value="0" class="bg-dark-900" {{ ($settings['log_retention_days'] ?? 30) == 0 ? 'selected' : '' }}>Forever</option>
                                </select>
                                <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                    <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-white/5"></div>

                    {{-- Timezone --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <div>
                            <label class="block text-sm font-medium text-white/60">Timezone</label>
                            <p class="text-xs text-white/30 mt-0.5">Timezone for scheduled tasks and logs</p>
                        </div>
                        <div class="md:col-span-2">
                            <input type="text" name="timezone" value="{{ $settings['timezone'] ?? config('app.timezone', 'UTC') }}"
                                   placeholder="e.g. Europe/Moscow"
                                   class="glass-input max-w-xs w-full">
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-5 border-t border-white/5 flex justify-end">
                    <button type="submit" class="btn-primary flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        Save Settings
                    </button>
                </div>
            </form>
        </div>

        {{-- Danger Zone --}}
        <div class="glass-card p-6 border-red-500/20">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-red-500 to-rose-600 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-red-400">Danger Zone</h2>
            </div>

            <div class="space-y-4">
                {{-- Clear logs --}}
                <div class="flex items-center justify-between p-4 rounded-xl border border-white/5 hover:border-red-500/20 transition-all duration-300">
                    <div>
                        <p class="text-sm font-medium text-white/60">Clear All Logs</p>
                        <p class="text-xs text-white/30 mt-0.5">Permanently delete all activity logs</p>
                    </div>
                    <form action="{{ route('settings.clearLogs') }}" method="POST"
                          onsubmit="return confirm('Are you sure you want to delete ALL logs? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-danger btn-sm flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                            Clear Logs
                        </button>
                    </form>
                </div>

                {{-- Stop all tasks --}}
                <div class="flex items-center justify-between p-4 rounded-xl border border-white/5 hover:border-red-500/20 transition-all duration-300">
                    <div>
                        <p class="text-sm font-medium text-white/60">Stop All Tasks</p>
                        <p class="text-xs text-white/30 mt-0.5">Deactivate all scheduled tasks across all accounts</p>
                    </div>
                    <form action="{{ route('settings.stopAllTasks') }}" method="POST"
                          onsubmit="return confirm('Are you sure you want to stop ALL scheduled tasks?')">
                        @csrf
                        <button type="submit" class="btn-danger btn-sm flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 7.5A2.25 2.25 0 0 1 7.5 5.25h9a2.25 2.25 0 0 1 2.25 2.25v9a2.25 2.25 0 0 1-2.25 2.25h-9a2.25 2.25 0 0 1-2.25-2.25v-9Z" />
                            </svg>
                            Stop All
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
