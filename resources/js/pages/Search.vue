<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import { home, login } from '@/routes';
import { index as priceUploads } from '@/routes/price-uploads';
import type { DiscountRule, SearchGroup } from '@/types';

defineProps<{
    query: string;
    pricesDate: string | null;
    groups: SearchGroup[];
    suggestions: string[];
}>();

const page = usePage();
const { t, locale } = useTrans();

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString(locale());
}

function formatPrice(price: string): string {
    return Number(price).toLocaleString(locale(), {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function discountLabel(discount: DiscountRule): string {
    const parts = [
        t(`price_uploads.discounts.${discount.type}`, {
            percent: Number(discount.percent),
        }),
    ];

    if (discount.min_sum !== null) {
        parts.push(
            t('price_uploads.discounts.from_sum', {
                sum: Number(discount.min_sum).toLocaleString(locale()),
            }),
        );
    } else if (discount.is_conditional) {
        parts.push(t('price_uploads.discounts.conditional'));
    }

    return parts.join(' ');
}
</script>

<template>
    <Head :title="t('search.title')" />

    <div class="min-h-screen bg-background text-foreground">
        <header
            class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4"
        >
            <Link :href="home()" class="flex items-center gap-2 font-semibold">
                <AppLogoIcon class="size-6 fill-current" />
                <span>{{ page.props.name }}</span>
            </Link>
            <div class="flex items-center gap-2">
                <LocaleSwitcher />
                <Button variant="outline" size="sm" as-child>
                    <Link
                        :href="page.props.auth.user ? priceUploads() : login()"
                    >
                        {{
                            page.props.auth.user
                                ? t('search.admin')
                                : t('search.sign_in')
                        }}
                    </Link>
                </Button>
            </div>
        </header>

        <main class="mx-auto flex max-w-5xl flex-col gap-8 px-4 pt-6 pb-16">
            <section class="space-y-4">
                <div class="space-y-1">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ t('search.title') }}
                    </h1>
                    <p class="text-muted-foreground">
                        {{ t('search.description') }}
                    </p>
                </div>

                <Form
                    v-bind="home.form()"
                    class="flex flex-col gap-2 sm:flex-row sm:items-end"
                    v-slot="{ processing }"
                >
                    <div class="grid flex-1 gap-2">
                        <Label for="q" class="sr-only">{{
                            t('search.label')
                        }}</Label>
                        <Input
                            id="q"
                            name="q"
                            type="search"
                            :default-value="query"
                            :placeholder="t('search.placeholder')"
                            autocomplete="off"
                            maxlength="100"
                            required
                            autofocus
                        />
                    </div>
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="search-button"
                        >{{ t('search.button') }}</Button
                    >
                </Form>
                <InputError :message="page.props.errors.q" />

                <p v-if="pricesDate" class="text-sm text-muted-foreground">
                    {{
                        t('search.prices_date', {
                            date: formatDate(pricesDate),
                        })
                    }}
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    {{ t('search.no_prices') }}
                </p>
            </section>

            <section
                v-if="query && pricesDate && groups.length === 0"
                class="space-y-3"
            >
                <p>{{ t('search.nothing_found', { query }) }}</p>
                <div
                    v-if="suggestions.length"
                    class="flex flex-wrap items-center gap-2"
                >
                    <span class="text-sm text-muted-foreground">{{
                        t('search.did_you_mean')
                    }}</span>
                    <Button
                        v-for="suggestion in suggestions"
                        :key="suggestion"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <Link :href="home({ query: { q: suggestion } })">{{
                            suggestion
                        }}</Link>
                    </Button>
                </div>
            </section>

            <section
                v-for="group in groups"
                :key="group.pack_count ?? 'none'"
                class="space-y-3"
            >
                <h2 class="text-lg font-semibold">
                    {{
                        group.pack_count === null
                            ? t('search.no_pack')
                            : t('search.pack', { count: group.pack_count })
                    }}
                </h2>

                <div class="grid gap-4 md:grid-cols-3">
                    <article
                        v-for="offer in group.offers"
                        :key="offer.id"
                        class="flex flex-col gap-3 rounded-xl border p-4"
                        :class="offer.place === 1 ? 'border-primary' : ''"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold">
                                    {{ offer.pharmacy?.name }}
                                </p>
                                <a
                                    v-if="offer.pharmacy?.phone"
                                    :href="`tel:${offer.pharmacy.phone.replace(/[^+\d]/g, '')}`"
                                    class="text-sm text-muted-foreground underline-offset-4 hover:underline"
                                >
                                    {{ offer.pharmacy.phone }}
                                </a>
                            </div>
                            <Badge
                                :variant="
                                    offer.place === 1 ? 'default' : 'outline'
                                "
                            >
                                {{ t('search.place', { place: offer.place }) }}
                            </Badge>
                        </div>

                        <div>
                            <p class="text-sm">{{ offer.raw_name }}</p>
                            <p
                                v-if="offer.manufacturer"
                                class="text-xs text-muted-foreground"
                            >
                                {{ offer.manufacturer }}
                            </p>
                        </div>

                        <div class="mt-auto space-y-1">
                            <p class="text-2xl font-semibold tabular-nums">
                                {{ formatPrice(offer.effective_price) }}
                                <span
                                    class="text-base font-normal text-muted-foreground"
                                    >{{ t('search.manat') }}</span
                                >
                            </p>
                            <p
                                v-if="offer.effective_price !== offer.price"
                                class="text-sm text-muted-foreground"
                            >
                                {{
                                    t('search.price_before_discount', {
                                        price: formatPrice(offer.price),
                                    })
                                }}
                            </p>
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-if="offer.goods_discount_percent"
                                    variant="secondary"
                                >
                                    {{
                                        t('search.goods_discount', {
                                            percent: Number(
                                                offer.goods_discount_percent,
                                            ),
                                        })
                                    }}
                                </Badge>
                                <Badge
                                    v-for="discount in offer.sheet_discounts ??
                                    []"
                                    :key="discount.id"
                                    variant="outline"
                                >
                                    {{ discountLabel(discount) }}
                                </Badge>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </main>
    </div>
</template>
