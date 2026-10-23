<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import DrugSynonymController from '@/actions/App/Http/Controllers/DrugSynonymController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import { index } from '@/routes/synonyms';
import type { DrugSynonym } from '@/types';

defineProps<{
    synonyms: DrugSynonym[];
}>();

const { t } = useTrans();

setLayoutProps({
    breadcrumbs: [{ title: t('synonyms.title'), href: index() }],
});
</script>

<template>
    <Head :title="t('synonyms.title')" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <Heading
            :title="t('synonyms.title')"
            :description="t('synonyms.description')"
        />

        <section class="max-w-3xl space-y-4 rounded-xl border p-4">
            <Heading variant="small" :title="t('synonyms.create_title')" />

            <Form
                v-bind="DrugSynonymController.store.form()"
                reset-on-success
                :options="{ preserveScroll: true }"
                class="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-start"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="term">{{ t('synonyms.term') }}</Label>
                    <Input
                        id="term"
                        name="term"
                        required
                        maxlength="100"
                        :placeholder="t('synonyms.term_placeholder')"
                    />
                    <InputError :message="errors.term" />
                </div>

                <div class="grid gap-2">
                    <Label for="synonym">{{ t('synonyms.synonym') }}</Label>
                    <Input
                        id="synonym"
                        name="synonym"
                        required
                        maxlength="100"
                        :placeholder="t('synonyms.synonym_placeholder')"
                    />
                    <InputError :message="errors.synonym" />
                </div>

                <Button
                    class="sm:mt-[22px]"
                    :disabled="processing"
                    data-test="create-synonym-button"
                >
                    {{ t('synonyms.create_button') }}
                </Button>
            </Form>
        </section>

        <p v-if="synonyms.length === 0" class="text-sm text-muted-foreground">
            {{ t('synonyms.empty') }}
        </p>

        <div v-else class="max-w-3xl overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">
                            {{ t('synonyms.term') }}
                        </th>
                        <th class="px-4 py-2 font-medium" />
                        <th class="px-4 py-2 font-medium">
                            {{ t('synonyms.synonym') }}
                        </th>
                        <th class="px-4 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="synonym in synonyms"
                        :key="synonym.id"
                        class="border-t"
                    >
                        <td class="px-4 py-2 font-medium">
                            {{ synonym.term }}
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">=</td>
                        <td class="px-4 py-2">{{ synonym.synonym }}</td>
                        <td class="px-4 py-2 text-right">
                            <Form
                                v-bind="
                                    DrugSynonymController.destroy.form(
                                        synonym.id,
                                    )
                                "
                                :options="{ preserveScroll: true }"
                                v-slot="{ processing }"
                            >
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :disabled="processing"
                                    >{{ t('synonyms.delete') }}</Button
                                >
                            </Form>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
