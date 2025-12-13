<script setup lang="ts">
import type { Table } from '@tanstack/vue-table'
import type { User } from '@/types/models';
import { computed } from 'vue'

import { Plus } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

import { roles, statuses } from '../data/data'
import DataTableFacetedFilter from '@/components/reports/DataTableFacetedFilter.vue'
// DataTable view options/add-user components not present in reports folder; use Register when needed
import AddUser from '@/components/Users/Register.vue'

interface DataTableToolbarProps {
  <script setup lang="ts">
  import DataTableToolbar from '@/components/ui/DataTableToolbar.vue'
  defineProps()
  </script>

  <template>
    <DataTableToolbar v-bind="$attrs" v-on="$listeners" />
  </template>
<template>
