<!-- resources/js/Pages/Menus/EditMenu.vue -->
<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Pencil } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { useToast } from '@/composables/useToast';
import { useUpdateModel } from '@/composables/useUpdateModel';
import { Menu } from '@/types/models';
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

// Toast for notifications
const toast = useToast();

// Dialog state
const showDialog = ref(false);

// Image preview
const previewImage = ref<string | null>(props.menu.img_url);

// Reactive menu data (single item for this component)
const localMenu = ref([props.menu]);

// Initialize the composable for the 'menu' model
const { form, startEditing, cancelEditing, updateItem } = useUpdateModel(
  'menu',
  'menu',
  localMenu
);

// Open dialog and start editingS
const openDialog = () => {
  showDialog.value = true;
  startEditing(props.menu, { image: null }); // Initialize form with image field
};

// Handle file selection and preview
function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement;
  if (input.files?.length) {
    const file = input.files[0];
    form.image = file; // Set file in the form
    previewImage.value = URL.createObjectURL(file); // Update preview
    // Optimistic update for img_url
    localMenu.value[0].img_url = previewImage.value;
  }
}

// Submit the image upload
function submit() {
  console.log(form);
  router.post(route('menu.upload.image', props.menu.id), {
    _method: 'put',
    forceFormData: true, // <-- THIS is the secret ingredient
    image: form.image,
    // onSuccess: () => { 
    //   toast.success('✅ Menu image updated!');
    //   showDialog.value = false;
    // },
    // onError: (errors) => { 
    //   console.error('❌ Validation error', errors);
    //   toast.error('❌ Failed to update image');
    //   // Revert optimistic update
    //   localMenu.value[0].img_url = props.menu.img_url;
    // },
  });
  // console.log(form.image);
  // updateItem(props.menu.id, {
  //   method: 'post', // Use POST for image upload
  //   onSuccessCallback: () => {
  //     toast.success('✅ Menu image updated!');
  //     showDialog.value = false;
  //   },
  //   onErrorCallback: (errors: any) => {
  //     console.error('❌ Validation error', errors);
  //     toast.error('❌ Failed to update image');
  //     // Revert optimistic update
  //     localMenu.value[0].img_url = props.menu.img_url;
  //   },
  // });
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
      <form @submit.prevent="submit" class="space-y-4">
        <!-- File Input -->
        <div class="grid gap-2">
          <Label for="image" class="text-woosoo-dark-gray">Featured Image</Label>
          <Input
            id="image"
            type="file"
            accept="image/*"
            @change="onFileChange"
   
          />
          <InputError :message="form.errors.image" />
        </div>

        <div class="flex items-center justify-between gap-2 flex-row-reverse mt-5">
          <Button
            type="submit"
            class="hover:bg-woosoo-primary-light hover:text-woosoo-primary-dark bg-woosoo-accent cursor-pointer text-gray-100 w-50"
            :disabled="form.processing"
          >
            Save Changes
          </Button>

          <DialogClose as-child>
            <Button type="button" variant="secondary" class="cursor-pointer w-50" @click="cancelEditing">
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
