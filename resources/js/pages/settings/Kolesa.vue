<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import KolesaController from '@/actions/App/Http/Controllers/Settings/KolesaController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/kolesa';

defineProps<{
    hasToken: boolean;
    tokenUpdatedAt: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Kolesa.kz',
                href: edit(),
            },
        ],
    },
});

function formatUpdatedAt(iso: string | null): string {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return iso;
    }
}
</script>

<template>
    <Head title="Kolesa.kz" />

    <h1 class="sr-only">Настройки Kolesa.kz</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Kolesa.kz"
            description="X-AUTH-TOKEN для синхронизации объявлений"
        />

        <Badge v-if="hasToken" variant="secondary">
            Токен сохранён
            <span v-if="tokenUpdatedAt" class="ml-2 text-muted-foreground">
                · {{ formatUpdatedAt(tokenUpdatedAt) }}
            </span>
        </Badge>

        <Alert v-else variant="destructive">
            <AlertTitle>Токен не настроен</AlertTitle>
            <AlertDescription>
                Синхронизация не запустится, пока не сохранён токен.
            </AlertDescription>
        </Alert>

        <Form
            v-bind="KolesaController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="token">X-AUTH-TOKEN</Label>
                <PasswordInput
                    id="token"
                    class="mt-1 block w-full"
                    name="token"
                    required
                    autocomplete="off"
                    placeholder="01a16285-4b7d-717f-9422-..."
                />
                <InputError class="mt-2" :message="errors.token" />
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="save-kolesa-token-button">
                    Сохранить
                </Button>
            </div>
        </Form>
    </div>
</template>
