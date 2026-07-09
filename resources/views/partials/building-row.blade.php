{{-- Building Row Component --}}
{{-- Expected: $building (array/object with: buildingName_string, buildingGrid, isProductionActive, buildingMode, upgradeLevel) --}}
{{-- Expected: $account (object with: id) --}}
@php
    $building = (object) $building;
    $grid = $building->buildingGrid ?? 0;
    
    // Check if production is active
    $isProducing = (bool) ($building->isProductionActive ?? false);
    
    // Human-friendly name conversion (e.g. "WoodCutter" -> "Wood Cutter")
    $name = $building->buildingName_string ?? $building->buildingName ?? 'Unknown Building';
    $name = preg_replace('/(?<!^)(?=[A-Z])/', ' ', $name);
    $name = str_replace('_', ' ', $name);
    $name = ucwords(strtolower($name));
    
    $level = $building->upgradeLevel ?? 1;
@endphp

<div class="flex items-center justify-between p-3 rounded-xl bg-white/[0.02] hover:bg-white/5 border border-transparent hover:border-white/5 transition-all duration-300 group">
    <div class="flex items-center gap-3">
        {{-- Building icon --}}
        <div class="w-9 h-9 rounded-lg {{ $isProducing ? 'bg-emerald-500/10 text-emerald-400' : 'bg-white/5 text-white/30' }} flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
            </svg>
        </div>

        <div>
            <p class="text-sm font-medium text-white/80 group-hover:text-white transition-colors">
                {{ $name }} <span class="text-white/30 text-xs">Lvl {{ $level }}</span>
            </p>
            <div class="flex items-center gap-2 mt-0.5">
                <span class="text-[10px] text-white/30 font-mono">Grid #{{ $grid }}</span>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-2">
        {{-- Status badge --}}
        <span class="badge {{ $isProducing ? 'badge-success' : 'badge-neutral' }} text-[10px]">
            {{ $isProducing ? 'Producing' : 'Stopped' }}
        </span>

        {{-- Action buttons --}}
        @if($isProducing)
            <form action="{{ route('accounts.action', $account->id) }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="action_type" value="stop_production">
                <input type="hidden" name="grid" value="{{ $grid }}">
                <button type="submit" class="btn-secondary btn-sm text-[10px] text-amber-400/60 hover:text-amber-400 hover:border-amber-500/30">
                    Stop
                </button>
            </form>
        @else
            <form action="{{ route('accounts.action', $account->id) }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="action_type" value="start_production">
                <input type="hidden" name="grid" value="{{ $grid }}">
                <button type="submit" class="btn-secondary btn-sm text-[10px] text-emerald-400/60 hover:text-emerald-400 hover:border-emerald-500/30">
                    Start
                </button>
            </form>
        @endif
    </div>
</div>
