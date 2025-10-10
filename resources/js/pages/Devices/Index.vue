<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from "@/components/ui/button"
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from "@/components/ui/card"
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from "@/components/ui/tabs"
import { type BreadcrumbItem } from '@/types';

import { columns } from '@/components/Devices/columns';
import DataTable from '@/components/Devices/DataTable.vue'
import type { Device } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Devices',
        href: route('devices.index'),
    },
];

defineProps<{
    title: string;
    description: string;
    devices: Device[];
    registrationCodes: any[];
}>()

</script>

<template>


    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col bg-white gap-4 rounded p-6">
            <Tabs default-value="devices" class="">
                <TabsList class="grid w-full grid-cols-2">
                    <TabsTrigger value="devices">
                        Devices
                    </TabsTrigger>
                    <TabsTrigger value="codes">
                        Codes
                    </TabsTrigger>
                </TabsList>
                <TabsContent value="devices" class="p-2">
                    <DataTable :data="devices" :columns="columns" />
                </TabsContent>
                <TabsContent value="codes">
                    <Card>
                        <CardHeader>
                            <CardTitle>Codes</CardTitle>
                            <CardDescription>
                                Generate device codes for device activation.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                           
                               <!-- {{ registrationCodes }} -->
                               <Table>
                                    <TableCaption>A list of your recent invoices.</TableCaption>
                                    <TableHeader>
                                    <TableRow>
                                        <TableHead class="">
                                        Code
                                        </TableHead>
                                       <TableHead class="">
                                        Device ID
                                        </TableHead> 
                                        <TableHead class="">
                                            Registered At
                                        </TableHead> 
                                    </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                    <TableRow v-for="code in registrationCodes" :key="code.id">
                                        <TableCell>
                                        {{ code.code }}
                                        </TableCell>
                                        <TableCell>{{ code.used_by_device_id }}</TableCell>
                                        <TableCell>{{ code.used_at }}</TableCell>
                                    </TableRow>
                                    </TableBody>
                                </Table>
                          
                        </CardContent>
                        <CardFooter>
                            <Button>Generate</Button>
                        </CardFooter>
                    </Card>
                </TabsContent>
            </Tabs>
           
        </div>
    </AppLayout>
</template>