<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import DailySales from '@/pages/reports/sales/DailySales.vue';
import MonthlySales from '@/pages/reports/sales/MonthlySales.vue';
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
];

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
    title: string;
    description: string;
    data: any[];
    // filters: Filter[],
    isLoading?: boolean;
}>();

const selectedReport = ref(reportTypes[0].value);

const activeReportComponent = computed(() => {
    const found = reportTypes.find((r: any) => r.value === selectedReport.value);
    return found ? found.component : null;
});
</script>

<template>
    <Head :title="title" :description="description" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex flex-col items-start gap-4 rounded-[24px] border border-border/60 bg-card/95 p-4 shadow-sm shadow-black/5 md:flex-row md:items-center"
        >
            <!-- Report Type Selector -->
            <div>
                <label class="mb-1 block text-sm font-medium">Report Type</label>
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

            <Separator orientation="vertical" class="hidden h-8 md:block" />

            <!-- Date Range Picker -->

            <Separator orientation="vertical" class="hidden h-8 md:block" />

            <!-- Action Buttons -->
            <div class="flex w-full gap-2 md:w-auto">
                <Button class="flex-1 md:flex-none" :disabled="props.isLoading">
                    <span v-if="!props.isLoading">Generate Report</span>
                    <span v-else class="flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                        </svg>
                        Loading...
                    </span>
                </Button>

                <Button variant="secondary" class="flex-1 md:flex-none" :disabled="props.isLoading"> Export </Button>
            </div>
        </div>

        <!-- Report Type Selector -->
        <div class="min-h-screen bg-transparent p-4 antialiased md:p-8">
            <div class="mx-auto max-w-7xl">
                <Card class="rounded-[26px] border border-border/70 bg-card/95 shadow-sm shadow-black/5">
                    <CardHeader class="border-b border-border pb-6 text-center">
                        <CardTitle class="font-header text-3xl font-semibold text-foreground">
                            {{ selectedReport.toUpperCase() }}
                        </CardTitle>
                        <CardDescription class="mt-2 text-muted-foreground"> description </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-8 pt-6">
                        <component :is="activeReportComponent" :rows="props.data" />
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
