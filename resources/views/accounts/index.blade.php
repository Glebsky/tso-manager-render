@extends('layouts.app')

@section('title', 'Accounts')

@section('content')
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-white">Accounts</h1>
            <p class="text-white/40 mt-1">Manage your TSO game accounts</p>
        </div>
    </div>

    {{-- Add Account Form --}}
    <div class="glass-card p-6 mb-8">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
            </div>
            <h2 class="text-lg font-semibold text-white">Add New Account</h2>
        </div>

        <form action="{{ route('accounts.store') }}" method="POST">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                {{-- Username --}}
                <div>
                    <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Username (Email)</label>
                    <input type="email" name="username" value="{{ old('username') }}" required
                           placeholder="user@example.com"
                           class="glass-input w-full">
                </div>

                {{-- Password --}}
                <div>
                    <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Password</label>
                    <input type="password" name="password" required
                           placeholder="••••••••"
                           class="glass-input w-full">
                </div>

                {{-- Region --}}
                <div>
                    <label class="block text-xs font-medium text-white/40 mb-2 uppercase tracking-wider">Region</label>
                    <div class="relative">
                        <select name="region" required class="glass-select w-full">
                            <option value="" disabled selected class="bg-dark-900">Select Region</option>
                            <option value="ru" class="bg-dark-900">🇷🇺 Russia (RU)</option>
                            <option value="en" class="bg-dark-900">🇬🇧 English (EN)</option>
                            <option value="de" class="bg-dark-900">🇩🇪 Germany (DE)</option>
                            <option value="fr" class="bg-dark-900">🇫🇷 France (FR)</option>
                            <option value="pl" class="bg-dark-900">🇵🇱 Poland (PL)</option>
                            <option value="es" class="bg-dark-900">🇪🇸 Spain (ES)</option>
                            <option value="it" class="bg-dark-900">🇮🇹 Italy (IT)</option>
                            <option value="cz" class="bg-dark-900">🇨🇿 Czech (CZ)</option>
                            <option value="tr" class="bg-dark-900">🇹🇷 Turkey (TR)</option>
                            <option value="pt" class="bg-dark-900">🇵🇹 Portugal (PT)</option>
                            <option value="nl" class="bg-dark-900">🇳🇱 Netherlands (NL)</option>
                            <option value="hu" class="bg-dark-900">🇭🇺 Hungary (HU)</option>
                        </select>
                        <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="flex items-end">
                    <button type="submit" class="btn-primary w-full flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add Account
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Accounts List --}}
    <div>
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                </svg>
                All Accounts
                @if(isset($accounts))
                    <span class="badge badge-neutral text-[10px]">{{ $accounts->count() }}</span>
                @endif
            </h2>
        </div>

        @if(isset($accounts) && $accounts->count() > 0)
            <div class="space-y-3">
                @foreach($accounts as $account)
                    @php
                        $statusColors = [
                            'online'  => 'from-emerald-500 to-teal-500',
                            'syncing' => 'from-amber-500 to-orange-500',
                            'error'   => 'from-red-500 to-rose-500',
                            'offline' => 'from-gray-500 to-gray-600',
                        ];
                        $sc = $statusColors[$account->status ?? 'offline'] ?? $statusColors['offline'];
                        $sd = 'status-' . ($account->status ?? 'offline');
                        $bc = null;
                        if (!empty($account->zone_data)) {
                            $zd = is_string($account->zone_data) ? json_decode($account->zone_data, true) : $account->zone_data;
                            $bc = is_array($zd) && isset($zd['buildings']) ? count($zd['buildings']) : null;
                        }
                    @endphp

                    <div class="glass-card overflow-hidden group hover:border-white/20 transition-all duration-500"
                         x-data="{ expanded: false }">

                        {{-- Gradient accent --}}
                        <div class="h-0.5 bg-gradient-to-r {{ $sc }}"></div>

                        {{-- Main row --}}
                        <div class="flex items-center gap-4 p-5">
                            {{-- Avatar --}}
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br {{ $sc }} flex items-center justify-center text-white font-bold text-sm shadow-lg flex-shrink-0">
                                {{ strtoupper(substr($account->nickname ?? $account->username ?? '?', 0, 2)) }}
                            </div>

                            {{-- Info --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-3">
                                    <h3 class="font-semibold text-white group-hover:text-emerald-400 transition-colors truncate">
                                        {{ $account->nickname ?? $account->username }}
                                    </h3>
                                    <span class="badge badge-info text-[10px]">{{ strtoupper($account->region ?? 'N/A') }}</span>
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-2 h-2 rounded-full {{ $sd }}"></div>
                                        <span class="text-[10px] text-white/30 capitalize">{{ $account->status ?? 'offline' }}</span>
                                    </div>
                                </div>
                                <p class="text-xs text-white/30 mt-0.5">{{ $account->username }}</p>
                            </div>

                            {{-- Meta --}}
                            <div class="hidden lg:flex items-center gap-4 text-xs text-white/30 flex-shrink-0">
                                @if($bc !== null)
                                    <span class="flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                                        </svg>
                                        {{ $bc }} buildings
                                    </span>
                                @endif
                                @if($account->last_sync_at)
                                    <span class="flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        {{ \Carbon\Carbon::parse($account->last_sync_at)->diffForHumans() }}
                                    </span>
                                @endif
                            </div>

                            {{-- Actions --}}
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <form action="{{ route('accounts.sync', $account->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="btn-secondary btn-sm flex items-center gap-1.5 hover:border-emerald-500/30 hover:text-emerald-400" title="Sync Account">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                                        </svg>
                                        Sync
                                    </button>
                                </form>

                                @if($bc !== null)
                                    <button @click="expanded = !expanded" class="btn-secondary btn-sm flex items-center gap-1.5 hover:border-blue-500/30 hover:text-blue-400" title="Toggle Buildings">
                                        <svg class="w-3.5 h-3.5 transition-transform duration-300" :class="expanded && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                        Buildings
                                    </button>
                                @endif

                                <form action="{{ route('accounts.destroy', $account->id) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Delete this account?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-secondary btn-sm text-red-400/60 hover:text-red-400 hover:border-red-500/30" title="Delete Account">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </div>

                        {{-- Expandable Buildings Section --}}
                        @if($bc !== null)
                            <div x-show="expanded" x-collapse x-cloak
                                 class="border-t border-white/5 bg-white/[0.01]">
                                <div class="p-4 space-y-1.5">
                                    <p class="text-xs text-white/30 font-medium uppercase tracking-wider mb-3 px-1">Buildings</p>
                                    @php
                                        $zd = is_string($account->zone_data) ? json_decode($account->zone_data, true) : $account->zone_data;
                                        $buildings = is_array($zd) && isset($zd['buildings']) ? $zd['buildings'] : [];
                                    @endphp
                                    @if(is_array($buildings))
                                        @foreach($buildings as $building)
                                            @include('partials.building-row', ['building' => $building, 'account' => $account])
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="glass-card p-12 text-center">
                <div class="w-16 h-16 rounded-2xl bg-white/5 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white/20" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </div>
                <h3 class="text-white/60 font-medium mb-1">No Accounts Yet</h3>
                <p class="text-white/30 text-sm">Add your first account using the form above.</p>
            </div>
        @endif
    </div>
@endsection
