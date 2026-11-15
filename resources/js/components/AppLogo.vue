<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
    >
        <AppLogoIcon class="size-5 fill-current" />
    </div>
    <div class="ml-2 grid flex-1 text-left">
        <span class="text-sm font-semibold tracking-tight leading-tight">
            Kolesa CRM
        </span>
        <span class="text-[11px] uppercase tracking-widest text-muted-foreground leading-tight">
            Parts
        </span>
    </div>
</template>
