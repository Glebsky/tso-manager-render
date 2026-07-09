@if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-[-10px]"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-[-10px]"
         class="mb-6 glass-card p-4 border-emerald-500/30 bg-emerald-500/10">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-emerald-500/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
            </div>
            <p class="text-sm text-emerald-300 flex-1">{{ session('success') }}</p>
            <button @click="show = false" class="text-white/30 hover:text-white/60 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>
@endif

@if(session('error'))
    <div x-data="{ show: true }" x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-[-10px]"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-[-10px]"
         class="mb-6 glass-card p-4 border-red-500/30 bg-red-500/10">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-red-500/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <p class="text-sm text-red-300 flex-1">{{ session('error') }}</p>
            <button @click="show = false" class="text-white/30 hover:text-white/60 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>
@endif

@if(session('warning'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-[-10px]"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-[-10px]"
         class="mb-6 glass-card p-4 border-amber-500/30 bg-amber-500/10">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-amber-500/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                </svg>
            </div>
            <p class="text-sm text-amber-300 flex-1">{{ session('warning') }}</p>
            <button @click="show = false" class="text-white/30 hover:text-white/60 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>
@endif

@if($errors->any())
    <div x-data="{ show: true }" x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-[-10px]"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-[-10px]"
         class="mb-6 glass-card p-4 border-red-500/30 bg-red-500/10">
        <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-red-500/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg class="w-5 h-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <div class="flex-1">
                <p class="text-sm font-medium text-red-300 mb-2">Please fix the following errors:</p>
                <ul class="space-y-1">
                    @foreach($errors->all() as $error)
                        <li class="text-sm text-red-400/80 flex items-center gap-2">
                            <span class="w-1 h-1 rounded-full bg-red-400/60 flex-shrink-0"></span>
                            {{ $error }}
                        </li>
                    @endforeach
                </ul>
            </div>
            <button @click="show = false" class="text-white/30 hover:text-white/60 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>
@endif
