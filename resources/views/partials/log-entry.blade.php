{{-- Log Entry Component --}}
{{-- Expected: $log (object with: id, account_id, account_name or account relation, level, message, created_at) --}}
@php
    $levelBadge = match($log->level ?? 'info') {
        'success' => 'badge-success',
        'info'    => 'badge-info',
        'warning' => 'badge-warning',
        'error'   => 'badge-danger',
        default   => 'badge-neutral',
    };
    $levelIcon = match($log->level ?? 'info') {
        'success' => '<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />',
        'error'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />',
        'warning' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />',
        default   => '<path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />',
    };
    $accountName = $log->account->nickname ?? $log->account->username ?? $log->account_name ?? 'System';
@endphp

<div class="flex items-start gap-3 px-4 py-3 rounded-xl hover:bg-white/[0.02] transition-all duration-200 group">
    {{-- Timestamp --}}
    <span class="text-[11px] text-white/20 font-mono whitespace-nowrap pt-0.5 flex-shrink-0 w-36">
        {{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i:s') }}
    </span>

    {{-- Account name --}}
    <span class="text-xs text-white/40 w-24 truncate flex-shrink-0 pt-0.5">
        {{ $accountName }}
    </span>

    {{-- Level badge --}}
    <span class="badge {{ $levelBadge }} text-[10px] flex-shrink-0">
        <svg class="w-3 h-3 mr-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">{!! $levelIcon !!}</svg>
        {{ strtoupper($log->level ?? 'INFO') }}
    </span>

    {{-- Message --}}
    <p class="text-sm text-white/60 group-hover:text-white/80 transition-colors flex-1 leading-relaxed">
        {{ $log->message }}
    </p>
</div>
