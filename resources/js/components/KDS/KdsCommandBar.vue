<script setup lang="ts">
import { Bell, BellOff, ChefHat, Clock3, Flame, Wifi, WifiOff } from 'lucide-vue-next'

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
  }>(),
  { online: true, chimeMuted: false },
)

defineEmits<{
  toggleChime: []
}>()
</script>

<template>
  <header class="kds-command">
    <div class="kds-brand" aria-label="Woosoo Kitchen Live">
      <div class="kds-brand-mark">
        <ChefHat aria-hidden="true" />
      </div>
      <div>
        <div class="kds-brand-name">WOOSOO</div>
        <div class="kds-brand-sub">Kitchen - Live</div>
      </div>
    </div>

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

    <div class="kds-status-clock">
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
      <span class="kds-online" :class="{ 'is-offline': !online }">
        <Wifi v-if="online" aria-hidden="true" />
        <WifiOff v-else aria-hidden="true" />
        {{ online ? 'Online' : 'Offline' }}
      </span>
      <span v-if="overdue > 0" class="kds-rush">
        <Flame aria-hidden="true" />
        Rush
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
