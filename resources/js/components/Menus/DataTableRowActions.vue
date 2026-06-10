<script setup lang="ts">
import { computed } from 'vue'
import type { Row } from '@tanstack/vue-table'
import type { Menu } from '@/types/models'
import MenuForm from '@/components/Menus/MenuForm.vue'

interface DataTableRowActionsProps {
    row: Row<Menu>
}
const props = defineProps<DataTableRowActionsProps>()
const computedMenu = computed(() => props.row.original as Menu)
</script>

<template>
    <MenuForm :menu="computedMenu" />
</template>
