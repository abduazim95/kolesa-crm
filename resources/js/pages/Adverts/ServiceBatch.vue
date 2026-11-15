<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import { ArrowLeft, CheckCircle2, Loader2, XCircle } from '@lucide/vue';
import { computed, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index as advertsIndex } from '@/routes/adverts';
import { index as servicesHistory } from '@/routes/adverts/services';
import { edit as editKolesa } from '@/routes/kolesa';

type BatchInfo = {
    id: string;
    service_name: string;
    service_label: string;
    storage_id: string;
    total_count: number;
    applied_count: number;
    failed_count: number;
    skipped_count: number;
    pending_count: number;
    estimated_total: number;
    finished: boolean;
    cancelled: boolean;
    progress: number;
    created_at: string | null;
    finished_at: string | null;
};

type ApplicationRow = {
    id: number;
    kolesa_id: number;
    advert_title: string | null;
    advert_status: string | null;
    service_name: string;
    estimated_price: number | null;
    status: 'pending' | 'applied' | 'failed' | 'skipped';
    error_type: string | null;
    error_message: string | null;
    applied_at: string | null;
};

const props = defineProps<{
    batch: BatchInfo;
    applications: ApplicationRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Объявления', href: advertsIndex() },
            { title: 'История', href: servicesHistory() },
            { title: 'Применение', href: null },
        ],
    },
});

const isActive = computed(
    () => !props.batch.finished && !props.batch.cancelled,
);

const { pause, resume } = useIntervalFn(
    () => {
        router.reload({
            only: ['batch', 'applications'],
            preserveScroll: true,
            preserveState: true,
        });
    },
    2000,
    { immediate: false },
);

watch(
    isActive,
    (value) => {
        if (value) resume();
        else pause();
    },
    { immediate: true },
);

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

const statusRowClass: Record<ApplicationRow['status'], string> = {
    applied: 'text-emerald-700 dark:text-emerald-400',
    failed: 'text-destructive',
    skipped: 'text-muted-foreground',
    pending: '',
};

const statusLabels: Record<ApplicationRow['status'], string> = {
    pending: 'Ожидает',
    applied: 'Применено',
    failed: 'Ошибка',
    skipped: 'Пропущено',
};

const authExpired = computed(() =>
    props.applications.some((a) => a.error_type === 'auth_expired'),
);

const appliedSum = computed(() =>
    props.applications
        .filter((a) => a.status === 'applied')
        .reduce((sum, a) => sum + (a.estimated_price ?? 0), 0),
);

function cancelBatch() {
    const pending = props.batch.pending_count;
    if (
        !confirm(
            `Отменить применение? ${pending} объявлений ещё в очереди — они будут пропущены. Уже применённые услуги не отзываются.`,
        )
    ) {
        return;
    }
    router.post(
        `/adverts/services/${props.batch.id}/cancel`,
        {},
        { preserveScroll: true, preserveState: false },
    );
}
</script>

<template>
    <Head title="Применение услуги" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                :title="batch.service_label"
                :description="`Batch ${batch.id.slice(0, 8)}`"
            />
            <div class="flex gap-2">
                <Button
                    v-if="isActive"
                    variant="destructive"
                    @click="cancelBatch"
                >
                    Отменить
                </Button>
                <Button as-child variant="outline">
                    <Link :href="servicesHistory()">
                        <ArrowLeft class="size-4" />
                        К истории
                    </Link>
                </Button>
            </div>
        </div>

        <section
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 text-sm">
                    <Loader2
                        v-if="isActive"
                        class="size-4 animate-spin text-primary"
                    />
                    <CheckCircle2
                        v-else-if="batch.finished && !batch.cancelled"
                        class="size-4 text-emerald-600"
                    />
                    <XCircle
                        v-else-if="batch.cancelled"
                        class="size-4 text-destructive"
                    />
                    <span class="font-medium">
                        <template v-if="isActive">Выполняется</template>
                        <template v-else-if="batch.cancelled">
                            Отменено
                        </template>
                        <template v-else>Завершено</template>
                    </span>
                    <span class="text-muted-foreground">
                        ·
                        {{
                            batch.applied_count +
                            batch.failed_count +
                            batch.skipped_count
                        }}
                        /
                        {{ batch.total_count }}
                    </span>
                </div>
                <div class="text-xs text-muted-foreground">
                    {{ formatDateTime(batch.created_at) }}
                </div>
            </div>

            <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full bg-primary transition-all"
                    :style="{ width: `${batch.progress}%` }"
                />
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                <div>
                    Применено:
                    <span
                        class="font-semibold text-emerald-700 dark:text-emerald-400"
                    >
                        {{ batch.applied_count }}
                    </span>
                </div>
                <div>
                    Ошибки:
                    <span class="font-semibold text-destructive">
                        {{ batch.failed_count }}
                    </span>
                </div>
                <div>
                    Пропущено:
                    <span class="font-semibold">
                        {{ batch.skipped_count }}
                    </span>
                </div>
                <div>
                    Ожидают:
                    <span class="font-semibold">
                        {{ batch.pending_count }}
                    </span>
                </div>
            </div>

            <div
                v-if="!isActive"
                class="mt-3 text-sm text-muted-foreground"
            >
                Сумма успешно применённых: {{ formatPrice(appliedSum) }}
            </div>
        </section>

        <Alert v-if="authExpired" variant="destructive">
            <AlertTitle>Токен протух</AlertTitle>
            <AlertDescription>
                Batch остановлен. После обновления токена примените услугу
                заново для пропущенных объявлений.
                <Link :href="editKolesa()" class="ml-1 underline">
                    Обновить токен
                </Link>
            </AlertDescription>
        </Alert>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-left text-sm">
                <thead
                    class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                >
                    <tr>
                        <th class="px-3 py-2 font-medium">ID</th>
                        <th class="px-3 py-2 font-medium">Название</th>
                        <th class="px-3 py-2 font-medium">Цена</th>
                        <th class="px-3 py-2 font-medium">Статус</th>
                        <th class="px-3 py-2 font-medium">Применено</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="app in applications"
                        :key="app.id"
                        :class="
                            cn(
                                'border-b border-sidebar-border/50 last:border-0 dark:border-sidebar-border',
                                statusRowClass[app.status],
                            )
                        "
                    >
                        <td class="px-3 py-2 font-mono text-xs">
                            {{ app.kolesa_id }}
                        </td>
                        <td class="px-3 py-2">
                            <div>{{ app.advert_title || '—' }}</div>
                            <div
                                v-if="app.error_message"
                                class="text-xs text-destructive"
                            >
                                {{ app.error_message }}
                            </div>
                        </td>
                        <td class="px-3 py-2 tabular-nums">
                            {{ formatPrice(app.estimated_price) }}
                        </td>
                        <td class="px-3 py-2">
                            <Badge
                                :variant="
                                    app.status === 'applied'
                                        ? 'secondary'
                                        : app.status === 'failed'
                                          ? 'destructive'
                                          : 'outline'
                                "
                            >
                                {{ statusLabels[app.status] }}
                            </Badge>
                        </td>
                        <td class="px-3 py-2 text-xs text-muted-foreground">
                            {{ formatDateTime(app.applied_at) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
