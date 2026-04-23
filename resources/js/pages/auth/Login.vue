<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { asset } from '@/lib/utils';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle2, LoaderCircle, ShieldCheck } from 'lucide-vue-next';

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

const coverImage = asset('images/Woosoo Cover Photo_Artboard 1.png');

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in" />

    <main
        id="main-content"
        class="relative flex min-h-svh overflow-hidden bg-[linear-gradient(180deg,#fff8f0_0%,#fff4e7_42%,#f7efe6_100%)] text-foreground"
    >
        <div class="absolute inset-x-0 top-0 h-64 bg-[radial-gradient(circle_at_top_left,rgba(246,181,109,0.28),transparent_58%)]" />
        <div class="absolute inset-y-0 right-0 hidden w-2/5 bg-[radial-gradient(circle_at_center,rgba(37,37,37,0.14),transparent_70%)] lg:block" />

        <div class="relative mx-auto flex w-full max-w-[1360px] flex-1 flex-col justify-center px-5 py-4 sm:px-8 sm:py-6 lg:px-10 lg:py-6 xl:py-8">
            <div class="grid flex-1 items-center gap-5 xl:grid-cols-[minmax(0,0.96fr)_minmax(460px,0.88fr)] 2xl:gap-6">
                <section class="relative hidden self-stretch overflow-hidden rounded-[2rem] border border-white/65 bg-[#252525] text-white shadow-[0_24px_80px_rgba(37,37,37,0.22)] xl:flex xl:max-h-[calc(100svh-4rem)]">
                    <img
                        :src="coverImage"
                        alt="Woosoo restaurant interior"
                        width="1200"
                        height="1600"
                        class="absolute inset-0 h-full w-full object-cover opacity-24"
                    >
                    <div class="absolute inset-0 bg-[linear-gradient(145deg,rgba(37,37,37,0.88),rgba(37,37,37,0.62),rgba(176,128,71,0.66))]" />

                    <div class="relative flex h-full w-full flex-col justify-between p-6 sm:p-7 xl:p-8">
                        <div class="flex items-center justify-between gap-4">
                            <Link :href="route('home')" class="inline-flex items-center gap-3 rounded-full border border-white/15 bg-white/8 px-4 py-2 text-sm font-medium tracking-[0.18em] text-white/92 uppercase backdrop-blur-sm transition hover:bg-white/14 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70 focus-visible:ring-offset-2 focus-visible:ring-offset-transparent">
                                <AppLogoIcon class="h-11 w-11 rounded-full object-cover" />
                                <span>{{ title ?? 'Woosoo Admin' }}</span>
                            </Link>
                            <div class="hidden rounded-full border border-white/10 bg-white/10 px-4 py-2 text-xs font-medium text-white/75 backdrop-blur-sm sm:block">
                                Verified staff access
                            </div>
                        </div>

                        <div class="mx-auto flex max-w-lg flex-1 flex-col justify-center text-left">
                            <p class="text-sm font-semibold tracking-[0.28em] text-[#f6b56d] uppercase">Operations sign-in</p>
                            <h1 class="mt-4 font-header text-4xl font-semibold leading-tight text-white 2xl:text-5xl">
                                Calm, focused access to the Woosoo back office.
                            </h1>
                            <p class="mt-5 max-w-md text-base leading-7 text-white/76">
                                Sign in to manage orders, devices, menus, and service settings without the clutter.
                            </p>

                            <div class="mt-8 space-y-4">
                                <div class="flex items-start gap-3 rounded-[1.2rem] border border-white/12 bg-white/8 px-4 py-4 backdrop-blur-sm">
                                    <ShieldCheck class="mt-0.5 h-5 w-5 shrink-0 text-[#f6b56d]" />
                                    <div>
                                        <p class="text-sm font-semibold tracking-[0.08em] text-white uppercase">Secure admin workspace</p>
                                        <p class="mt-1 text-sm leading-6 text-white/72">One entry point for operational controls, with verified staff access and session protection.</p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-3 rounded-[1.2rem] border border-white/12 bg-black/16 px-4 py-4 backdrop-blur-sm">
                                    <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-[#f6b56d]" />
                                    <div>
                                        <p class="text-sm font-semibold tracking-[0.08em] text-white uppercase">What you can manage</p>
                                        <p class="mt-1 text-sm leading-6 text-white/72">Dashboard, devices, menus, reporting, configuration, and other daily back-office tools.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="flex items-center justify-center xl:justify-end">
                    <div class="w-full max-w-[540px] rounded-[2rem] border border-[#f0dcc4] bg-white/92 p-6 shadow-[0_22px_60px_rgba(176,128,71,0.14)] backdrop-blur-xl sm:p-7 lg:p-8">
                        <div class="flex flex-col gap-4">
                            <div class="flex items-center gap-3">
                                <div class="rounded-2xl bg-[#fff1df] p-2.5 shadow-[inset_0_1px_0_rgba(255,255,255,0.7)]">
                                    <AppLogoIcon class="h-12 w-12 rounded-[1rem] object-cover" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-[#7a6c5d]">Restaurant operations workspace</p>
                                </div>
                            </div>

                            <div>
                                <p class="text-sm font-semibold tracking-[0.24em] text-[#b08047] uppercase">Admin access</p>
                                <h2 class="mt-2 font-header text-3xl font-semibold tracking-tight text-[#252525]">Welcome back</h2>
                                <p class="mt-2 max-w-md text-sm leading-6 text-[#6f6255] sm:text-[15px]">
                                    Sign in to manage daily operations, monitor active services, and keep the restaurant stack in sync.
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="status"
                            class="mt-6 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700"
                            role="status"
                            aria-live="polite"
                        >
                            {{ status }}
                        </div>

                        <form @submit.prevent="submit" class="mt-6 space-y-5" novalidate>
                            <div class="space-y-2.5">
                                <Label for="email" class="text-sm font-semibold text-[#3b3228]">Email address</Label>
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    autofocus
                                    autocomplete="email"
                                    placeholder="manager@woosoo.com"
                                    class="h-12 rounded-2xl border-[#e7c49a] bg-[#fffaf4] px-4 text-[15px] shadow-none transition focus-visible:border-[#b08047] focus-visible:ring-4 focus-visible:ring-[#f6b56d]/25"
                                    :tabindex="1"
                                    :aria-invalid="Boolean(form.errors.email)"
                                />
                                <InputError :message="form.errors.email" />
                            </div>

                            <div class="space-y-2.5">
                                <div class="flex items-center justify-between gap-4">
                                    <Label for="password" class="text-sm font-semibold text-[#3b3228]">Password</Label>
                                    <TextLink
                                        v-if="canResetPassword"
                                        :href="route('password.request')"
                                        class="text-sm text-[#8d5f2d] decoration-[#d8b792] hover:text-[#6f4b24]"
                                        :tabindex="5"
                                    >
                                        Forgot password?
                                    </TextLink>
                                </div>
                                <Input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="Enter your password"
                                    class="h-12 rounded-2xl border-[#e7c49a] bg-[#fffaf4] px-4 text-[15px] shadow-none transition focus-visible:border-[#b08047] focus-visible:ring-4 focus-visible:ring-[#f6b56d]/25"
                                    :tabindex="2"
                                    :aria-invalid="Boolean(form.errors.password)"
                                />
                                <InputError :message="form.errors.password" />
                            </div>

                            <div class="flex flex-col gap-3 border-y border-[#f4e4d0] py-3 sm:flex-row sm:items-center sm:justify-between">
                                <Label for="remember" class="inline-flex cursor-pointer items-center gap-3 text-sm text-[#5f5348]">
                                    <Checkbox
                                        id="remember"
                                        v-model="form.remember"
                                        class="size-4 rounded-md border-[#d7b58d] data-[state=checked]:border-[#b08047] data-[state=checked]:bg-[#b08047]"
                                        :tabindex="3"
                                    />
                                    <span>Keep me signed in on this device</span>
                                </Label>
                                <p class="text-sm text-[#8b7b6a]">Authorized staff only</p>
                            </div>

                            <Button
                                type="submit"
                                class="h-12 w-full rounded-2xl bg-[#252525] text-sm font-semibold tracking-[0.12em] text-white uppercase shadow-[0_16px_30px_rgba(37,37,37,0.18)] transition hover:bg-[#1a1a1a] focus-visible:ring-4 focus-visible:ring-[#f6b56d]/25 disabled:cursor-not-allowed disabled:opacity-70"
                                :tabindex="4"
                                :disabled="form.processing"
                            >
                                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                                <span>{{ form.processing ? 'Signing in' : 'Enter workspace' }}</span>
                                <ArrowRight class="h-4 w-4" />
                            </Button>
                        </form>

                        <div class="mt-6 rounded-[1.5rem] border border-[#f3e1cd] bg-[#fff8f1] px-5 py-4 text-sm leading-6 text-[#6f6255]">
                            Use your assigned staff credentials. If you cannot access the workspace, contact the system administrator before creating a new account.
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>
</template>
