<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Trash2 } from '@lucide/vue';
import { reactive, computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as advertsIndex } from '@/routes/adverts';
import { index as servicesHistory } from '@/routes/adverts/services';

type ServiceOption = {
    name: string;
    label: string;
    price: number;
    type: string;
};

type CartItem = {
    id: number;
    advert_id: number;
    kolesa_id: number | null;
    title: string | null;
    photo_url: string | null;
    advert_status: string | null;
    services: ServiceOption[];
};

type CartView = {
    id: number;
    storage_id: string;
    count: number;
    items: CartItem[];
};

const props = defineProps<{
    carts: CartView[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Объявления', href: advertsIndex() },
            { title: 'Корзина', href: null },
        ],
    },
});

const storageLabels: Record<string, string> = {
    live: 'Активные',
    archive: 'Архив',
};

function serviceOptionsFor(cart: CartView): ServiceOption[] {
    const seen = new Map<string, ServiceOption>();
    cart.items.forEach((item) => {
        item.services.forEach((svc) => {
            if (!seen.has(svc.name)) {
                seen.set(svc.name, { ...svc });
            }
        });
    });
    return [...seen.values()];
}

const selectedService = reactive<Record<number, string>>({});

props.carts.forEach((c) => {
    const options = serviceOptionsFor(c);
    if (options.length > 0) selectedService[c.id] = options[0].name;
});

function priceFor(item: CartItem, serviceName: string | undefined): number | null {
    if (!serviceName) return null;
    const svc = item.services.find((s) => s.name === serviceName);
    return svc?.price ?? null;
}

const totalFor = computed(
    () => (cart: CartView) => {
        const svc = selectedService[cart.id];
        if (!svc) return 0;
        return cart.items.reduce((sum, item) => sum + (priceFor(item, svc) ?? 0), 0);
    },
);

const applicableCountFor = computed(
    () => (cart: CartView) => {
        const svc = selectedService[cart.id];
        if (!svc) return 0;
        return cart.items.filter((i) => priceFor(i, svc) !== null).length;
    },
);

function formatPrice(price: number | null): string {
    if (price === null) return '—';
    return new Intl.NumberFormat('ru-RU').format(price) + ' ₸';
}

function removeItem(cartId: number, itemId: number) {
    router.delete(`/adverts/cart/${cartId}/items/${itemId}`, {
        preserveScroll: true,
        preserveState: true,
    });
}

function clearCart(cartId: number) {
    if (!confirm('Очистить корзину?')) return;
    router.delete(`/adverts/cart/${cartId}`, { preserveScroll: true });
}

function checkout(cart: CartView) {
    const svc = selectedService[cart.id];
    if (!svc) return;

    const total = totalFor.value(cart);
    const count = applicableCountFor.value(cart);
    const serviceLabel =
        cart.items[0]?.services.find((s) => s.name === svc)?.label ?? svc;

    if (
        !confirm(
            `С личного счёта будет списано примерно ${new Intl.NumberFormat('ru-RU').format(total)} ₸ за ${count} объявлений (${serviceLabel}). Убедитесь, что баланс достаточен. Продолжить?`,
        )
    ) {
        return;
    }
    router.post(
        `/adverts/cart/${cart.id}/checkout`,
        { service_name: svc },
        { preserveScroll: false },
    );
}
</script>

<template>
    <Head title="Корзина" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Корзина"
                description="Объявления, подготовленные к применению услуги"
            />
            <div class="flex gap-2">
                <Button as-child variant="outline">
                    <Link :href="servicesHistory()">История</Link>
                </Button>
                <Button as-child variant="outline">
                    <Link :href="advertsIndex()">
                        <ArrowLeft class="size-4" />
                        К объявлениям
                    </Link>
                </Button>
            </div>
        </div>

        <div
            v-if="carts.length === 0"
            class="rounded-xl border border-dashed border-sidebar-border/70 p-10 text-center text-muted-foreground dark:border-sidebar-border"
        >
            Корзина пуста. Выберите объявления на странице
            <Link :href="advertsIndex()" class="underline">
                «Объявления»
            </Link>
            и нажмите «В корзину».
        </div>

        <section
            v-for="cart in carts"
            :key="cart.id"
            class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <header
                class="flex flex-wrap items-center justify-between gap-3 border-b border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">
                        {{ storageLabels[cart.storage_id] ?? cart.storage_id }}
                    </Badge>
                    <span class="text-sm text-muted-foreground">
                        {{ cart.count }} шт
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-muted-foreground">
                            Услуга:
                        </span>
                        <Select
                            :model-value="selectedService[cart.id]"
                            @update:model-value="
                                (v) => (selectedService[cart.id] = String(v))
                            "
                        >
                            <SelectTrigger class="w-56">
                                <SelectValue placeholder="Выберите услугу" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="svc in serviceOptionsFor(cart)"
                                    :key="svc.name"
                                    :value="svc.name"
                                >
                                    {{ svc.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="text-sm">
                        Итого:
                        <span class="font-semibold">
                            {{ formatPrice(totalFor(cart)) }}
                        </span>
                    </div>
                    <Button size="sm" variant="ghost" @click="clearCart(cart.id)">
                        Очистить
                    </Button>
                    <Button
                        size="sm"
                        :disabled="
                            !selectedService[cart.id] ||
                            applicableCountFor(cart) === 0
                        "
                        @click="checkout(cart)"
                    >
                        Применить
                    </Button>
                </div>
            </header>

            <Alert
                v-if="cart.count > 0 && selectedService[cart.id]"
                class="mx-4 mt-4"
            >
                <AlertTitle>Проверьте баланс kolesa.kz</AlertTitle>
                <AlertDescription>
                    После подтверждения со счёта будет списано примерно
                    <b>{{ formatPrice(totalFor(cart)) }}</b> за
                    {{ applicableCountFor(cart) }} из {{ cart.count }}
                    объявлений. Остальные без цены на эту услугу будут
                    пропущены.
                </AlertDescription>
            </Alert>

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
                            <th class="px-3 py-2 font-medium" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in cart.items"
                            :key="item.id"
                            :class="
                                priceFor(item, selectedService[cart.id]) ===
                                    null && 'text-muted-foreground'
                            "
                            class="border-b border-sidebar-border/50 last:border-0 dark:border-sidebar-border"
                        >
                            <td class="px-3 py-2 font-mono text-xs">
                                {{ item.kolesa_id }}
                            </td>
                            <td class="px-3 py-2">
                                <img
                                    v-if="item.photo_url"
                                    :src="item.photo_url"
                                    alt=""
                                    class="h-10 w-16 rounded object-cover"
                                    loading="lazy"
                                />
                            </td>
                            <td class="px-3 py-2">
                                {{ item.title || '—' }}
                            </td>
                            <td class="px-3 py-2 tabular-nums">
                                <template
                                    v-if="
                                        priceFor(
                                            item,
                                            selectedService[cart.id],
                                        ) !== null
                                    "
                                >
                                    {{
                                        formatPrice(
                                            priceFor(
                                                item,
                                                selectedService[cart.id],
                                            ),
                                        )
                                    }}
                                </template>
                                <span v-else class="text-xs">
                                    нет цены
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    @click="removeItem(cart.id, item.id)"
                                    aria-label="Удалить"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="cart.items.length === 0">
                            <td
                                colspan="5"
                                class="px-3 py-6 text-center text-muted-foreground"
                            >
                                В этой корзине ничего нет.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
