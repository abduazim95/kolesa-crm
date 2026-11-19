<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { refDebounced, useIntervalFn } from '@vueuse/core';
import {
    ArrowDown,
    ArrowUp,
    Download,
    Loader2,
    Search,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CartController from '@/actions/App/Http/Controllers/Adverts/CartController';
import SyncController from '@/actions/App/Http/Controllers/Adverts/SyncController';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { index as advertsIndex } from '@/routes/adverts';
import { index as cartRoute } from '@/routes/adverts/cart';
import { edit as editKolesa } from '@/routes/kolesa';

type SyncRun = {
    id: number;
    status: 'pending' | 'running' | 'completed' | 'failed';
    current_section: 'live' | 'archive' | 'finalize' | null;
    live_processed: number;
    archive_processed: number;
    live_total: number | null;
    archive_total: number | null;
    marked_removed_count: number;
    error_type: string | null;
    error_message: string | null;
    started_at: string | null;
    finished_at: string | null;
};

type AdvertItem = {
    id: number;
    kolesa_id: number;
    title: string | null;
    price: number | null;
    status: string;
    photo_url: string | null;
    views_total: number;
    views_week: number;
    phone_views_total: number;
    phone_views_week: number;
    favorites_total: number;
    favorites_week: number;
    active_services: string[];
    show_till: string | null;
    published_at: string | null;
};

type SortKey =
    | 'published_at'
    | 'views'
    | 'phone_views'
    | 'favorites'
    | 'show_till';

const serviceLabels: Record<string, string> = {
    re: 'Продление',
    up: 'Поднятие',
    hot: 'Горячие',
    vip: 'VIP',
    'turbo-sale': 'Турбо',
    'fast-sale': 'Fast',
    'archive-re': 'Восстановление',
};

const serviceFilterOptions: ReadonlyArray<{ value: string; label: string }> = [
    { value: 'turbo-sale', label: 'Турбо' },
    { value: 'fast-sale', label: 'Fast' },
    { value: 'vip', label: 'VIP' },
    { value: 'up', label: 'Поднятие' },
];

type PaginatedAdverts = {
    data: AdvertItem[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    from: number | null;
    to: number | null;
    total: number;
};

const props = defineProps<{
    adverts: PaginatedAdverts;
    filters: {
        status: string | null;
        q: string | null;
        sort: SortKey | null;
        direction: 'asc' | 'desc' | null;
        service: string | null;
        without: string | null;
    };
    syncRun: SyncRun | null;
    hasToken: boolean;
    groups: Array<{ id: number; name: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Adverts', href: advertsIndex() }],
    },
});

const page = usePage<{ syncRun: SyncRun | null }>();

const isSyncActive = computed(
    () =>
        !!page.props.syncRun &&
        ['pending', 'running'].includes(page.props.syncRun.status),
);

const { pause, resume } = useIntervalFn(
    () => {
        router.reload({
            only: ['syncRun'],
            preserveScroll: true,
            preserveState: true,
        });
    },
    2000,
    { immediate: false },
);

watch(
    isSyncActive,
    (value) => {
        if (value) {
            resume();
        } else {
            pause();
        }
    },
    { immediate: true },
);

const activeTab = computed(() => props.filters.status ?? 'live');
const activeSort = computed<SortKey>(() => props.filters.sort ?? 'published_at');
const activeDirection = computed<'asc' | 'desc'>(
    () => props.filters.direction ?? 'desc',
);
const activeService = computed(() => props.filters.service ?? '');
const activeWithout = computed(() => props.filters.without ?? '');

function buildQuery(
    overrides: Partial<{
        status: string;
        sort: SortKey;
        direction: 'asc' | 'desc';
        q: string;
        service: string;
        without: string;
    }> = {},
) {
    const q = overrides.q ?? searchInput.value.trim();
    const sort = overrides.sort ?? activeSort.value;
    const direction = overrides.direction ?? activeDirection.value;
    const service =
        overrides.service !== undefined
            ? overrides.service
            : activeService.value;
    const without =
        overrides.without !== undefined
            ? overrides.without
            : activeWithout.value;
    return {
        status: overrides.status ?? activeTab.value,
        ...(q === '' ? {} : { q }),
        ...(sort === 'published_at' ? {} : { sort }),
        ...(direction === 'desc' ? {} : { direction }),
        ...(service === '' ? {} : { service }),
        ...(without === '' ? {} : { without }),
    };
}

function setSort(sort: SortKey) {
    let direction: 'asc' | 'desc' = 'desc';
    if (sort === activeSort.value) {
        // клик по той же колонке → переключение направления
        direction = activeDirection.value === 'desc' ? 'asc' : 'desc';
    }
    router.get(advertsIndex().url, buildQuery({ sort, direction }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function setService(service: string) {
    if (service === activeService.value && activeWithout.value === '') return;
    // service и without — взаимоисключающие
    router.get(advertsIndex().url, buildQuery({ service, without: '' }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function setWithout(without: string) {
    if (without === activeWithout.value && activeService.value === '') return;
    router.get(advertsIndex().url, buildQuery({ without, service: '' }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const showTillLabel = computed(() =>
    activeTab.value === 'archive' ? 'Удалится' : 'Продвигается до',
);

const sortableColumns = computed<ReadonlyArray<{ key: SortKey; label: string }>>(
    () => [
        { key: 'views', label: 'Просмотры' },
        { key: 'phone_views', label: 'Звонки' },
        { key: 'favorites', label: 'В избранном' },
        { key: 'show_till', label: showTillLabel.value },
        { key: 'published_at', label: 'Опубликовано' },
    ],
);

const tabs: ReadonlyArray<{ value: string; label: string }> = [
    { value: 'live', label: 'Активные' },
    { value: 'archive', label: 'Архив' },
];

const searchInput = ref(props.filters.q ?? '');
const searchDebounced = refDebounced(searchInput, 300);

watch(searchDebounced, (value) => {
    const trimmed = value.trim();
    if ((props.filters.q ?? '') === trimmed) return;

    router.get(advertsIndex().url, buildQuery({ q: trimmed }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
});

function clearSearch() {
    searchInput.value = '';
}

function switchTab(value: string) {
    router.get(advertsIndex().url, buildQuery({ status: value }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function formatPrice(price: number | null): string {
    if (price === null) return '—';
    return new Intl.NumberFormat('ru-RU').format(price) + ' ₸';
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleDateString('ru-RU');
    } catch {
        return iso;
    }
}

function statusBadgeVariant(
    status: string,
): 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'archive':
            return 'secondary';
        case 'delete':
            return 'destructive';
        default:
            return 'outline';
    }
}

function statusBadgeClass(status: string): string {
    if (status === 'live') {
        return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400';
    }
    if (status === 'temp') {
        return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400';
    }
    return '';
}

const statusLabels: Record<string, string> = {
    live: 'Активно',
    archive: 'Архив',
    delete: 'Удалено',
    temp: 'Черновик',
};

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    const q = buildQuery();
    for (const [key, value] of Object.entries(q)) {
        if (value !== null && value !== undefined && value !== '') {
            params.set(key, String(value));
        }
    }
    const qs = params.toString();
    return '/adverts/export' + (qs ? '?' + qs : '');
});

function startSync() {
    router.post(
        SyncController.store.url(),
        {},
        { preserveScroll: true, preserveState: false },
    );
}

const selectedIds = ref<Set<number>>(new Set());

const visibleIds = computed(() => props.adverts.data.map((a) => a.id));
const allVisibleSelected = computed(
    () =>
        visibleIds.value.length > 0 &&
        visibleIds.value.every((id) => selectedIds.value.has(id)),
);
const selectableNow = computed(() =>
    props.adverts.data.filter((a) =>
        ['live', 'archive'].includes(a.status),
    ),
);
const selectedAdverts = computed(() =>
    props.adverts.data.filter((a) => selectedIds.value.has(a.id)),
);
const selectedStorages = computed(() => {
    const s = new Set<string>();
    selectedAdverts.value.forEach((a) => s.add(a.status));
    return [...s];
});
const commonStorage = computed(() =>
    selectedStorages.value.length === 1 ? selectedStorages.value[0] : null,
);

function toggleRow(id: number, checked: boolean) {
    const next = new Set(selectedIds.value);
    if (checked) next.add(id);
    else next.delete(id);
    selectedIds.value = next;
}

function toggleAllVisible(checked: boolean) {
    const next = new Set(selectedIds.value);
    if (checked) {
        selectableNow.value.forEach((a) => next.add(a.id));
    } else {
        visibleIds.value.forEach((id) => next.delete(id));
    }
    selectedIds.value = next;
}

function clearSelection() {
    selectedIds.value = new Set();
}

function addToCart() {
    if (!commonStorage.value) return;

    router.post(
        CartController.store.url(),
        {
            storage_id: commonStorage.value,
            advert_ids: Array.from(selectedIds.value),
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                clearSelection();
            },
        },
    );
}

function addToGroup(groupId: number) {
    router.post(
        `/adverts/groups/${groupId}/items`,
        { advert_ids: Array.from(selectedIds.value) },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => clearSelection(),
        },
    );
}

const newGroupOpen = ref(false);
const newGroupForm = ref({ name: '' });

function openNewGroup() {
    newGroupForm.value.name = '';
    newGroupOpen.value = true;
}

function submitNewGroup() {
    if (!newGroupForm.value.name.trim()) return;
    router.post(
        '/adverts/groups',
        { name: newGroupForm.value.name },
        { onSuccess: () => (newGroupOpen.value = false) },
    );
}
</script>

<template>
    <Head title="Adverts" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Объявления"
                description="Локальная копия объявлений kolesa.kz"
            />

            <div class="flex gap-2">
                <Button as-child variant="outline">
                    <a :href="exportUrl" download>
                        <Download class="size-4" />
                        Excel
                    </a>
                </Button>
                <Button as-child variant="outline">
                    <Link :href="cartRoute()">Корзина</Link>
                </Button>
                <Button
                    :disabled="!hasToken || isSyncActive"
                    @click="startSync"
                    data-test="start-sync-button"
                >
                    <Loader2 v-if="isSyncActive" class="size-4 animate-spin" />
                    {{ isSyncActive ? 'Синхронизация…' : 'Синхронизировать' }}
                </Button>
            </div>
        </div>

        <Alert v-if="!hasToken" variant="destructive">
            <AlertTitle>Токен не настроен</AlertTitle>
            <AlertDescription>
                <Link :href="editKolesa()" class="underline">
                    Добавьте X-AUTH-TOKEN в настройках
                </Link>
                — без него синхронизация не запустится.
            </AlertDescription>
        </Alert>

        <section
            v-if="syncRun"
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            data-test="sync-status"
        >
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium">Синхронизация</span>
                    <Badge
                        :variant="
                            syncRun.status === 'completed'
                                ? 'secondary'
                                : syncRun.status === 'failed'
                                  ? 'destructive'
                                  : 'default'
                        "
                    >
                        {{ syncRun.status }}
                    </Badge>
                    <Badge v-if="syncRun.current_section" variant="outline">
                        {{ syncRun.current_section }}
                    </Badge>
                </div>
                <div v-if="syncRun.started_at" class="text-xs text-muted-foreground">
                    {{ new Date(syncRun.started_at).toLocaleString('ru-RU') }}
                </div>
            </div>

            <div
                v-if="syncRun.status === 'running' || syncRun.status === 'pending'"
                class="mt-3 grid grid-cols-2 gap-2 text-sm"
            >
                <div>
                    live: {{ syncRun.live_processed }} /
                    {{ syncRun.live_total ?? '?' }}
                </div>
                <div>
                    archive: {{ syncRun.archive_processed }} /
                    {{ syncRun.archive_total ?? '?' }}
                </div>
            </div>

            <div
                v-else-if="syncRun.status === 'completed'"
                class="mt-3 grid grid-cols-3 gap-2 text-sm text-muted-foreground"
            >
                <div>live: {{ syncRun.live_processed }}</div>
                <div>archive: {{ syncRun.archive_processed }}</div>
                <div>removed: {{ syncRun.marked_removed_count }}</div>
            </div>

            <div v-else-if="syncRun.status === 'failed'" class="mt-3">
                <Alert variant="destructive">
                    <AlertTitle>
                        <template v-if="syncRun.error_type === 'auth_expired'">
                            Токен протух
                        </template>
                        <template v-else>
                            Ошибка синхронизации ({{ syncRun.error_type }})
                        </template>
                    </AlertTitle>
                    <AlertDescription>
                        <div v-if="syncRun.error_message">
                            {{ syncRun.error_message }}
                        </div>
                        <Link
                            v-if="syncRun.error_type === 'auth_expired'"
                            :href="editKolesa()"
                            class="underline"
                        >
                            Обновить токен
                        </Link>
                    </AlertDescription>
                </Alert>
            </div>
        </section>

        <div class="flex flex-wrap items-center gap-3">
            <div class="relative w-full max-w-md">
                <Search
                    class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="searchInput"
                    type="search"
                    placeholder="Поиск по названию или ID"
                    class="pl-9 pr-9"
                />
                <button
                    v-if="searchInput"
                    type="button"
                    @click="clearSearch"
                    class="absolute right-2 top-1/2 flex size-6 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                    aria-label="Очистить поиск"
                >
                    <X class="size-4" />
                </button>
            </div>

            <div class="flex w-full flex-col gap-1">
                <div class="flex flex-wrap items-center gap-1">
                    <span class="mr-1 w-16 text-sm text-muted-foreground">
                        С услугой:
                    </span>
                    <button
                        type="button"
                        @click="setService('')"
                        :class="
                            cn(
                                'rounded-full border px-3 py-1 text-xs transition-colors',
                                activeService === '' && activeWithout === ''
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-sidebar-border/70 text-muted-foreground hover:text-foreground dark:border-sidebar-border',
                            )
                        "
                    >
                        Все
                    </button>
                    <button
                        v-for="opt in serviceFilterOptions"
                        :key="opt.value"
                        type="button"
                        @click="setService(opt.value)"
                        :class="
                            cn(
                                'rounded-full border px-3 py-1 text-xs transition-colors',
                                activeService === opt.value
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-sidebar-border/70 text-muted-foreground hover:text-foreground dark:border-sidebar-border',
                            )
                        "
                    >
                        {{ opt.label }}
                    </button>
                </div>
                <div class="flex flex-wrap items-center gap-1">
                    <span class="mr-1 w-16 text-sm text-muted-foreground">
                        Без:
                    </span>
                    <button
                        v-for="opt in serviceFilterOptions"
                        :key="opt.value"
                        type="button"
                        @click="setWithout(opt.value)"
                        :class="
                            cn(
                                'rounded-full border px-3 py-1 text-xs transition-colors',
                                activeWithout === opt.value
                                    ? 'border-destructive bg-destructive text-destructive-foreground'
                                    : 'border-sidebar-border/70 text-muted-foreground hover:text-foreground dark:border-sidebar-border',
                            )
                        "
                    >
                        {{ opt.label }}
                    </button>
                </div>
            </div>
        </div>

        <nav
            class="-mb-px flex flex-wrap gap-6 border-b border-sidebar-border/70 dark:border-sidebar-border"
            aria-label="Фильтр статусов"
        >
            <button
                v-for="tab in tabs"
                :key="tab.value"
                type="button"
                @click="switchTab(tab.value)"
                :class="
                    cn(
                        'relative -mb-px border-b-2 border-transparent px-1 pb-3 text-sm font-medium transition-colors',
                        activeTab === tab.value
                            ? 'border-primary text-foreground'
                            : 'text-muted-foreground hover:text-foreground',
                    )
                "
            >
                {{ tab.label }}
            </button>
        </nav>

        <div
            v-if="selectedIds.size > 0"
            class="sticky top-2 z-10 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/40 bg-primary/5 px-4 py-3 shadow-sm"
        >
            <div class="text-sm">
                Выбрано:
                <span class="font-semibold">{{ selectedIds.size }}</span>
                <span
                    v-if="!commonStorage"
                    class="ml-2 text-destructive"
                >
                    — выберите объявления одного статуса
                </span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    size="sm"
                    :disabled="!commonStorage"
                    @click="addToCart"
                >
                    В корзину
                </Button>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button size="sm" variant="outline">В группу</Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuLabel>Выбрать группу</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            v-for="g in groups"
                            :key="g.id"
                            @click="addToGroup(g.id)"
                        >
                            {{ g.name }}
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-if="groups.length === 0"
                            disabled
                        >
                            Групп ещё нет
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @click="openNewGroup">
                            + Новая группа
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <Button
                    size="sm"
                    variant="ghost"
                    @click="clearSelection"
                >
                    Сбросить
                </Button>
            </div>
        </div>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-left text-sm">
                <thead
                    class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                >
                    <tr>
                        <th class="px-3 py-2 font-medium">
                            <Checkbox
                                :model-value="allVisibleSelected"
                                :disabled="selectableNow.length === 0"
                                @update:model-value="
                                    (v) => toggleAllVisible(v === true)
                                "
                                aria-label="Выделить все"
                            />
                        </th>
                        <th class="px-3 py-2 font-medium">ID</th>
                        <th class="px-3 py-2 font-medium">Фото</th>
                        <th class="px-3 py-2 font-medium">Название</th>
                        <th class="px-3 py-2 font-medium">Цена</th>
                        <th class="px-3 py-2 font-medium">Статус</th>
                        <th class="px-3 py-2 font-medium">Услуги</th>
                        <th
                            v-for="col in sortableColumns"
                            :key="col.key"
                            class="px-3 py-2 font-medium"
                        >
                            <button
                                type="button"
                                @click="setSort(col.key)"
                                :class="
                                    cn(
                                        'inline-flex items-center gap-1 transition-colors hover:text-foreground',
                                        activeSort === col.key
                                            ? 'text-foreground'
                                            : 'text-muted-foreground',
                                    )
                                "
                            >
                                {{ col.label }}
                                <template v-if="activeSort === col.key">
                                    <ArrowDown
                                        v-if="activeDirection === 'desc'"
                                        class="size-3"
                                    />
                                    <ArrowUp v-else class="size-3" />
                                </template>
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="advert in adverts.data"
                        :key="advert.id"
                        :class="
                            cn(
                                'border-b border-sidebar-border/50 last:border-0 dark:border-sidebar-border',
                                selectedIds.has(advert.id) && 'bg-muted/40',
                            )
                        "
                    >
                        <td class="px-3 py-2">
                            <Checkbox
                                :model-value="selectedIds.has(advert.id)"
                                :disabled="
                                    !['live', 'archive'].includes(advert.status)
                                "
                                @update:model-value="
                                    (v) => toggleRow(advert.id, v === true)
                                "
                                :aria-label="`Выделить ${advert.kolesa_id}`"
                            />
                        </td>
                        <td class="px-3 py-2 font-mono text-xs">
                            {{ advert.kolesa_id }}
                        </td>
                        <td class="px-3 py-2">
                            <img
                                v-if="advert.photo_url"
                                :src="advert.photo_url"
                                alt=""
                                class="h-10 w-16 rounded object-cover"
                                loading="lazy"
                            />
                        </td>
                        <td class="px-3 py-2">{{ advert.title || '—' }}</td>
                        <td class="px-3 py-2">{{ formatPrice(advert.price) }}</td>
                        <td class="px-3 py-2">
                            <Badge
                                :variant="statusBadgeVariant(advert.status)"
                                :class="statusBadgeClass(advert.status)"
                            >
                                {{ statusLabels[advert.status] ?? advert.status }}
                            </Badge>
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="svc in advert.active_services"
                                    :key="svc"
                                    variant="outline"
                                    class="cursor-pointer"
                                    @click="setService(svc)"
                                    :title="`Фильтровать по «${serviceLabels[svc] ?? svc}»`"
                                >
                                    {{ serviceLabels[svc] ?? svc }}
                                </Badge>
                                <span
                                    v-if="advert.active_services.length === 0"
                                    class="text-xs text-muted-foreground"
                                >
                                    —
                                </span>
                            </div>
                        </td>
                        <td class="px-3 py-2 tabular-nums">
                            <div>{{ advert.views_total.toLocaleString('ru-RU') }}</div>
                            <div
                                v-if="advert.views_week > 0"
                                class="text-xs text-muted-foreground"
                            >
                                +{{ advert.views_week }} за нед.
                            </div>
                        </td>
                        <td class="px-3 py-2 tabular-nums">
                            <div>
                                {{
                                    advert.phone_views_total.toLocaleString(
                                        'ru-RU',
                                    )
                                }}
                            </div>
                            <div
                                v-if="advert.phone_views_week > 0"
                                class="text-xs text-muted-foreground"
                            >
                                +{{ advert.phone_views_week }} за нед.
                            </div>
                        </td>
                        <td class="px-3 py-2 tabular-nums">
                            <div>
                                {{
                                    advert.favorites_total.toLocaleString(
                                        'ru-RU',
                                    )
                                }}
                            </div>
                            <div
                                v-if="advert.favorites_week > 0"
                                class="text-xs text-muted-foreground"
                            >
                                +{{ advert.favorites_week }} за нед.
                            </div>
                        </td>
                        <td class="px-3 py-2">
                            {{ formatDate(advert.show_till) }}
                        </td>
                        <td class="px-3 py-2">
                            {{ formatDate(advert.published_at) }}
                        </td>
                    </tr>
                    <tr v-if="adverts.data.length === 0">
                        <td colspan="12" class="px-3 py-6 text-center text-muted-foreground">
                            Объявлений нет. Запустите синхронизацию.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Dialog v-model:open="newGroupOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Новая группа</DialogTitle>
                </DialogHeader>
                <div class="space-y-2">
                    <Label for="new-group-name">Название</Label>
                    <Input
                        id="new-group-name"
                        v-model="newGroupForm.name"
                        required
                        autofocus
                    />
                    <p class="text-xs text-muted-foreground">
                        В группе можно держать и активные, и архивные
                        объявления. Услуги применяются только к подходящему
                        статусу.
                    </p>
                </div>
                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="secondary">Отмена</Button>
                    </DialogClose>
                    <Button
                        :disabled="!newGroupForm.name.trim()"
                        @click="submitNewGroup"
                    >
                        Создать
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <div
            v-if="adverts.links.length > 3"
            class="flex flex-wrap items-center gap-1"
        >
            <template v-for="(link, idx) in adverts.links" :key="idx">
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
