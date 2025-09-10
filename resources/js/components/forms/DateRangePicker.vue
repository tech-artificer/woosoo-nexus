<script setup lang="ts">
import { ref, watch } from "vue"
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem
} from "@/components/ui/select"

const props = defineProps<{
  modelValue: string | null
}>()

const emit = defineEmits<{
  (e: "update:modelValue", value: string | null): void
}>()

const dateRanges = [
  { label: "Today", value: "today" },
  { label: "Yesterday", value: "yesterday" },
  { label: "This Week", value: "this_week" },
  { label: "This Month", value: "this_month" },
  { label: "Custom Range", value: "custom" },
]

const selectedRange = ref<string | null>(props.modelValue ?? null)

watch(selectedRange, (val) => {
  emit("update:modelValue", val)
})
</script>

<template>
  <div>
    <Select v-model="selectedRange">
      <SelectTrigger class="w-[250px]">
        <SelectValue placeholder="Select a date range" />
      </SelectTrigger>
      <SelectContent>
        <SelectItem
          v-for="range in dateRanges"
          :key="range.value"
          :value="range.value"
        >
          {{ range.label }}
        </SelectItem>
      </SelectContent>
    </Select>
  </div>
</template>
