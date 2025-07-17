<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Pencil } from 'lucide-vue-next'
import { Input } from '@/components/ui/input'
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label'
// import { useToast } from '@/composables/useToast'
import { Menu } from '@/types/models'

// const toast = useToast();


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

const props = defineProps<{
  menu: Menu
}>()

const showDialog = ref(false)
const open = ref(false)
const previewImage = ref<string | null>(props.menu.img_url)

const form = useForm({
  id: props.menu.id,
  name: props.menu.name,
  price: props.menu.price,
  image: null,
});

const openDialog = () => {
  showDialog.value = true
  form.reset()
  form.id = props.menu.id // this updates the value shown in Select
}

// const submit = () => {
//   form.put(`/menu/${props.menu.id}/image`, {
//     preserveScroll: true,
//     preserveState: true,

//     onSuccess: () => {
//       toast.success('✅ Menu updated!')
//       showDialog.value = false
//     },

//     onError: (errors) => {
//       console.error('❌ Validation error', errors)
//     },
//   })
// }

function submit() {

  form.post(route('menus.assign-image', props.menu), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {

      open.value = false;
      // router.reload({ only: ['menus'] })
      // toast('Menu updated', {
      //   description: 'Menu updated successfully',
      //   position: 'top-right',
      //   action: {
      //     label: 'Undo',
      //     onClick: () => console.log('Undo'),
      //   },
      // });
    },
    onError: () => {
      console.log('Error updating menu');
    }
  })
}


function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files?.length) {
    const file = input.files[0]
    // imageFile.value = file
    previewImage.value = URL.createObjectURL(file)
  }
}

onMounted(() => {
  //  toast.success('✅ Menu updated!')
})

</script>

<template>
  <Dialog>
    <TooltipProvider :delay-duration="100">
      <Tooltip>
        <TooltipTrigger as-child>
          <DialogTrigger as-child>
            <Button variant="ghost" class="cursor-pointer" @click="openDialog">
              <Pencil />
            </Button>
          </DialogTrigger>
        </TooltipTrigger>
        <TooltipContent>
          <p>Click to View/Modify</p>
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>

    <DialogContent class="sm:max-w-[600px]">
      <DialogHeader class="flex flex-col gap-2 justify-start">
        <div class="flex justify-start  mb-5">
          <!-- Image Preview -->
          <div v-if="previewImage" class="flex justify-center">
            <img :src="previewImage" class="w-32 h-32 object-cover border rounded-lg" />
          </div>
        </div>

        <DialogTitle>{{ form.name }}</DialogTitle>
        <DialogDescription>
          <!-- <div class="block">Upload a new image.</div> -->
        </DialogDescription>
      </DialogHeader>
      <form @submit.prevent="submit" class="space-y-4">
        <!-- File Input -->
        <div class="grid gap-2">
          <Label for="name" class="text-woosoo-dark-gray">Featured Image</Label>
          <Input id="image" type="file" accept="image/*" @input="form.image = $event.target.files[0]"
            @change="onFileChange" />
          <InputError :message="form.errors.image" />
        </div>

        <!-- <div class="flex gap-4">

          <div class="flex flex-col gap-2">
            <Label for="name" class="text-woosoo-dark-gray">Name</Label>
            <Input class="text-woosoo-dark-gray" id="name" type="text" :tabindex="1" :disabled="true"
              :value="form.name" />
            <InputError :message="form.errors.name" />
          </div>


          <div class="flex flex-col gap-2">
            <Label for="price" class="text-woosoo-dark-gray">Price</Label>
            <Input class="text-woosoo-dark-gray" id="price" :tabindex="2" type="number" :disabled="true"
              :value="form.price" />
            <InputError :message="form.errors.price" />
          </div>
        </div> -->

        <div class="flex items-center justify-between gap-2 flex-row-reverse mt-5">
          <Button type="submit"
            class="hover:bg-woosoo-primary-light hover:text-woosoo-primary-dark bg-woosoo-accent cursor-pointer text-gray-100 w-50"
            :disabled="form.processing">
            Save Changes
          </Button>

          <DialogClose as-child>
            <Button type="button" variant="secondary" class="cursor-pointer w-50">Close</Button>
          </DialogClose>
        </div>
      </form>
    </DialogContent>
  </Dialog>
</template>
