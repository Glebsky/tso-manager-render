<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('ui.auth.login_title') }} — TSO Manager</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-dark-950 font-sans text-white antialiased">
    <div class="relative min-h-full flex items-center justify-center overflow-hidden px-4 py-12">
        <!-- Language Switcher in Upper Right Corner -->
        <div class="absolute top-5 right-5 z-50">
            <div class="relative inline-block text-left" id="lang-menu">
                <button
                    type="button"
                    onclick="document.getElementById('lang-dropdown').classList.toggle('hidden')"
                    class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-white/5 border border-white/10 text-xs font-semibold text-white/80 hover:text-white hover:bg-white/10 hover:border-white/20 transition-all duration-200 shadow-sm"
                    title="Switch Language / Сменить язык / Змінити мову"
                >
                    @if(app()->getLocale() === 'uk')
                        <span class="text-sm leading-none">🇺🇦</span>
                        <span class="font-mono text-[11px] uppercase tracking-wider">UK</span>
                    @elseif(app()->getLocale() === 'ru')
                        <span class="text-sm leading-none">🇷🇺</span>
                        <span class="font-mono text-[11px] uppercase tracking-wider">RU</span>
                    @else
                        <span class="text-sm leading-none">🇺🇸</span>
                        <span class="font-mono text-[11px] uppercase tracking-wider">EN</span>
                    @endif
                    <svg class="w-3.5 h-3.5 text-white/40" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>

                <div
                    id="lang-dropdown"
                    class="hidden absolute right-0 mt-2 w-36 rounded-xl bg-dark-900/95 border border-white/10 shadow-2xl backdrop-blur-xl py-1.5 z-[100] overflow-hidden"
                >
                    <button
                        type="button"
                        onclick="setAppLocale('uk')"
                        class="w-full px-3 py-2 text-xs flex items-center justify-between transition-colors hover:bg-white/10 text-left {{ app()->getLocale() === 'uk' ? 'text-emerald-400 font-semibold bg-emerald-500/10' : 'text-white/80 hover:text-white' }}"
                    >
                        <span class="flex items-center gap-2">
                            <span class="text-base leading-none">🇺🇦</span>
                            <span>Українська</span>
                        </span>
                        @if(app()->getLocale() === 'uk')
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-sm shadow-emerald-400"></span>
                        @endif
                    </button>
                    <button
                        type="button"
                        onclick="setAppLocale('ru')"
                        class="w-full px-3 py-2 text-xs flex items-center justify-between transition-colors hover:bg-white/10 text-left {{ app()->getLocale() === 'ru' ? 'text-emerald-400 font-semibold bg-emerald-500/10' : 'text-white/80 hover:text-white' }}"
                    >
                        <span class="flex items-center gap-2">
                            <span class="text-base leading-none">🇷🇺</span>
                            <span>Русский</span>
                        </span>
                        @if(app()->getLocale() === 'ru')
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-sm shadow-emerald-400"></span>
                        @endif
                    </button>
                    <button
                        type="button"
                        onclick="setAppLocale('en')"
                        class="w-full px-3 py-2 text-xs flex items-center justify-between transition-colors hover:bg-white/10 text-left {{ app()->getLocale() === 'en' ? 'text-emerald-400 font-semibold bg-emerald-500/10' : 'text-white/80 hover:text-white' }}"
                    >
                        <span class="flex items-center gap-2">
                            <span class="text-base leading-none">🇺🇸</span>
                            <span>English</span>
                        </span>
                        @if(app()->getLocale() === 'en')
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-sm shadow-emerald-400"></span>
                        @endif
                    </button>
                </div>
            </div>
        </div>

        <div class="absolute -top-40 -left-40 h-96 w-96 rounded-full bg-emerald-500/10 blur-3xl"></div>
        <div class="absolute -bottom-40 -right-40 h-96 w-96 rounded-full bg-teal-500/10 blur-3xl"></div>

        <div class="relative z-10 w-full max-w-md">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 shadow-lg shadow-emerald-500/25">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold">TSO Manager</h1>
                <p class="mt-2 text-sm text-white/40">{{ __('ui.auth.login_subtitle') }}</p>
            </div>

            <div class="glass-card border border-white/10 p-7 shadow-2xl">
                @if (session('status'))
                    <div class="mb-5 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="mb-2 block text-xs font-semibold uppercase tracking-wider text-white/40">{{ __('ui.auth.email') }}</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                               class="glass-input w-full @error('email') border-red-500/50 @enderror">
                        @error('email')
                            <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-xs font-semibold uppercase tracking-wider text-white/40">{{ __('ui.auth.password') }}</label>
                        <input id="password" name="password" type="password" required autocomplete="current-password"
                               class="glass-input w-full @error('password') border-red-500/50 @enderror">
                        @error('password')
                            <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex cursor-pointer items-center gap-3 text-sm text-white/50">
                        <input type="checkbox" name="remember" value="1" class="rounded border-white/20 bg-white/5 text-emerald-500 focus:ring-emerald-500/30">
                        {{ __('ui.auth.remember_me') }}
                    </label>

                    <button type="submit" class="w-full rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-500/20 transition hover:shadow-emerald-500/35">
                        {{ __('ui.auth.sign_in') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function setAppLocale(locale) {
            localStorage.setItem('app_locale', locale);
            document.cookie = "app_locale=" + locale + "; path=/; max-age=31536000; SameSite=Lax";
            window.location.reload();
        }
        document.addEventListener('click', function(e) {
            var menu = document.getElementById('lang-menu');
            var dropdown = document.getElementById('lang-dropdown');
            if (menu && dropdown && !menu.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
