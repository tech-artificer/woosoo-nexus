<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'
import { toast } from 'vue-sonner'
import { type BreadcrumbItem } from '@/types'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Switch } from '@/components/ui/switch'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Settings', href: '#' },
]

const loading = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)

const settings = ref({
  theme: 'light' as 'light' | 'dark' | 'system',
  itemsPerPage: 25,
  emailNotifications: true,
  orderAlerts: true,
  soundAlerts: false,
  posSystem: '',
  apiBaseUrl: '',
  websocketUrl: '',
})

async function fetchSettings() {
  loading.value = true
  error.value = null
  try {
    const res = await fetch(route('admin.settings.get'))
    if (!res.ok) throw new Error(`Server responded ${res.status}`)
    const data = await res.json()
    settings.value = {
      theme: data.theme ?? 'light',
      itemsPerPage: data.itemsPerPage ?? 25,
      emailNotifications: data.emailNotifications ?? true,
      orderAlerts: data.orderAlerts ?? true,
      soundAlerts: data.soundAlerts ?? false,
      posSystem: data.posSystem ?? '',
      apiBaseUrl: data.apiBaseUrl ?? '',
      websocketUrl: data.websocketUrl ?? '',
    }
  } catch (e: any) {
    error.value = e?.message ?? 'Failed to load settings'
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  saving.value = true
  try {
    const res = await fetch(route('admin.settings.put'), {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': decodeURIComponent(
          document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
        ),
      },
      body: JSON.stringify({
        ...settings.value,
        posSystem: settings.value.posSystem || null,
        apiBaseUrl: settings.value.apiBaseUrl || null,
        websocketUrl: settings.value.websocketUrl || null,
      }),
    })
    if (!res.ok) {
      const body = await res.json().catch(() => ({}))
      throw new Error(body?.message ?? `Server responded ${res.status}`)
    }
    toast.success('Settings saved')
  } catch (e: any) {
    toast.error(e?.message ?? 'Failed to save settings')
  } finally {
    saving.value = false
  }
}

onMounted(fetchSettings)
</script>

<template>
  <Head title="Settings" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="max-w-2xl mx-auto p-6 space-y-6">
      <div>
        <h1 class="text-2xl font-bold tracking-tight">Branch Settings</h1>
        <p class="text-muted-foreground">Configure per-branch preferences and integrations.</p>
      </div>

      <div v-if="loading" class="text-sm text-muted-foreground">Loading settings…</div>

      <div v-else-if="error" class="text-sm text-destructive">{{ error }}</div>

      <template v-else>
        <!-- Appearance -->
        <Card>
          <CardHeader>
            <CardTitle>Appearance</CardTitle>
            <CardDescription>UI theme and display preferences.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-2">
              <Label for="theme">Theme</Label>
              <Select v-model="settings.theme">
                <SelectTrigger id="theme" class="w-48">
                  <SelectValue placeholder="Select theme" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="light">Light</SelectItem>
                  <SelectItem value="dark">Dark</SelectItem>
                  <SelectItem value="system">System</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="itemsPerPage">Items per page</Label>
              <Input
                id="itemsPerPage"
                type="number"
                min="1"
                max="200"
                class="w-32"
                :value="settings.itemsPerPage"
                @input="settings.itemsPerPage = Number(($event.target as HTMLInputElement).value)"
              />
            </div>
          </CardContent>
        </Card>

        <!-- Notifications -->
        <Card>
          <CardHeader>
            <CardTitle>Notifications</CardTitle>
            <CardDescription>Control alert and notification behaviour.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="flex items-center justify-between">
              <Label for="emailNotifications">Email notifications</Label>
              <Switch id="emailNotifications" v-model:checked="settings.emailNotifications" />
            </div>
            <div class="flex items-center justify-between">
              <Label for="orderAlerts">Order alerts</Label>
              <Switch id="orderAlerts" v-model:checked="settings.orderAlerts" />
            </div>
            <div class="flex items-center justify-between">
              <Label for="soundAlerts">Sound alerts</Label>
              <Switch id="soundAlerts" v-model:checked="settings.soundAlerts" />
            </div>
          </CardContent>
        </Card>

        <!-- Integrations -->
        <Card>
          <CardHeader>
            <CardTitle>Integrations</CardTitle>
            <CardDescription>POS system and connectivity settings.</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-2">
              <Label for="posSystem">POS system</Label>
              <Input id="posSystem" v-model="settings.posSystem" placeholder="e.g. krypton" class="max-w-xs" />
            </div>
            <div class="space-y-2">
              <Label for="apiBaseUrl">API base URL</Label>
              <Input id="apiBaseUrl" v-model="settings.apiBaseUrl" placeholder="https://…" class="max-w-sm" />
            </div>
            <div class="space-y-2">
              <Label for="websocketUrl">WebSocket URL</Label>
              <Input id="websocketUrl" v-model="settings.websocketUrl" placeholder="wss://…" class="max-w-sm" />
            </div>
          </CardContent>
        </Card>

        <div class="flex justify-end">
          <Button @click="saveSettings" :disabled="saving">
            {{ saving ? 'Saving…' : 'Save settings' }}
          </Button>
        </div>
      </template>
    </div>
  </AppLayout>
</template>
