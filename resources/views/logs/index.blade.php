@extends('layouts.app')

@section('title', 'Logs')

@section('content')
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-white">Activity Logs</h1>
            <p class="text-white/40 mt-1">Monitor all system and account activity</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="location.reload()" class="btn-secondary flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                </svg>
                Refresh
            </button>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="glass-card p-4 mb-6">
        <form method="GET" action="{{ route('logs.index') }}" class="flex flex-wrap items-end gap-4">
            {{-- Account filter --}}
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Account</label>
                <div class="relative">
                    <select name="account_id" class="glass-select w-full">
                        <option value="" class="bg-dark-900">All Accounts</option>
                        @if(isset($accounts))
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" class="bg-dark-900" {{ request('account_id') == $acc->id ? 'selected' : '' }}>
                                    {{ $acc->nickname ?? $acc->username }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                    <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Level filter --}}
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Log Level</label>
                <div class="relative">
                    <select name="level" class="glass-select w-full">
                        <option value="" class="bg-dark-900">All Levels</option>
                        <option value="success" class="bg-dark-900" {{ request('level') == 'success' ? 'selected' : '' }}>✅ Success</option>
                        <option value="info" class="bg-dark-900" {{ request('level') == 'info' ? 'selected' : '' }}>ℹ️ Info</option>
                        <option value="warning" class="bg-dark-900" {{ request('level') == 'warning' ? 'selected' : '' }}>⚠️ Warning</option>
                        <option value="error" class="bg-dark-900" {{ request('level') == 'error' ? 'selected' : '' }}>❌ Error</option>
                    </select>
                    <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <div>
                <button type="submit" class="btn-secondary flex items-center gap-2 py-3">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
                    </svg>
                    Filter
                </button>
            </div>

            @if(request('account_id') || request('level'))
                <div>
                    <a href="{{ route('logs.index') }}" class="btn-secondary flex items-center gap-2 py-3 text-white/40 hover:text-white">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                        Clear
                    </a>
                </div>
            @endif
        </form>
    </div>

    {{-- Log Entries --}}
    <div class="glass-card overflow-hidden">
        {{-- Header --}}
        <div class="flex items-center gap-3 px-5 py-3 border-b border-white/5 bg-white/[0.02]">
            <span class="text-[10px] font-semibold text-white/20 uppercase tracking-wider w-36">Timestamp</span>
            <span class="text-[10px] font-semibold text-white/20 uppercase tracking-wider w-24">Account</span>
            <span class="text-[10px] font-semibold text-white/20 uppercase tracking-wider w-20">Level</span>
            <span class="text-[10px] font-semibold text-white/20 uppercase tracking-wider flex-1">Message</span>
        </div>

        {{-- Scrollable log area --}}
        <div class="max-h-[calc(100vh-24rem)] overflow-y-auto" id="log-container">
            @if(isset($logs) && $logs->count() > 0)
                <div class="divide-y divide-white/[0.03]">
                    @foreach($logs as $log)
                        @include('partials.log-entry', ['log' => $log])
                    @endforeach
                </div>
            @else
                <div class="p-12 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-white/5 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white/20" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                        </svg>
                    </div>
                    <h3 class="text-white/60 font-medium mb-1">No Log Entries</h3>
                    <p class="text-white/30 text-sm">Activity logs will appear here as actions are performed.</p>
                </div>
            @endif
        </div>

        {{-- Pagination --}}
        @if(isset($logs) && method_exists($logs, 'links'))
            <div class="px-5 py-3 border-t border-white/5 bg-white/[0.02]">
                {{ $logs->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    // Auto-scroll to bottom of logs
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('log-container');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    });
</script>
@endpush
