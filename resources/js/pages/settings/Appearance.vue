<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Внешний вид',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Внешний вид" />

    <h1 class="sr-only">Внешний вид</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Внешний вид"
            description="Светлая или тёмная тема интерфейса"
        />
        <AppearanceTabs />
    </div>
</template>
