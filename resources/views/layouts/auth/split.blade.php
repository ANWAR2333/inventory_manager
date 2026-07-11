<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen antialiased" style="background-color: #14161A;">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">

            {{-- Panneau de marque (masqué sur mobile) --}}
            <div class="relative hidden h-full flex-col justify-between p-10 lg:flex overflow-hidden"
                 style="background-color: #16181C; border-right: 1px solid #2B2F36;">

                {{-- Grille de fond façon registre/manifeste --}}
                <div class="absolute inset-0 opacity-[0.07]" style="
                    background-image:
                        repeating-linear-gradient(0deg, #E8A33D 0, #E8A33D 1px, transparent 1px, transparent 48px),
                        repeating-linear-gradient(90deg, #E8A33D 0, #E8A33D 1px, transparent 1px, transparent 48px);
                "></div>

                <a href="{{ route('home') }}" class="relative z-20 flex items-center gap-3" wire:navigate>
                    <x-app-logo-icon class="h-7 fill-current" style="color: #EDEAE3;" />
                    <span class="font-display text-lg tracking-wide" style="color: #EDEAE3; font-family: var(--font-display, 'Barlow Condensed', sans-serif); text-transform: uppercase; letter-spacing: 0.05em;">
                        {{ config('app.name', 'Laravel') }}
                    </span>
                </a>

                {{-- Élément signature : wordmark tamponné + lignes de manifeste --}}
                <div class="relative z-20 mt-auto space-y-6">
                    <div class="stamp-mark text-3xl">
                        {{ config('app.name', 'Laravel') }}
                    </div>

                    <p class="max-w-sm text-sm leading-relaxed" style="color: #A8A499;">
                        Chaque référence suivie, du quai de réception jusqu'à la sortie de stock.
                    </p>

                    <div class="space-y-1.5 pt-2 text-xs" style="font-family: var(--font-mono, monospace); color: #55524C;">
                        <div>SKU-2841-A · IN · 40 units · WH-01</div>
                        <div>SKU-1075-C · OUT · 12 units · WH-01</div>
                        <div>SKU-3390-B · IN · 96 units · WH-02</div>
                    </div>
                </div>
            </div>

            {{-- Formulaire --}}
            <div class="w-full lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <a href="{{ route('home') }}" class="z-20 flex flex-col items-center gap-2 font-medium lg:hidden" wire:navigate>
                        <span class="flex h-9 w-9 items-center justify-center rounded-md">
                            <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                        </span>
                        <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                    </a>
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>