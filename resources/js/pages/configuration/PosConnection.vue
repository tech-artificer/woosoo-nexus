<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { type BreadcrumbItem } from '@/types';
import { CheckCircle, XCircle, Loader2, DatabaseZap } from 'lucide-vue-next';
import axios from 'axios';

interface PosConnection {
    host: string;
    port: string;
    database: string;
    username: string;
    has_password: boolean;
}

const props = defineProps<{
    connection: PosConnection | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuration', href: route('configuration.index') ?? '/configuration' },
    { title: 'POS Database Connection', href: route('pos-connection.index') },
];

const form = useForm({
    host:     props.connection?.host     ?? '',
    port:     props.connection?.port     ?? '3306',
    database: props.connection?.database ?? '',
    username: props.connection?.username ?? '',
    password: '',
});

const isConfigured = computed(() => props.connection !== null);

// Test connection state
const testing   = ref(false);
const testResult = ref<{ success: boolean; message: string } | null>(null);

async function testConnection() {
    testing.value   = true;
    testResult.value = null;

    try {
        const { data } = await axios.post(route('pos-connection.test'), {
            host:     form.host,
            port:     form.port,
            database: form.database,
            username: form.username,
            password: form.password,
        });
        testResult.value = data;
    } catch (err: any) {
        testResult.value = {
            success: false,
            message: err?.response?.data?.message ?? 'Request failed.',
        };
    } finally {
        testing.value = false;
    }
}

function save() {
    form.put(route('pos-connection.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.password = '';
            testResult.value = null;
        },
    });
}
</script>

<template>
    <Head title="POS Database Connection" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6 max-w-2xl">

            <div>
                <h1 class="text-2xl font-bold tracking-tight flex items-center gap-2">
                    <DatabaseZap class="h-6 w-6" />
                    POS Database Connection
                </h1>
                <p class="text-muted-foreground mt-1">
                    Configure the IP address and credentials for the Krypton POS database
                    running on your Windows POS unit.
                </p>
            </div>

            <!-- Status badge -->
            <div class="flex items-center gap-2">
                <Badge v-if="isConfigured" variant="default" class="gap-1">
                    <CheckCircle class="h-3 w-3" />
                    Configured
                </Badge>
                <Badge v-else variant="secondary" class="gap-1">
                    <XCircle class="h-3 w-3" />
                    Not configured — using .env defaults
                </Badge>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Connection Settings</CardTitle>
                    <CardDescription>
                        These credentials are stored encrypted in the database and override
                        the <code class="text-xs">DB_POS_*</code> environment variables at runtime.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="save" class="space-y-4">

                        <div class="grid grid-cols-3 gap-4">
                            <div class="col-span-2 space-y-1">
                                <Label for="host">Host / IP Address</Label>
                                <Input
                                    id="host"
                                    v-model="form.host"
                                    placeholder="192.168.1.100"
                                    :class="{ 'border-destructive': form.errors.host }"
                                />
                                <p v-if="form.errors.host" class="text-xs text-destructive">{{ form.errors.host }}</p>
                            </div>
                            <div class="space-y-1">
                                <Label for="port">Port</Label>
                                <Input
                                    id="port"
                                    v-model="form.port"
                                    placeholder="3306"
                                    :class="{ 'border-destructive': form.errors.port }"
                                />
                                <p v-if="form.errors.port" class="text-xs text-destructive">{{ form.errors.port }}</p>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <Label for="database">Database Name</Label>
                            <Input
                                id="database"
                                v-model="form.database"
                                placeholder="krypton_woosoo"
                                :class="{ 'border-destructive': form.errors.database }"
                            />
                            <p v-if="form.errors.database" class="text-xs text-destructive">{{ form.errors.database }}</p>
                        </div>

                        <div class="space-y-1">
                            <Label for="username">Username</Label>
                            <Input
                                id="username"
                                v-model="form.username"
                                placeholder="pos_user"
                                autocomplete="username"
                                :class="{ 'border-destructive': form.errors.username }"
                            />
                            <p v-if="form.errors.username" class="text-xs text-destructive">{{ form.errors.username }}</p>
                        </div>

                        <div class="space-y-1">
                            <Label for="password">Password</Label>
                            <Input
                                id="password"
                                type="password"
                                v-model="form.password"
                                :placeholder="isConfigured && connection?.has_password ? '••••••••  (leave blank to keep current)' : 'Enter password'"
                                autocomplete="new-password"
                                :class="{ 'border-destructive': form.errors.password }"
                            />
                            <p v-if="form.errors.password" class="text-xs text-destructive">{{ form.errors.password }}</p>
                        </div>

                        <!-- Test result banner -->
                        <div
                            v-if="testResult"
                            class="flex items-start gap-2 rounded-md border p-3 text-sm"
                            :class="testResult.success ? 'border-green-500 bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-300' : 'border-destructive bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-300'"
                        >
                            <CheckCircle v-if="testResult.success" class="h-4 w-4 shrink-0 mt-0.5" />
                            <XCircle v-else class="h-4 w-4 shrink-0 mt-0.5" />
                            {{ testResult.message }}
                        </div>

                        <div class="flex items-center gap-3 pt-2">
                            <Button type="submit" :disabled="form.processing">
                                <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                                Save Connection
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="testing || !form.host || !form.database || !form.username"
                                @click="testConnection"
                            >
                                <Loader2 v-if="testing" class="mr-2 h-4 w-4 animate-spin" />
                                Test Connection
                            </Button>
                        </div>

                    </form>
                </CardContent>
            </Card>

            <p class="text-xs text-muted-foreground">
                Changes take effect immediately. The active POS connection is re-established on the
                next incoming request. The Krypton session cache is also invalidated automatically.
            </p>

        </div>
    </AppLayout>
</template>
