@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-white">Dashboard</h1>
            <p class="text-white/40 mt-1">{{ now()->format('l, F j, Y') }}</p>
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

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        {{-- Total Accounts --}}
        <div class="glass-card p-5 group hover:border-white/20 transition-all duration-500">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-blue-500/20">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </div>
                <span class="badge badge-info text-[10px]">Total</span>
            </div>
            <p class="text-2xl font-bold text-white">{{ $stats['total_accounts'] ?? 0 }}</p>
            <p class="text-xs text-white/30 mt-1">Registered Accounts</p>
        </div>

        {{-- Active Tasks --}}
        <div class="glass-card p-5 group hover:border-white/20 transition-all duration-500">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-lg shadow-emerald-500/20">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <span class="badge badge-success text-[10px]">Active</span>
            </div>
            <p class="text-2xl font-bold text-white">{{ $stats['active_tasks'] ?? 0 }}</p>
            <p class="text-xs text-white/30 mt-1">Scheduled Tasks</p>
        </div>

        {{-- Today's Actions --}}
        <div class="glass-card p-5 group hover:border-white/20 transition-all duration-500">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg shadow-amber-500/20">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                    </svg>
                </div>
                <span class="badge badge-warning text-[10px]">Today</span>
            </div>
            <p class="text-2xl font-bold text-white">{{ $stats['today_actions'] ?? 0 }}</p>
            <p class="text-xs text-white/30 mt-1">Actions Executed</p>
        </div>

        {{-- Errors --}}
        <div class="glass-card p-5 group hover:border-white/20 transition-all duration-500">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-red-500 to-rose-600 flex items-center justify-center shadow-lg shadow-red-500/20">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <span class="badge badge-danger text-[10px]">Errors</span>
            </div>
            <p class="text-2xl font-bold text-white">{{ $stats['errors'] ?? 0 }}</p>
            <p class="text-xs text-white/30 mt-1">Error Count</p>
        </div>
    </div>

    {{-- Account Cards Grid --}}
    <div class="mb-8">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>
                Accounts Overview
            </h2>
            <a href="{{ route('accounts.index') }}" class="text-sm text-emerald-400 hover:text-emerald-300 transition-colors flex items-center gap-1">
                View All
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </a>
        </div>

        @if(isset($accounts) && $accounts->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                @foreach($accounts as $account)
                    @include('partials.account-card', ['account' => $account])
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
                <p class="text-white/30 text-sm mb-4">Add your first TSO account to get started.</p>
                <a href="{{ route('accounts.index') }}" class="btn-primary inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Add Account
                </a>
            </div>
        @endif
    </div>

    {{-- Recent Logs --}}
    <div>
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-teal-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                </svg>
                Recent Activity
            </h2>
            <a href="{{ route('logs.index') }}" class="text-sm text-teal-400 hover:text-teal-300 transition-colors flex items-center gap-1">
                View All Logs
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </a>
        </div>

        <div class="glass-card overflow-hidden">
            @if(isset($logs) && $logs->count() > 0)
                <div class="divide-y divide-white/5">
                    @foreach($logs->take(10) as $log)
                        @include('partials.log-entry', ['log' => $log])
                    @endforeach
                </div>
            @else
                <div class="p-8 text-center">
                    <p class="text-white/30 text-sm">No log entries yet.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
