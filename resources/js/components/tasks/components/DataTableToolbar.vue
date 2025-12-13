<script setup lang="ts">
import type { Table } from '@tanstack/vue-table'
import type { Task } from '../data/schema'
import { computed } from 'vue'

import { Plus } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

import { priorities, statuses } from '../data/data'
import DataTableFacetedFilter from './DataTableFacetedFilter.vue'
import DataTableViewOptions from './DataTableViewOptions.vue'

interface DataTableToolbarProps {
  <script setup lang="ts">
  import DataTableToolbar from '@/components/ui/DataTableToolbar.vue'
  defineProps()
  </script>

  <template>
    <DataTableToolbar v-bind="$attrs" v-on="$listeners" />
  </template>
<template>
