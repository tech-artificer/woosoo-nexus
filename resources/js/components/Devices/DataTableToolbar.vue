<script setup lang="ts">
/* eslint-disable vue/no-deprecated-dollar-listeners-api, vue/valid-define-props */
import DataTableToolbar from '@/components/ui/DataTableToolbar.vue'
defineProps()
</script>

<template>
  <DataTableToolbar v-bind="$attrs" v-on="$listeners" />
</template>
