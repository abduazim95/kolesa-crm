<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index as advertsIndex } from '@/routes/adverts';

type BatchRow = {
    id: string;
    service_name: string;
    service_label: string;
    storage_id: string;
    total_count: number;
    applied_count: number;
    failed_count: number;
    skipped_count: number;
    estimated_total: number;
    finished_at: string | null;
    created_at: string | null;
};

type Paginated = {
    data: BatchRow[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

defineProps<{
    batches: Paginated;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Объявления', href: advertsIndex() },
            { title: 'История применений', href: null },
        ],
    },
});

function formatPrice(price: number | null): string {
    if (price === null) return '—';
    return new Intl.NumberFormat('ru-RU').format(price) + ' ₸';
}

function formatDateTime(iso: string | null): string {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('ru-RU');
    } catch {
        return iso;
    }
}

const storageLabels: Record<string, string> = {
    live: 'Активные',
    archive: 'Архив',
};
</script>

<template>
    <Head title="История применений" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="История применений"
                description="Все запуски массовых применений услуг и пакетов"
            />
            <Button as-child variant="outline">
                <Link :href="advertsIndex()">
                    <ArrowLeft class="size-4" />
                    К объявлениям
                </Link>
            </Button>
        </div>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-left text-sm">
                <thead
                    class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                >
                    <tr>
                        <th class="px-3 py-2 font-medium">Дата</th>
                        <th class="px-3 py-2 font-medium">Услуга</th>
                        <th class="px-3 py-2 font-medium">Статус</th>
                        <th class="px-3 py-2 font-medium">Всего</th>
                        <th class="px-3 py-2 font-medium">Применено</th>
                        <th class="px-3 py-2 font-medium">Ошибок</th>
                        <th class="px-3 py-2 font-medium">Пропущено</th>
                        <th class="px-3 py-2 font-medium">Сумма</th>
                        <th class="px-3 py-2 font-medium" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="b in batches.data"
                        :key="b.id"
                        class="border-b border-sidebar-border/50 last:border-0 dark:border-sidebar-border"
                    >
                        <td class="px-3 py-2 text-xs text-muted-foreground">
                            {{ formatDateTime(b.created_at) }}
                        </td>
                        <td class="px-3 py-2">{{ b.service_label }}</td>
                        <td class="px-3 py-2">
                            <Badge variant="outline">
                                {{
                                    storageLabels[b.storage_id] ?? b.storage_id
                                }}
                            </Badge>
                        </td>
                        <td class="px-3 py-2 tabular-nums">
                            {{ b.total_count }}
                        </td>
                        <td class="px-3 py-2 tabular-nums text-emerald-700 dark:text-emerald-400">
                            {{ b.applied_count }}
                        </td>
                        <td class="px-3 py-2 tabular-nums text-destructive">
                            {{ b.failed_count }}
                        </td>
                        <td class="px-3 py-2 tabular-nums">
                            {{ b.skipped_count }}
                        </td>
                        <td class="px-3 py-2 tabular-nums">
                            {{ formatPrice(b.estimated_total) }}
                        </td>
                        <td class="px-3 py-2">
                            <Button
                                as-child
                                size="sm"
                                variant="ghost"
                            >
                                <Link :href="`/adverts/services/${b.id}`">
                                    Детали
                                </Link>
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="batches.data.length === 0">
                        <td
                            colspan="9"
                            class="px-3 py-10 text-center text-muted-foreground"
                        >
                            Пока нет запусков.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="batches.links.length > 3"
            class="flex flex-wrap items-center gap-1"
        >
            <template v-for="(link, idx) in batches.links" :key="idx">
                <Button
                    v-if="link.url"
                    variant="outline"
                    size="sm"
                    :class="{ 'bg-muted': link.active }"
                    @click="
                        router.get(
                            link.url,
                            {},
                            { preserveScroll: true, preserveState: true },
                        )
                    "
                >
                    <span v-html="link.label" />
                </Button>
                <Button
                    v-else
                    variant="outline"
                    size="sm"
                    disabled
                    class="opacity-50"
                >
                    <span v-html="link.label" />
                </Button>
            </template>
        </div>
    </div>
</template>
