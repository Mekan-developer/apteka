<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import AdminController from '@/actions/App/Http/Controllers/AdminController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import { index } from '@/routes/admins';
import type { Admin } from '@/types';

defineProps<{
    admins: Admin[];
}>();

const { t } = useTrans();

setLayoutProps({
    breadcrumbs: [{ title: t('admins.title'), href: index() }],
});
</script>

<template>
    <Head :title="t('admins.title')" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <Heading
            :title="t('admins.title')"
            :description="t('admins.description')"
        />

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">
                            {{ t('admins.name') }}
                        </th>
                        <th class="px-4 py-2 font-medium">
                            {{ t('admins.email') }}
                        </th>
                        <th class="px-4 py-2 font-medium">
                            {{ t('admins.columns.role') }}
                        </th>
                        <th class="px-4 py-2 font-medium">
                            {{ t('admins.columns.status') }}
                        </th>
                        <th class="px-4 py-2 text-right font-medium">
                            {{ t('admins.columns.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="admin in admins"
                        :key="admin.id"
                        class="border-t"
                    >
                        <td class="px-4 py-2 font-medium">{{ admin.name }}</td>
                        <td class="px-4 py-2">{{ admin.email }}</td>
                        <td class="px-4 py-2">
                            <Badge
                                :variant="
                                    admin.role === 'super_admin'
                                        ? 'default'
                                        : 'outline'
                                "
                            >
                                {{ t(`admins.roles.${admin.role}`) }}
                            </Badge>
                        </td>
                        <td class="px-4 py-2">
                            <Badge
                                :variant="
                                    admin.is_active ? 'outline' : 'destructive'
                                "
                            >
                                {{
                                    admin.is_active
                                        ? t('admins.active')
                                        : t('admins.inactive')
                                }}
                            </Badge>
                        </td>
                        <td class="px-4 py-2">
                            <div
                                v-if="admin.can_manage"
                                class="flex justify-end gap-2"
                            >
                                <Form
                                    v-bind="
                                        AdminController.toggleStatus.form(
                                            admin.id,
                                        )
                                    "
                                    :options="{ preserveScroll: true }"
                                    v-slot="{ processing }"
                                >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="processing"
                                    >
                                        {{
                                            admin.is_active
                                                ? t('admins.deactivate')
                                                : t('admins.activate')
                                        }}
                                    </Button>
                                </Form>

                                <Dialog>
                                    <DialogTrigger as-child>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            >{{ t('admins.delete') }}</Button
                                        >
                                    </DialogTrigger>
                                    <DialogContent>
                                        <Form
                                            v-bind="
                                                AdminController.destroy.form(
                                                    admin.id,
                                                )
                                            "
                                            :options="{ preserveScroll: true }"
                                            class="space-y-6"
                                            v-slot="{ processing }"
                                        >
                                            <DialogHeader class="space-y-3">
                                                <DialogTitle>{{
                                                    t('admins.delete_title')
                                                }}</DialogTitle>
                                                <DialogDescription>
                                                    {{
                                                        t(
                                                            'admins.delete_description',
                                                            {
                                                                name: admin.name,
                                                            },
                                                        )
                                                    }}
                                                </DialogDescription>
                                            </DialogHeader>
                                            <DialogFooter class="gap-2">
                                                <DialogClose as-child>
                                                    <Button
                                                        variant="secondary"
                                                        >{{
                                                            t('admins.cancel')
                                                        }}</Button
                                                    >
                                                </DialogClose>
                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                    :disabled="processing"
                                                >
                                                    {{ t('admins.delete') }}
                                                </Button>
                                            </DialogFooter>
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <section class="max-w-xl space-y-4 rounded-xl border p-4">
            <Heading variant="small" :title="t('admins.create_title')" />

            <Form
                v-bind="AdminController.store.form()"
                reset-on-success
                class="space-y-4"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">{{ t('admins.name') }}</Label>
                    <Input id="name" name="name" required autocomplete="off" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">{{ t('admins.email') }}</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autocomplete="off"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">{{ t('admins.password') }}</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        autocomplete="new-password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">{{
                        t('admins.password_confirmation')
                    }}</Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                    />
                </div>

                <Button :disabled="processing" data-test="create-admin-button">
                    {{ t('admins.create_button') }}
                </Button>
            </Form>
        </section>
    </div>
</template>
