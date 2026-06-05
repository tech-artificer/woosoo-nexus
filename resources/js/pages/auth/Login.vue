<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowRight, ClipboardList, Eye, EyeOff, Info, LoaderCircle, Lock, Mail, ShieldCheck, Tablet, UtensilsCrossed } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    title?: string;
    status?: string;
    warning?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

// Non-sensitive capability tiles — the original mockup showed authenticated
// operational counts (devices/orders/queue), which must not surface pre-login.
const capabilities = [
    { icon: ClipboardList, label: 'Orders', hint: 'Live & synced' },
    { icon: Tablet, label: 'Devices', hint: 'Paired fleet' },
    { icon: UtensilsCrossed, label: 'Menus', hint: 'Service-ready' },
];

const submit = async () => {
    // Ensure CSRF cookie is fresh before submitting — prevents 419 on cold sessions.
    await axios.get('/sanctum/csrf-cookie');
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in" />

    <main id="main-content" class="relative flex min-h-svh overflow-hidden bg-background text-foreground">
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(60%_50%_at_70%_30%,rgba(246,181,109,0.10),transparent_70%)]"
            aria-hidden="true"
        />

        <div class="relative mx-auto flex w-full max-w-[1360px] flex-1 flex-col justify-center px-5 py-6 sm:px-8 lg:px-10 xl:py-8">
            <div class="grid flex-1 items-center gap-8 lg:grid-cols-[minmax(0,1.05fr)_minmax(440px,0.85fr)] 2xl:gap-10">
                <!-- Left · informational panel -->
                <section class="flex flex-col justify-between gap-10 py-2">
                    <div class="flex items-center gap-3">
                        <div class="rounded-2xl border border-border bg-card p-2 shadow-sm">
                            <AppLogoIcon class="h-10 w-10 rounded-xl object-cover" />
                        </div>
                        <div class="leading-tight">
                            <p class="text-base font-semibold tracking-[0.18em] text-foreground uppercase">Woosoo</p>
                            <p class="text-xs font-medium tracking-[0.28em] text-primary uppercase">Nexus · Admin</p>
                        </div>
                    </div>

                    <div class="max-w-xl">
                        <p class="flex items-center gap-2 text-sm font-semibold tracking-[0.28em] text-primary uppercase">
                            <span class="inline-block h-1.5 w-1.5 rounded-full bg-primary" aria-hidden="true" />
                            Operations sign-in
                        </p>
                        <h1 class="mt-5 font-header text-4xl leading-[1.1] font-semibold text-foreground sm:text-5xl">
                            Calm, focused control of the
                            <span class="text-primary">Woosoo back office.</span>
                        </h1>
                        <p class="mt-5 max-w-md text-base leading-7 text-muted-foreground">
                            One entry point for orders, devices, menus and service — the full restaurant stack, kept in sync and out of your way.
                        </p>

                        <!-- Platform / trust card (non-sensitive; no live metrics) -->
                        <div class="mt-8 max-w-md rounded-[1.5rem] border border-border bg-card/60 p-5 backdrop-blur-sm">
                            <div class="flex items-center justify-between gap-4">
                                <p class="text-xs font-semibold tracking-[0.22em] text-muted-foreground uppercase">Woosoo Nexus · Platform</p>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border border-woosoo-green/30 bg-woosoo-green/10 px-2.5 py-1 text-[11px] font-semibold tracking-[0.12em] text-woosoo-green uppercase"
                                >
                                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-woosoo-green" aria-hidden="true" />
                                    Operational
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-3 gap-3">
                                <div
                                    v-for="cap in capabilities"
                                    :key="cap.label"
                                    class="rounded-2xl border border-border/70 bg-background/40 px-3 py-3.5"
                                >
                                    <component :is="cap.icon" class="h-5 w-5 text-primary" aria-hidden="true" />
                                    <p class="mt-2.5 text-sm font-semibold text-foreground">{{ cap.label }}</p>
                                    <p class="mt-0.5 text-xs text-muted-foreground">{{ cap.hint }}</p>
                                </div>
                            </div>

                            <p class="mt-4 border-t border-border/70 pt-3 text-xs text-muted-foreground">
                                Encrypted · Session protected · Verified staff access
                            </p>
                        </div>
                    </div>

                    <p class="text-xs text-muted-foreground">© 2026 Woosoo · Verified staff access · Session protected</p>
                </section>

                <!-- Right · form panel -->
                <section class="flex items-center justify-center lg:justify-end">
                    <div class="w-full max-w-[480px] rounded-[2rem] border border-border bg-card/80 p-6 shadow-xl backdrop-blur-xl sm:p-8">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-border bg-background/50 px-3 py-1.5 text-xs font-medium text-muted-foreground"
                        >
                            <ShieldCheck class="h-3.5 w-3.5 text-primary" aria-hidden="true" />
                            Authorized staff only
                        </div>

                        <div class="mt-5">
                            <p class="text-sm font-semibold tracking-[0.24em] text-primary uppercase">Admin access</p>
                            <h2 class="mt-2 font-header text-3xl font-semibold tracking-tight text-foreground">Welcome back</h2>
                            <p class="mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                                Sign in to manage daily operations, monitor active services and keep the restaurant stack in sync.
                            </p>
                        </div>

                        <div
                            v-if="warning"
                            data-testid="session-warning"
                            class="mt-6 rounded-2xl border border-amber-300/40 bg-amber-400/10 px-4 py-3 text-sm font-medium text-amber-600 dark:text-amber-300"
                            role="status"
                            aria-live="polite"
                        >
                            {{ warning }}
                        </div>

                        <div
                            v-if="status"
                            class="mt-6 rounded-2xl border border-emerald-300/40 bg-emerald-400/10 px-4 py-3 text-sm font-medium text-emerald-600 dark:text-emerald-300"
                            role="status"
                            aria-live="polite"
                        >
                            {{ status }}
                        </div>

                        <form @submit.prevent="submit" class="mt-6 space-y-5" novalidate>
                            <div class="space-y-2.5">
                                <Label for="email" class="text-sm font-semibold text-foreground">Email address</Label>
                                <div class="relative">
                                    <Mail
                                        class="pointer-events-none absolute top-1/2 left-3.5 h-5 w-5 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        required
                                        autofocus
                                        autocomplete="email"
                                        placeholder="manager@woosoo.com"
                                        class="h-12 rounded-2xl pl-11 text-[15px]"
                                        :tabindex="1"
                                        :aria-invalid="Boolean(form.errors.email)"
                                    />
                                </div>
                                <InputError :message="form.errors.email" />
                            </div>

                            <div class="space-y-2.5">
                                <div class="flex items-center justify-between gap-4">
                                    <Label for="password" class="text-sm font-semibold text-foreground">Password</Label>
                                    <TextLink
                                        v-if="canResetPassword"
                                        :href="route('password.request')"
                                        class="text-sm text-primary hover:text-primary/80"
                                        :tabindex="5"
                                    >
                                        Forgot password?
                                    </TextLink>
                                </div>
                                <div class="relative">
                                    <Lock
                                        class="pointer-events-none absolute top-1/2 left-3.5 h-5 w-5 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        id="password"
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        required
                                        autocomplete="current-password"
                                        placeholder="Enter your password"
                                        class="h-12 rounded-2xl px-11 text-[15px]"
                                        :tabindex="2"
                                        :aria-invalid="Boolean(form.errors.password)"
                                    />
                                    <button
                                        type="button"
                                        @click="showPassword = !showPassword"
                                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                        :aria-pressed="showPassword"
                                        class="absolute top-1/2 right-2.5 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg text-muted-foreground transition hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        :tabindex="6"
                                    >
                                        <EyeOff v-if="showPassword" class="h-5 w-5" />
                                        <Eye v-else class="h-5 w-5" />
                                    </button>
                                </div>
                                <InputError :message="form.errors.password" />
                            </div>

                            <div class="flex items-center justify-between gap-4 border-y border-border py-3">
                                <Label for="remember" class="inline-flex cursor-pointer items-center gap-3 text-sm text-muted-foreground">
                                    <Checkbox
                                        id="remember"
                                        v-model="form.remember"
                                        class="size-4 rounded-md data-[state=checked]:border-primary data-[state=checked]:bg-primary"
                                        :tabindex="3"
                                    />
                                    <span>Keep me signed in</span>
                                </Label>
                                <p class="text-sm text-muted-foreground">This device only</p>
                            </div>

                            <Button
                                type="submit"
                                class="h-12 w-full rounded-2xl bg-primary text-sm font-semibold tracking-[0.12em] text-primary-foreground uppercase shadow-lg transition hover:bg-primary/90 focus-visible:ring-4 focus-visible:ring-ring/30 disabled:cursor-not-allowed disabled:opacity-70"
                                :tabindex="4"
                                :disabled="form.processing"
                            >
                                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                                <span>{{ form.processing ? 'Signing in' : 'Enter workspace' }}</span>
                                <ArrowRight class="h-4 w-4" />
                            </Button>
                        </form>

                        <div
                            class="mt-6 flex items-start gap-3 rounded-[1.5rem] border border-border bg-background/40 px-5 py-4 text-sm leading-6 text-muted-foreground"
                        >
                            <Info class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                            <p>
                                Use your assigned staff credentials. If you can't access the workspace, contact your
                                <span class="font-semibold text-foreground">system administrator</span> before creating a new account.
                            </p>
                        </div>

                        <p class="mt-6 text-center text-xs text-muted-foreground">Woosoo Nexus · Restaurant Operations Platform</p>
                    </div>
                </section>
            </div>
        </div>
    </main>
</template>
