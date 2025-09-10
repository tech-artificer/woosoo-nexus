<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from "vue"
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import {
    Card, CardContent, CardHeader, CardTitle, CardDescription
} from '@/components/ui/card';

import DailySales from '@/pages/reports/sales/DailySales.vue'
import MonthlySales from '@/pages/reports/sales//MonthlySales.vue'
// import { rows } from '@unovis/ts/components/timeline/style';
// import { columns } from '@/pages/reports/sales/columns'

// interface ReportType { 
//   label: string
//   value: string
//   description?: string,
//   component: any | null
// }

const reportTypes = [
    {
    label: 'Daily Sales',
    value: 'daily',
    description: 'View sales data for a single day',
    component: DailySales,
    },
    {
        label: 'Monthly Sales',
        value: 'monthly',
        description: 'Summarized sales across a month',
        component: MonthlySales,
    },
]

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: '/users',
    },
];

// interface Filter {
//     type: string
//     start_date: string
//     end_date: string
// }

const props = defineProps<{
    title: string,
    description: string,
    data: any[],
    // filters: Filter[],
    isLoading?: boolean,
}>()

const selectedReport = ref(reportTypes[0].value)

const activeReportComponent = computed(() => {
    const found = reportTypes.find((r: any) => r.value === selectedReport.value)
    return found ? found.component : null
})



</script>

<template>

    <Head :title="title" :description="description" />

    <AppLayout :breadcrumbs="breadcrumbs">


        <div class="flex flex-col md:flex-row items-start md:items-center gap-4 p-4 
           bg-white dark:bg-gray-800 rounded-lg shadow">
            <!-- Report Type Selector -->
            <div>
                <label class="block text-sm font-medium mb-1">Report Type</label>
                <Select v-model="selectedReport">
                    <SelectTrigger class="w-[200px]">
                        <SelectValue placeholder="Select a report type" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="reportType in reportTypes" :key="reportType.value" :value="reportType.value">
                            {{ reportType.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <Separator orientation="vertical" class="hidden md:block h-8" />

            <!-- Date Range Picker -->


            <Separator orientation="vertical" class="hidden md:block h-8" />

            <!-- Action Buttons -->
            <div class="flex gap-2 w-full md:w-auto">
                <Button class="flex-1 md:flex-none" :disabled="props.isLoading">
                    <span v-if="!props.isLoading">Generate Report</span>
                    <span v-else class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                        </svg>
                        Loading...
                    </span>
                </Button>

                <Button variant="secondary" class="flex-1 md:flex-none" :disabled="props.isLoading">
                    Export
                </Button>
            </div>
        </div>


        <!-- Report Type Selector -->
        <div class="bg-gray-50 dark:bg-gray-900 min-h-screen font-sans antialiased p-4 md:p-8">
            <div class="max-w-7xl mx-auto">
                <Card
                    class="bg-white dark:bg-gray-800 shadow-2xl rounded-2xl border border-gray-200 dark:border-gray-700">
                    <CardHeader class="text-center pb-6 border-b border-gray-200 dark:border-gray-700">
                        <CardTitle class="text-3xl font-body font-semibold text-gray-900 dark:text-gray-100">
                           {{ selectedReport.toUpperCase() }}
                        </CardTitle>
                        <CardDescription class="text-gray-500 dark:text-gray-400 mt-2 font-body">
                            description
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-8 pt-6">
                        <component :is="activeReportComponent" :rows="props.data" />
                    </CardContent>
                </Card>

            </div>
        </div>
    </AppLayout>

</template>
