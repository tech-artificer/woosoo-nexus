<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem  } from '@/types';
import { type Branch } from '@/types/models';

const props = defineProps<{
    title?: string
    description?: string
    branch: Branch
}>()

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: '/settings/profile',
    },
];

const form = useForm({
    name: props.branch.name,
    location: props.branch.location,
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Profile information" description="Update your name and email address" />

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input id="name" class="mt-1 block w-full" v-model="form.name" required autocomplete="name" placeholder="Name" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input id="name" class="mt-1 block w-full" v-model="form.name" required autocomplete="name" placeholder="Name" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>
                </form>
            </div>

            <!-- <DeleteUser /> -->
        </SettingsLayout>
     </AppLayout> 
</template>