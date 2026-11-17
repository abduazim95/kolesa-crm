<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, KeyRound, ListChecks, RefreshCw } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index as advertsIndex } from '@/routes/adverts';
import { edit as editKolesa } from '@/routes/kolesa';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Главная',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Главная" />

    <div class="flex flex-col gap-6 p-4">
        <div
            class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-neutral-950 p-6 text-neutral-100 dark:border-sidebar-border"
        >
            <div
                class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-red-700 via-red-500 to-red-700"
            />
            <div class="space-y-2">
                <div
                    class="text-xs uppercase tracking-[0.2em] text-neutral-500"
                >
                    Kolesa CRM · Parts
                </div>
                <h1
                    class="text-2xl font-semibold leading-tight tracking-tight"
                >
                    Управление объявлениями запчастей на
                    <span class="text-red-500">kolesa.kz</span>
                </h1>
                <p class="max-w-prose text-sm text-neutral-400">
                    Синхронизация каталога и массовое подключение пакетов
                    «Турбо» и «Fast».
                </p>
            </div>
            <div class="mt-5 flex flex-wrap gap-2">
                <Button
                    as-child
                    class="bg-red-700 hover:bg-red-600 focus-visible:ring-red-500"
                >
                    <Link :href="advertsIndex()">
                        Перейти к объявлениям
                        <ArrowRight class="ml-1 size-4" />
                    </Link>
                </Button>
                <Button
                    as-child
                    variant="outline"
                    class="border-neutral-700 bg-transparent text-neutral-100 hover:bg-neutral-800 hover:text-neutral-100"
                >
                    <Link :href="editKolesa()">
                        <KeyRound class="mr-1 size-4" />
                        Настроить токен
                    </Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <Link
                :href="advertsIndex()"
                class="group rounded-xl border border-sidebar-border/70 p-4 transition-colors hover:border-red-700/60 hover:bg-accent/50 dark:border-sidebar-border"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary"
                    >
                        <ListChecks class="size-5" />
                    </div>
                    <div class="space-y-1">
                        <div class="font-medium">Объявления</div>
                        <p class="text-sm text-muted-foreground">
                            Локальная копия каталога с фильтром по статусу
                            (активные, архив, удалённые).
                        </p>
                    </div>
                </div>
            </Link>

            <Link
                :href="advertsIndex()"
                class="group rounded-xl border border-sidebar-border/70 p-4 transition-colors hover:border-red-700/60 hover:bg-accent/50 dark:border-sidebar-border"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary"
                    >
                        <RefreshCw class="size-5" />
                    </div>
                    <div class="space-y-1">
                        <div class="font-medium">Синхронизация</div>
                        <p class="text-sm text-muted-foreground">
                            Запуск фоновой задачи, которая стягивает актуальный
                            список объявлений из kolesa.kz.
                        </p>
                    </div>
                </div>
            </Link>
        </div>
    </div>
</template>
