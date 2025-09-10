<!-- resources/js/Pages/Menus/EditMenu.vue -->
<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Image, Pencil } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Menu } from '@/types/models';
import { toast } from 'vue-sonner'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';

// Props
const props = defineProps<{
  menu: Menu;
}>();

const showDialog = ref(false); // Dialog state
const previewImage = ref<string | null>(props.menu.img_url); // Image preview
const localMenu = ref([props.menu]); // Reactive menu data (single item for this component)
const form = useForm({
  image: null,
});
// Open dialog and start editingS
const openDialog = () => {
  showDialog.value = true;
};
// Handle file selection and preview
function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement | null;
  if (!input || !input.files?.length) {
    return;
  }
  const file = input.files[0];
  try {
    // form.image = file; // Set file in the form
    previewImage.value = URL.createObjectURL(file); // Update preview
    // Optimistic update for img_url
    localMenu.value[0].img_url = previewImage.value;
  } catch (error: any) {
    console.error('Error while handling file change', error);
  }
}
// Submit the image upload
function submit() {
  form.post(route('menu.upload.image', { id: props.menu.id }), {
    forceFormData: true, // <-- THIS is the secret ingredient
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      // console.log('✅ Image uploaded successfully');
      toast('Image Uploaded:', {
        description: 'The previous image has been replaced with your new upload.',
        action: {
            label: 'Ok',
            variant: 'success',
            onClick: () => console.log('Ok'),
        },
        duration: 5000,
        position: 'top-right',
      });
      showDialog.value = false;
    },
    onError: () => {
      toast('An Error Occured', {
        description: 'Something Went Wrong!',
        // action: {
        //   label: 'Undo',
        //   onClick: () => console.log('Undo'),
        // },
        duration: 5000,
        position: 'top-right',
      });
      localMenu.value[0].img_url = props.menu.img_url;
    },
  });

}
// Clean up preview URL on unmount
onMounted(() => {
  return () => {
    if (previewImage.value && previewImage.value.startsWith('blob:')) {
      URL.revokeObjectURL(previewImage.value);
    }
  };
});

</script>
<template>
  <Dialog v-model:open="showDialog">
    <TooltipProvider :delay-duration="100">
      <Tooltip class="">
        <TooltipTrigger as-child>
          <DialogTrigger as-child>
            <Button variant="ghost" class="cursor-pointer" @click="openDialog">
              <Image  />
            </Button>
          </DialogTrigger>
        </TooltipTrigger>
        <TooltipContent>
          <p>Upload/Replace Image</p>
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
    <TooltipProvider :delay-duration="100">
      <Tooltip>
        <TooltipTrigger as-child>
          <DialogTrigger as-child>
             <Button variant="ghost" class="cursor-pointer">
              <Pencil />
            </Button>
          </DialogTrigger>
        </TooltipTrigger>
        <TooltipContent>
          <p>Edit Menu</p>
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>

    <DialogContent class="sm:max-w-[600px]">
      <DialogHeader class="flex flex-col gap-2 justify-start">
        <div class="flex justify-start mb-5">
          <!-- Image Preview -->
          <div v-if="previewImage" class="flex justify-center">
            <img :src="previewImage" class="w-32 h-32 object-cover border rounded-lg" alt="Menu Image" />
          </div>
        </div>

        <DialogTitle>{{ props.menu.name }}</DialogTitle>
        <DialogDescription>
          Upload or replace the featured image for this menu item.
        </DialogDescription>
      </DialogHeader>
      <form @submit.prevent="submit" method="put" class="space-y-4">
        <!-- File Input -->
        <div class="grid gap-2">
          <Label for="image" class="text-woosoo-dark-gray">Featured Image</Label>
          <Input id="image" type="file" :v-model="form.image" accept="image/*" @change="onFileChange"
            @input="form.image = $event.target.files[0]" />
          <progress v-if="form.progress" :value="form.progress.percentage" max="100">
            {{ form.progress.percentage }}%
          </progress>
          <InputError :message="form.errors.image" />
        </div>

        <div class="flex items-center justify-between gap-2 flex-row-reverse mt-5">
          <Button type="submit"
            class="hover:bg-woosoo-primary-light hover:text-woosoo-primary-dark bg-woosoo-accent cursor-pointer text-gray-100 w-50"
            :disabled="form.processing">
            Save Changes
          </Button>

          <DialogClose as-child>
            <Button type="button" variant="secondary" class="cursor-pointer w-50">
              Close
            </Button>
          </DialogClose>
        </div>
      </form>
    </DialogContent>
  </Dialog>
</template>

<style scoped>
.error {
  color: red;
  font-size: 0.8em;
}
</style>
