<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarClock, FolderOpen, Plus } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
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
import { index as advertsIndex } from '@/routes/adverts';

type GroupRow = {
    id: number;
    name: string;
    adverts_count: number;
    live_count: number;
    archive_count: number;
    schedules_count: number;
    enabled_schedules: number;
};

defineProps<{
    groups: GroupRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Объявления', href: advertsIndex() },
            { title: 'Группы', href: null },
        ],
    },
});

const createOpen = ref(false);
const form = ref({ name: '' });

function openCreate() {
    form.value = { name: '' };
    createOpen.value = true;
}

function submitCreate() {
    router.post('/adverts/groups', form.value, {
        onSuccess: () => {
            createOpen.value = false;
        },
    });
}
</script>

<template>
    <Head title="Группы" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Группы"
                description="Заготовленные наборы объявлений для массового применения услуг"
            />
            <Button @click="openCreate">
                <Plus class="size-4" />
                Создать группу
            </Button>
        </div>

        <div
            v-if="groups.length === 0"
            class="rounded-xl border border-dashed border-sidebar-border/70 p-10 text-center text-muted-foreground dark:border-sidebar-border"
        >
            Групп ещё нет. Создайте первую.
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="group in groups"
                :key="group.id"
                :href="`/adverts/groups/${group.id}`"
                class="group rounded-xl border border-sidebar-border/70 p-4 transition-colors hover:border-primary/50 dark:border-sidebar-border"
            >
                <div class="flex items-start gap-2">
                    <FolderOpen class="mt-0.5 size-5 text-muted-foreground" />
                    <div class="flex-1 space-y-2">
                        <div class="font-medium">{{ group.name }}</div>
                        <div class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                            <span>Всего: {{ group.adverts_count }}</span>
                            <span>Активных: {{ group.live_count }}</span>
                            <span>Архив: {{ group.archive_count }}</span>
                            <span
                                v-if="group.schedules_count > 0"
                                class="inline-flex items-center gap-1"
                            >
                                <CalendarClock class="size-3" />
                                {{ group.enabled_schedules }} /
                                {{ group.schedules_count }}
                            </span>
                        </div>
                    </div>
                </div>
            </Link>
        </div>

        <Dialog v-model:open="createOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Новая группа</DialogTitle>
                </DialogHeader>
                <div class="space-y-2">
                    <Label for="group-name">Название</Label>
                    <Input
                        id="group-name"
                        v-model="form.name"
                        placeholder="Mitsubishi Airtrek — двигатель"
                        required
                        autofocus
                    />
                </div>
                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="secondary">Отмена</Button>
                    </DialogClose>
                    <Button
                        :disabled="!form.name.trim()"
                        @click="submitCreate"
                    >
                        Создать
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
