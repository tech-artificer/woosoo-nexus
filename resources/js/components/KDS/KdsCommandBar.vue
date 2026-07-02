<script setup lang="ts">
import { Bell, BellOff, ChefHat, Clock3, Maximize2, Minimize2, Wifi, WifiOff } from 'lucide-vue-next'

withDefaults(
  defineProps<{
    active: number
    newCount: number
    preparing: number
    overdue: number
    clock: string
    dateLabel: string
    online?: boolean
    chimeMuted?: boolean
    isFullscreen?: boolean
  }>(),
  { online: true, chimeMuted: false, isFullscreen: false },
)

defineEmits<{
  toggleChime: []
  toggleFullscreen: []
}>()
</script>

<template>
  <header class="kds-header">
    <div class="kds-header__left">
      <div class="kds-brand" aria-label="Woosoo Kitchen Live">
        <div class="kds-brand-mark">
          <ChefHat aria-hidden="true" />
        </div>
        <div>
          <div class="kds-brand-name">WOOSOO</div>
          <div class="kds-brand-sub">Kitchen - Live</div>
        </div>
      </div>
    </div>

    <nav class="kds-header__center">
      <div class="kds-metrics" aria-label="Kitchen queue counts">
        <div class="kds-metric">
          <strong>{{ active }}</strong>
          <span>Active</span>
        </div>
        <div class="kds-metric">
          <strong class="is-new">{{ newCount }}</strong>
          <span>New</span>
        </div>
        <div class="kds-metric">
          <strong class="is-preparing">{{ preparing }}</strong>
          <span>Preparing</span>
        </div>
        <div class="kds-metric">
          <strong class="is-overdue">{{ overdue }}</strong>
          <span>Overdue</span>
        </div>
      </div>
    </nav>

    <div class="kds-header__right">
      <button
        type="button"
        class="kds-chime-toggle"
        :aria-pressed="chimeMuted"
        :aria-label="chimeMuted ? 'Unmute new-order chime' : 'Mute new-order chime'"
        @click="$emit('toggleChime')"
      >
        <BellOff v-if="chimeMuted" aria-hidden="true" />
        <Bell v-else aria-hidden="true" />
      </button>
      <button
        type="button"
        class="kds-chime-toggle"
        :aria-pressed="isFullscreen"
        :aria-label="isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen'"
        @click="$emit('toggleFullscreen')"
      >
        <Minimize2 v-if="isFullscreen" aria-hidden="true" />
        <Maximize2 v-else aria-hidden="true" />
      </button>
      <span class="kds-conn" :class="online ? 'is-online' : 'is-offline'"
            role="status"
            :aria-label="online ? 'Connected' : 'Offline — updates paused'"
            :title="online ? 'Connected' : 'Offline — updates paused'">
        <Wifi v-if="online" aria-hidden="true" />
        <WifiOff v-else aria-hidden="true" />
      </span>
      <div class="kds-clock">
        <Clock3 aria-hidden="true" />
        <div>
          <strong>{{ clock }}</strong>
          <span>{{ dateLabel }}</span>
        </div>
      </div>
    </div>
  </header>
</template>
