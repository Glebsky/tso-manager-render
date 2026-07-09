@extends('layouts.app')

@section('title', 'Account: ' . ($account->nickname ?? $account->username))

@section('content')
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-4">
            <a href="{{ route('accounts.index') }}" class="btn-secondary flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Back
            </a>
            <div>
                <h1 class="text-3xl font-bold text-white">{{ $account->nickname ?? $account->username }}</h1>
                <p class="text-white/40 mt-1">{{ $account->username }} · Region {{ strtoupper($account->region ?? 'N/A') }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <form action="{{ route('accounts.sync', $account->id) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="btn-primary flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                    </svg>
                    Sync Now
                </button>
            </form>
        </div>
    </div>

    {{-- Account Info Card --}}
    @php
        $statusColors = [
            'online'  => 'from-emerald-500 to-teal-500',
            'syncing' => 'from-amber-500 to-orange-500',
            'error'   => 'from-red-500 to-rose-500',
            'offline' => 'from-gray-500 to-gray-600',
        ];
        $sc = $statusColors[$account->status ?? 'offline'] ?? $statusColors['offline'];
    @endphp

    <div class="glass-card overflow-hidden mb-8">
        <div class="h-1 bg-gradient-to-r {{ $sc }}"></div>
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div>
                    <p class="text-xs text-white/30 uppercase tracking-wider mb-1">Status</p>
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full status-{{ $account->status ?? 'offline' }}"></div>
                        <span class="text-white font-medium capitalize">{{ $account->status ?? 'offline' }}</span>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-white/30 uppercase tracking-wider mb-1">Region</p>
                    <span class="text-white font-medium">{{ strtoupper($account->region ?? 'N/A') }}</span>
                </div>
                <div>
                    <p class="text-xs text-white/30 uppercase tracking-wider mb-1">Last Sync</p>
                    <span class="text-white font-medium">
                        {{ $account->last_sync_at ? \Carbon\Carbon::parse($account->last_sync_at)->diffForHumans() : 'Never' }}
                    </span>
                </div>
                <div>
                    <p class="text-xs text-white/30 uppercase tracking-wider mb-1">Buildings</p>
                    @php
                        $buildings = [];
                        if (!empty($account->zone_data)) {
                            $zd = is_string($account->zone_data) ? json_decode($account->zone_data, true) : $account->zone_data;
                            $buildings = is_array($zd) && isset($zd['buildings']) ? $zd['buildings'] : [];
                        }
                    @endphp
                    <span class="text-white font-medium">{{ count($buildings) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Buildings List --}}
    <div>
        <h2 class="text-lg font-semibold text-white flex items-center gap-2 mb-5">
            <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
            </svg>
            Buildings
            <span class="badge badge-neutral text-[10px]">{{ count($buildings) }}</span>
        </h2>

        @if(count($buildings) > 0)
            <div class="glass-card p-4 space-y-1.5">
                @foreach($buildings as $building)
                    @include('partials.building-row', ['building' => $building, 'account' => $account])
                @endforeach
            </div>
        @else
            <div class="glass-card p-12 text-center">
                <div class="w-16 h-16 rounded-2xl bg-white/5 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white/20" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                    </svg>
                </div>
                <h3 class="text-white/60 font-medium mb-1">No Buildings Found</h3>
                <p class="text-white/30 text-sm">Sync the account to load building data.</p>
            </div>
        @endif
    </div>
@endsection
