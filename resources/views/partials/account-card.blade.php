{{-- Account Card Component --}}
{{-- Expected: $account (object with: id, nickname, username, region, status, last_sync_at, zone_data) --}}
@php
    $statusColors = [
        'online'  => 'from-emerald-500 to-teal-500',
        'syncing' => 'from-amber-500 to-orange-500',
        'error'   => 'from-red-500 to-rose-500',
        'offline' => 'from-gray-500 to-gray-600',
    ];
    $statusClass = $statusColors[$account->status ?? 'offline'] ?? $statusColors['offline'];
    $statusDot   = 'status-' . ($account->status ?? 'offline');
    $buildingCount = null;
    if (!empty($account->zone_data)) {
        $zoneData = is_string($account->zone_data) ? json_decode($account->zone_data, true) : $account->zone_data;
        $buildingCount = is_array($zoneData) && isset($zoneData['buildings']) ? count($zoneData['buildings']) : null;
    }
@endphp

<div class="glass-card overflow-hidden group hover:border-white/20 transition-all duration-500 animate-fade-in-up"
     style="animation-delay: {{ ($loop->index ?? 0) * 100 }}ms">

    {{-- Gradient accent stripe --}}
    <div class="h-1 bg-gradient-to-r {{ $statusClass }}"></div>

    <div class="p-5">
        {{-- Header row --}}
        <div class="flex items-start justify-between mb-4">
            <div class="flex items-center gap-3">
                {{-- Avatar --}}
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br {{ $statusClass }} flex items-center justify-center text-white font-bold text-sm shadow-lg">
                    {{ strtoupper(substr($account->nickname ?? $account->username ?? '?', 0, 2)) }}
                </div>
                <div>
                    <h3 class="font-semibold text-white group-hover:text-emerald-400 transition-colors">
                        {{ $account->nickname ?? $account->username }}
                    </h3>
                    <p class="text-xs text-white/40">{{ $account->username }}</p>
                </div>
            </div>

            {{-- Status dot --}}
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full {{ $statusDot }}"></div>
                <span class="text-xs text-white/40 capitalize">{{ $account->status ?? 'offline' }}</span>
            </div>
        </div>

        {{-- Info row --}}
        <div class="flex items-center gap-3 mb-4">
            {{-- Region badge --}}
            <span class="badge badge-info">
                <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                </svg>
                {{ strtoupper($account->region ?? 'N/A') }}
            </span>

            {{-- Building count --}}
            @if($buildingCount !== null)
                <span class="badge badge-neutral">
                    <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                    </svg>
                    {{ $buildingCount }} buildings
                </span>
            @endif

            {{-- Last sync --}}
            @if($account->last_sync_at)
                <span class="text-xs text-white/30 flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    {{ \Carbon\Carbon::parse($account->last_sync_at)->diffForHumans() }}
                </span>
            @endif
        </div>

        {{-- Action buttons --}}
        <div class="flex items-center gap-2 pt-3 border-t border-white/5">
            <form action="{{ route('accounts.sync', $account->id) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="btn-secondary btn-sm flex items-center gap-1.5 hover:border-emerald-500/30 hover:text-emerald-400">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                    </svg>
                    Sync
                </button>
            </form>

            <a href="{{ route('accounts.show', $account->id) }}" class="btn-secondary btn-sm flex items-center gap-1.5 hover:border-blue-500/30 hover:text-blue-400">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                View
            </a>

            <form action="{{ route('accounts.destroy', $account->id) }}" method="POST" class="inline ml-auto"
                  onsubmit="return confirm('Are you sure you want to delete this account?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-secondary btn-sm text-red-400/60 hover:text-red-400 hover:border-red-500/30">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                </button>
            </form>
        </div>
    </div>
</div>
