<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';
</script>

<template>
    <Head title="Kolesa CRM" />

    <div
        class="relative min-h-screen bg-neutral-950 text-neutral-100"
    >
        <div
            class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-red-700 via-red-500 to-red-700"
        />

        <main
            class="mx-auto flex min-h-screen w-full max-w-2xl flex-col items-start justify-center gap-10 px-6 py-24"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex size-10 items-center justify-center rounded-md bg-red-700 text-white"
                >
                    <AppLogoIcon class="size-6 fill-current" />
                </div>
                <div>
                    <div class="text-base font-semibold tracking-tight">
                        Kolesa CRM
                    </div>
                    <div
                        class="text-xs uppercase tracking-[0.2em] text-neutral-500"
                    >
                        Parts Management
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <h1 class="text-4xl font-semibold leading-tight tracking-tight">
                    Управление объявлениями
                    <span class="text-red-500">запчастей</span>
                    на&nbsp;kolesa.kz
                </h1>
                <p class="max-w-prose text-sm leading-relaxed text-neutral-400">
                    Синхронизация каталога, массовое подключение пакетов
                    «Турбо» и «ТОП» с&nbsp;предварительным расчётом бюджета.
                </p>
            </div>

            <Button
                as-child
                size="lg"
                class="bg-red-700 hover:bg-red-600 focus-visible:ring-red-500"
            >
                <Link :href="$page.props.auth.user ? dashboard() : login()">
                    {{ $page.props.auth.user ? 'Открыть дашборд' : 'Войти' }}
                </Link>
            </Button>
        </main>
    </div>
</template>
