<script setup lang="ts">
import { onMounted } from 'vue'
import { Button } from '@/components/ui/button'
import { Pencil } from 'lucide-vue-next';
// import { toast } from 'vue-sonner'

import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'

import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'

import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'

defineProps({
    device: {
        type: Object,
        required: true,
    },
})

const form = reactive({
  first_name: null,
  last_name: null,
  email: null,
})

function submit() {
//   router.post('/users', form)
}

onMounted(() => {
   
})

</script>

<template>
    <Dialog>
    <DialogTrigger as-child>
    <Button variant="ghost" class="cursor-pointer">
        <TooltipProvider>
          <Tooltip>
            <TooltipTrigger as-child>
            
                <Pencil />
              
            </TooltipTrigger>
            <TooltipContent>
              <p>Edit</p>
            </TooltipContent>
          </Tooltip>
        </TooltipProvider>
      </Button>
    </DialogTrigger>
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>Edit: {{ device.name }} </DialogTitle>
        <DialogDescription>
        <p>Edit the details of the selected device using the form below and click 'Submit' to save your changes.</p>
        </DialogDescription>
      </DialogHeader>
      <div class="grid gap-4 py-4">
        <!-- Update the form to reflect the device details -->
        <form @submit.prevent="submit" class="space-y-4">
          <div class="flex flex-col">
            <label for="first_name" class="mb-1 text-sm font-medium">First name:</label>
            <input id="first_name" v-model="form.first_name" class="border border-gray-300 p-2 rounded" />
          </div>
          <div class="flex flex-col">
            <label for="last_name" class="mb-1 text-sm font-medium">Last name:</label>
            <input id="last_name" v-model="form.last_name" class="border border-gray-300 p-2 rounded" />
          </div>
          <div class="flex flex-col">
            <label for="email" class="mb-1 text-sm font-medium">Email:</label>
            <input id="email" autocomplete="off" v-model="form.email" class="border border-gray-300 p-2 rounded" />
          </div>
          <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Submit</button>
        </form>
      </div>
      <DialogFooter class="sm:justify-start">
        <DialogClose as-child>
          <Button type="button" variant="secondary">
            Close
          </Button>
        </DialogClose>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>