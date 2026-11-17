<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarClock,
    CirclePlay,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { index as advertsIndex } from '@/routes/adverts';
import { index as groupsIndex } from '@/routes/adverts/groups';

type ServiceOption = { name: string; label: string; storage_id: string };

type AdvertRow = {
    id: number;
    kolesa_id: number;
    title: string | null;
    status: string;
    photo_url: string | null;
    price: number | null;
    show_till: string | null;
    active_services: string[];
    services: Array<{
        name: string;
        label: string;
        price: number;
        type: string;
    }>;
};

type ScheduleRow = {
    id: number;
    service_name: string;
    service_label: string;
    service_storage: string | null;
    cron_expression: string;
    timezone: string;
    enabled: boolean;
    last_run_at: string | null;
    next_run_at: string | null;
    last_batch_id: string | null;
    created_by: string | null;
};

const props = defineProps<{
    group: {
        id: number;
        name: string;
        created_at: string | null;
    };
    adverts: AdvertRow[];
    available_services: ServiceOption[];
    schedules: ScheduleRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Объявления', href: advertsIndex() },
            { title: 'Группы', href: groupsIndex() },
            { title: '', href: null },
        ],
    },
});

const storageLabels: Record<string, string> = {
    live: 'Активные',
    archive: 'Архив',
};

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('ru-RU');
    } catch {
        return iso;
    }
}

function formatPrice(price: number | null): string {
    if (price === null) return '—';
    return new Intl.NumberFormat('ru-RU').format(price) + ' ₸';
}

// ─── Вкладки по статусу ──────────────────────────────────────────────

const activeTab = ref<'live' | 'archive'>(
    props.adverts.some((a) => a.status === 'live') ? 'live' : 'archive',
);

const countsByStatus = computed(() => ({
    live: props.adverts.filter((a) => a.status === 'live').length,
    archive: props.adverts.filter((a) => a.status === 'archive').length,
}));

const visibleAdverts = computed(() =>
    props.adverts.filter((a) => a.status === activeTab.value),
);

const tabs = [
    { value: 'live', label: 'Активные' },
    { value: 'archive', label: 'Архив' },
] as const;

// ─── Переименование ──────────────────────────────────────────────────

const renameOpen = ref(false);
const renameForm = ref({ name: props.group.name });

function openRename() {
    renameForm.value.name = props.group.name;
    renameOpen.value = true;
}

function submitRename() {
    router.patch(
        `/adverts/groups/${props.group.id}`,
        { name: renameForm.value.name },
        { onSuccess: () => (renameOpen.value = false) },
    );
}

function destroyGroup() {
    if (!confirm('Удалить группу? Расписания тоже удалятся.')) return;
    router.delete(`/adverts/groups/${props.group.id}`);
}

// ─── Удаление элементов ──────────────────────────────────────────────

function removeAdvert(advertId: number) {
    router.delete(
        `/adverts/groups/${props.group.id}/items/${advertId}`,
        { preserveScroll: true },
    );
}

function clearAll() {
    if (!confirm('Убрать все объявления из группы?')) return;
    router.delete(`/adverts/groups/${props.group.id}/items`, {
        preserveScroll: true,
    });
}

// ─── Применить услугу сейчас ─────────────────────────────────────────

const applyOpen = ref(false);
const applyForm = ref({
    service_name: props.available_services[0]?.name ?? '',
});

function openApply() {
    applyForm.value.service_name = props.available_services[0]?.name ?? '';
    applyOpen.value = true;
}

const applyTargetCount = computed(() => {
    const svc = props.available_services.find(
        (s) => s.name === applyForm.value.service_name,
    );
    if (!svc) return 0;
    return props.adverts.filter(
        (a) =>
            a.status === svc.storage_id &&
            a.services.some((x) => x.name === svc.name),
    ).length;
});

const applyTotal = computed(() => {
    const svc = props.available_services.find(
        (s) => s.name === applyForm.value.service_name,
    );
    if (!svc) return 0;
    return props.adverts
        .filter((a) => a.status === svc.storage_id)
        .reduce((sum, a) => {
            const price = a.services.find((x) => x.name === svc.name)?.price;
            return sum + (price ?? 0);
        }, 0);
});

const applyUnpricedCount = computed(() => {
    const svc = props.available_services.find(
        (s) => s.name === applyForm.value.service_name,
    );
    if (!svc) return 0;
    return props.adverts.filter(
        (a) =>
            a.status === svc.storage_id &&
            !a.services.some((x) => x.name === svc.name),
    ).length;
});

function submitApply() {
    if (!applyForm.value.service_name) return;
    const svc = props.available_services.find(
        (s) => s.name === applyForm.value.service_name,
    );
    if (!svc) return;

    const totalStr = new Intl.NumberFormat('ru-RU').format(applyTotal.value);
    if (
        !confirm(
            `Применить «${svc.label}» к ${applyTargetCount.value} объявлениям (${storageLabels[svc.storage_id]})? С личного счёта будет списано примерно ${totalStr} ₸. Убедитесь, что баланса достаточно.`,
        )
    ) {
        return;
    }
    router.post(
        `/adverts/groups/${props.group.id}/apply`,
        { service_name: applyForm.value.service_name },
        { onSuccess: () => (applyOpen.value = false) },
    );
}

// ─── Расписания ──────────────────────────────────────────────────────

type ScheduleMode = 'simple' | 'cron';

const scheduleOpen = ref(false);
const editingScheduleId = ref<number | null>(null);
const scheduleMode = ref<ScheduleMode>('simple');
const scheduleForm = ref<{
    service_name: string;
    weekdays: number[];
    hour: number;
    minute: number;
    cron_expression: string;
    timezone: string;
    enabled: boolean;
}>({
    service_name: '',
    weekdays: [1],
    hour: 9,
    minute: 0,
    cron_expression: '',
    timezone: 'Asia/Almaty',
    enabled: true,
});

const weekdayOptions = [
    { value: 1, label: 'Пн' },
    { value: 2, label: 'Вт' },
    { value: 3, label: 'Ср' },
    { value: 4, label: 'Чт' },
    { value: 5, label: 'Пт' },
    { value: 6, label: 'Сб' },
    { value: 0, label: 'Вс' },
];

const builtCron = computed(() => {
    const { minute, hour, weekdays } = scheduleForm.value;
    const days =
        weekdays.length === 0
            ? '*'
            : [...weekdays].sort((a, b) => a - b).join(',');
    return `${minute} ${hour} * * ${days}`;
});

function openScheduleCreate() {
    editingScheduleId.value = null;
    scheduleMode.value = 'simple';
    scheduleForm.value = {
        service_name: props.available_services[0]?.name ?? '',
        weekdays: [1],
        hour: 9,
        minute: 0,
        cron_expression: '',
        timezone: 'Asia/Almaty',
        enabled: true,
    };
    scheduleOpen.value = true;
}

function openScheduleEdit(s: ScheduleRow) {
    editingScheduleId.value = s.id;
    scheduleMode.value = 'cron';
    scheduleForm.value = {
        service_name: s.service_name,
        weekdays: [1],
        hour: 9,
        minute: 0,
        cron_expression: s.cron_expression,
        timezone: s.timezone,
        enabled: s.enabled,
    };
    scheduleOpen.value = true;
}

function submitSchedule() {
    const payload = {
        service_name: scheduleForm.value.service_name,
        cron_expression:
            scheduleMode.value === 'simple'
                ? builtCron.value
                : scheduleForm.value.cron_expression.trim(),
        timezone: scheduleForm.value.timezone,
        enabled: scheduleForm.value.enabled,
    };

    const done = () => (scheduleOpen.value = false);

    if (editingScheduleId.value) {
        router.patch(
            `/adverts/groups/${props.group.id}/schedules/${editingScheduleId.value}`,
            payload,
            { onSuccess: done, preserveScroll: true },
        );
    } else {
        router.post(
            `/adverts/groups/${props.group.id}/schedules`,
            payload,
            { onSuccess: done, preserveScroll: true },
        );
    }
}

function toggleSchedule(s: ScheduleRow) {
    router.post(
        `/adverts/groups/${props.group.id}/schedules/${s.id}/toggle`,
        {},
        { preserveScroll: true },
    );
}

function deleteSchedule(s: ScheduleRow) {
    if (!confirm('Удалить расписание?')) return;
    router.delete(`/adverts/groups/${props.group.id}/schedules/${s.id}`, {
        preserveScroll: true,
    });
}

function toggleWeekday(d: number) {
    const i = scheduleForm.value.weekdays.indexOf(d);
    if (i >= 0) scheduleForm.value.weekdays.splice(i, 1);
    else scheduleForm.value.weekdays.push(d);
}
</script>

<template>
    <Head :title="group.name" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <div class="space-y-1">
                <Heading :title="group.name" />
                <p class="text-sm text-muted-foreground">
                    Всего: {{ adverts.length }} · активных:
                    {{ countsByStatus.live }} · архив:
                    {{ countsByStatus.archive }}
                </p>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" @click="openRename">
                    <Pencil class="size-4" />
                    Переименовать
                </Button>
                <Button variant="destructive" @click="destroyGroup">
                    <Trash2 class="size-4" />
                    Удалить
                </Button>
                <Button as-child variant="outline">
                    <Link :href="groupsIndex()">
                        <ArrowLeft class="size-4" />
                        Назад
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Применение услуги -->
        <section
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <div class="font-medium">Применить услугу</div>
                    <p class="text-sm text-muted-foreground">
                        Услуга применится только к объявлениям соответствующего
                        статуса.
                    </p>
                </div>
                <Button :disabled="adverts.length === 0" @click="openApply">
                    <CirclePlay class="size-4" />
                    Применить
                </Button>
            </div>
        </section>

        <!-- Расписания -->
        <section
            class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <header
                class="flex items-center justify-between gap-2 border-b border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div class="flex items-center gap-2">
                    <CalendarClock class="size-5 text-muted-foreground" />
                    <span class="font-medium">Расписания</span>
                    <span class="text-sm text-muted-foreground">
                        · {{ schedules.length }}
                    </span>
                </div>
                <Button size="sm" @click="openScheduleCreate">
                    <Plus class="size-4" />
                    Добавить
                </Button>
            </header>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="px-3 py-2 font-medium">Услуга</th>
                            <th class="px-3 py-2 font-medium">Расписание</th>
                            <th class="px-3 py-2 font-medium">Следующий запуск</th>
                            <th class="px-3 py-2 font-medium">Последний запуск</th>
                            <th class="px-3 py-2 font-medium">Статус</th>
                            <th class="px-3 py-2 font-medium" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="s in schedules"
                            :key="s.id"
                            class="border-b border-sidebar-border/50 last:border-0 dark:border-sidebar-border"
                        >
                            <td class="px-3 py-2">
                                <div>{{ s.service_label }}</div>
                                <div
                                    v-if="s.service_storage"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ storageLabels[s.service_storage] }}
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <div class="font-mono text-xs">
                                    {{ s.cron_expression }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ s.timezone }}
                                </div>
                            </td>
                            <td class="px-3 py-2 text-xs">
                                {{ formatDate(s.next_run_at) }}
                            </td>
                            <td class="px-3 py-2 text-xs">
                                <div>{{ formatDate(s.last_run_at) }}</div>
                                <Link
                                    v-if="s.last_batch_id"
                                    :href="`/adverts/services/${s.last_batch_id}`"
                                    class="text-xs text-primary underline"
                                >
                                    batch
                                </Link>
                            </td>
                            <td class="px-3 py-2">
                                <Badge
                                    :variant="
                                        s.enabled ? 'secondary' : 'outline'
                                    "
                                    class="cursor-pointer"
                                    @click="toggleSchedule(s)"
                                >
                                    {{ s.enabled ? 'Активно' : 'Выключено' }}
                                </Badge>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        @click="openScheduleEdit(s)"
                                        aria-label="Редактировать"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        @click="deleteSchedule(s)"
                                        aria-label="Удалить"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="schedules.length === 0">
                            <td
                                colspan="6"
                                class="px-3 py-6 text-center text-muted-foreground"
                            >
                                Расписаний нет.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Список объявлений с вкладками -->
        <section
            class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <header
                class="flex items-center justify-between gap-2 border-b border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div class="font-medium">Объявления</div>
                <Button
                    v-if="adverts.length > 0"
                    size="sm"
                    variant="ghost"
                    @click="clearAll"
                >
                    Очистить
                </Button>
            </header>

            <Alert v-if="adverts.length === 0" class="m-4">
                <AlertTitle>Пусто</AlertTitle>
                <AlertDescription>
                    Откройте
                    <Link :href="advertsIndex()" class="underline">
                        «Объявления»
                    </Link>
                    , выделите несколько и нажмите «В группу».
                </AlertDescription>
            </Alert>

            <template v-else>
                <nav
                    class="-mb-px flex flex-wrap gap-6 border-b border-sidebar-border/70 px-4 dark:border-sidebar-border"
                >
                    <button
                        v-for="tab in tabs"
                        :key="tab.value"
                        type="button"
                        @click="activeTab = tab.value"
                        :class="
                            cn(
                                '-mb-px border-b-2 border-transparent px-1 pb-3 pt-3 text-sm font-medium transition-colors',
                                activeTab === tab.value
                                    ? 'border-primary text-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )
                        "
                    >
                        {{ tab.label }} ({{ countsByStatus[tab.value] }})
                    </button>
                </nav>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                        >
                            <tr>
                                <th class="px-3 py-2 font-medium">ID</th>
                                <th class="px-3 py-2 font-medium">Фото</th>
                                <th class="px-3 py-2 font-medium">Название</th>
                                <th class="px-3 py-2 font-medium">Цена</th>
                                <th class="px-3 py-2 font-medium">
                                    {{
                                        activeTab === 'archive'
                                            ? 'Удалится'
                                            : 'Продвигается до'
                                    }}
                                </th>
                                <th class="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="advert in visibleAdverts"
                                :key="advert.id"
                                class="border-b border-sidebar-border/50 last:border-0 dark:border-sidebar-border"
                            >
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
                                <td class="px-3 py-2">
                                    {{ advert.title || '—' }}
                                </td>
                                <td class="px-3 py-2 tabular-nums">
                                    {{ formatPrice(advert.price) }}
                                </td>
                                <td class="px-3 py-2 text-xs">
                                    {{ formatDate(advert.show_till) }}
                                </td>
                                <td class="px-3 py-2">
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        @click="removeAdvert(advert.id)"
                                        aria-label="Убрать"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="visibleAdverts.length === 0">
                                <td
                                    colspan="6"
                                    class="px-3 py-6 text-center text-muted-foreground"
                                >
                                    В этой вкладке пусто.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </section>

        <!-- Rename dialog -->
        <Dialog v-model:open="renameOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Переименовать группу</DialogTitle>
                </DialogHeader>
                <div class="space-y-2">
                    <Label for="rename-name">Название</Label>
                    <Input
                        id="rename-name"
                        v-model="renameForm.name"
                        required
                        autofocus
                    />
                </div>
                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="secondary">Отмена</Button>
                    </DialogClose>
                    <Button
                        :disabled="!renameForm.name.trim()"
                        @click="submitRename"
                    >
                        Сохранить
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Apply dialog -->
        <Dialog v-model:open="applyOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Применение услуги</DialogTitle>
                </DialogHeader>
                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label>Услуга</Label>
                        <Select
                            :model-value="applyForm.service_name"
                            @update:model-value="
                                (v) => (applyForm.service_name = String(v))
                            "
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Выберите услугу" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="svc in available_services"
                                    :key="svc.name"
                                    :value="svc.name"
                                >
                                    {{ svc.label }} ·
                                    {{ storageLabels[svc.storage_id] }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div
                        class="space-y-1 rounded-md border border-sidebar-border/70 bg-muted/30 p-3 text-sm dark:border-sidebar-border"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">
                                Применится к:
                            </span>
                            <span class="font-medium">
                                {{ applyTargetCount }} объявлениям
                            </span>
                        </div>
                        <div
                            v-if="applyUnpricedCount > 0"
                            class="flex items-center justify-between"
                        >
                            <span class="text-muted-foreground">
                                Без цены (пропустим):
                            </span>
                            <span class="text-amber-600 dark:text-amber-400">
                                {{ applyUnpricedCount }}
                            </span>
                        </div>
                        <div
                            class="flex items-center justify-between border-t border-sidebar-border/70 pt-1 dark:border-sidebar-border"
                        >
                            <span class="font-medium">Итого:</span>
                            <span class="text-base font-semibold">
                                {{ formatPrice(applyTotal) }}
                            </span>
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Проверьте баланс kolesa.kz перед применением.
                    </p>
                </div>
                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="secondary">Отмена</Button>
                    </DialogClose>
                    <Button
                        :disabled="
                            !applyForm.service_name || applyTargetCount === 0
                        "
                        @click="submitApply"
                    >
                        Применить
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Schedule dialog -->
        <Dialog v-model:open="scheduleOpen">
            <DialogContent class="max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            editingScheduleId
                                ? 'Изменить расписание'
                                : 'Новое расписание'
                        }}
                    </DialogTitle>
                </DialogHeader>
                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label>Услуга</Label>
                        <Select
                            :model-value="scheduleForm.service_name"
                            @update:model-value="
                                (v) =>
                                    (scheduleForm.service_name = String(v))
                            "
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Выберите услугу" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="svc in available_services"
                                    :key="svc.name"
                                    :value="svc.name"
                                >
                                    {{ svc.label }} ·
                                    {{ storageLabels[svc.storage_id] }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div
                        class="flex gap-4 border-b border-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <button
                            type="button"
                            @click="scheduleMode = 'simple'"
                            class="pb-2 text-sm"
                            :class="
                                scheduleMode === 'simple'
                                    ? 'border-b-2 border-primary font-medium'
                                    : 'text-muted-foreground'
                            "
                        >
                            Простой режим
                        </button>
                        <button
                            type="button"
                            @click="scheduleMode = 'cron'"
                            class="pb-2 text-sm"
                            :class="
                                scheduleMode === 'cron'
                                    ? 'border-b-2 border-primary font-medium'
                                    : 'text-muted-foreground'
                            "
                        >
                            Cron
                        </button>
                    </div>

                    <div v-if="scheduleMode === 'simple'" class="space-y-3">
                        <div class="space-y-2">
                            <Label>Дни недели</Label>
                            <div class="flex flex-wrap gap-1">
                                <button
                                    v-for="d in weekdayOptions"
                                    :key="d.value"
                                    type="button"
                                    @click="toggleWeekday(d.value)"
                                    class="rounded-full border px-3 py-1 text-xs"
                                    :class="
                                        scheduleForm.weekdays.includes(d.value)
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-sidebar-border/70 text-muted-foreground'
                                    "
                                >
                                    {{ d.label }}
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-2">
                                <Label>Час</Label>
                                <Input
                                    v-model.number="scheduleForm.hour"
                                    type="number"
                                    min="0"
                                    max="23"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label>Минута</Label>
                                <Input
                                    v-model.number="scheduleForm.minute"
                                    type="number"
                                    min="0"
                                    max="59"
                                />
                            </div>
                        </div>
                        <div class="text-xs text-muted-foreground">
                            Итоговое cron:
                            <code class="font-mono">{{ builtCron }}</code>
                        </div>
                    </div>

                    <div v-else class="space-y-2">
                        <Label>Cron-выражение</Label>
                        <Input
                            v-model="scheduleForm.cron_expression"
                            placeholder="0 9 * * 1"
                            class="font-mono"
                        />
                        <p class="text-xs text-muted-foreground">
                            Формат: minute hour day month dayOfWeek. Напр.
                            <code>0 9 * * 1</code> — каждый понедельник в 9:00.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label>Timezone</Label>
                        <Input
                            v-model="scheduleForm.timezone"
                            placeholder="Asia/Almaty"
                        />
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="scheduleForm.enabled" type="checkbox" />
                        Включено
                    </label>
                </div>
                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="secondary">Отмена</Button>
                    </DialogClose>
                    <Button
                        :disabled="!scheduleForm.service_name"
                        @click="submitSchedule"
                    >
                        Сохранить
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
