<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Lock } from 'lucide-vue-next';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

defineProps<{
    title?: string;
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in" />

    <div class="flex min-h-svh">
        <!-- Left panel — branding hero -->
        <div class="relative hidden lg:flex lg:w-1/2 xl:w-3/5 flex-col overflow-hidden bg-woosoo-dark-gray">
            <!-- Cover image -->
            <img
                src="images/Woosoo Cover Photo_Artboard 1.png"
                alt="Woosoo Restaurant"
                class="absolute inset-0 h-full w-full object-cover opacity-70"
            />
            <!-- Gradient overlay — bottom fade for legibility -->
            <div class="absolute inset-0 bg-linear-to-t from-black/80 via-black/20 to-transparent"></div>

            <!-- Brand lockup — bottom-left -->
            <div class="absolute bottom-10 left-10 right-10 z-10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 backdrop-blur-sm border border-white/20">
                        <AppLogoIcon class="h-8 w-8 object-contain" />
                    </div>
                    <span class="font-header font-bold text-white text-lg tracking-wide">Woosoo Admin</span>
                </div>
                <h2 class="font-header text-3xl font-bold text-white leading-snug mb-2">
                    Manage your restaurant<br />
                    <span class="text-woosoo-accent">with confidence.</span>
                </h2>
                <p class="text-white/60 text-sm">Orders, menus, devices — all in one place.</p>
            </div>
        </div>

        <!-- Right panel — login form -->
        <div class="flex flex-1 flex-col items-center justify-center bg-background px-6 py-12 lg:px-12 xl:px-20">
            <!-- Mobile logo -->
            <div class="flex lg:hidden items-center gap-2 mb-10">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg overflow-hidden">
                    <AppLogoIcon class="h-8 w-8 object-contain" />
                </div>
                <span class="font-header font-bold text-lg">Woosoo Admin</span>
            </div>

            <div class="w-full max-w-sm">
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-woosoo-accent/10 border border-woosoo-accent/30 mb-5">
                        <Lock class="h-5 w-5 text-woosoo-accent" />
                    </div>
                    <h1 class="font-header text-2xl font-bold tracking-tight text-foreground">Welcome back</h1>
                    <p class="mt-1.5 text-sm text-muted-foreground">Sign in to your admin account</p>
                </div>

                <!-- Status message (e.g. password reset confirmation) -->
                <div v-if="status" class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700 dark:border-green-800 dark:bg-green-950 dark:text-green-400">
                    {{ status }}
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="flex flex-col gap-5">
                    <div class="grid gap-1.5">
                        <Label for="email" class="text-sm font-medium">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="email"
                            v-model="form.email"
                            placeholder="you@example.com"
                            class="h-10 border-woosoo-accent/40 focus-visible:border-woosoo-accent focus-visible:ring-woosoo-accent/30"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="grid gap-1.5">
                        <div class="flex items-center justify-between">
                            <Label for="password" class="text-sm font-medium">Password</Label>
                            <TextLink
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-xs text-woosoo-accent hover:text-woosoo-primary-dark transition-colors"
                                :tabindex="4"
                            >
                                Forgot password?
                            </TextLink>
                        </div>
                        <Input
                            id="password"
                            type="password"
                            required
                            :tabindex="2"
                            autocomplete="current-password"
                            v-model="form.password"
                            placeholder="••••••••"
                            class="h-10 border-woosoo-accent/40 focus-visible:border-woosoo-accent focus-visible:ring-woosoo-accent/30"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div class="flex items-center gap-2.5">
                        <Checkbox id="remember" v-model="form.remember" :tabindex="3" />
                        <Label for="remember" class="text-sm font-normal text-muted-foreground cursor-pointer select-none">
                            Keep me signed in
                        </Label>
                    </div>

                    <Button
                        type="submit"
                        :tabindex="5"
                        :disabled="form.processing"
                        class="mt-1 h-10 w-full bg-woosoo-accent hover:bg-woosoo-primary-dark text-woosoo-dark-gray font-semibold transition-colors"
                    >
                        <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                        <span>{{ form.processing ? 'Signing in…' : 'Sign in' }}</span>
                    </Button>
                </form>

                <!-- Footer -->
                <p class="mt-8 text-center text-xs text-muted-foreground">
                    &copy; {{ new Date().getFullYear() }} Woosoo. Restaurant management system.
                </p>
            </div>
        </div>
    </div>
</template>
