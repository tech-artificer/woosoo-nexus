<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button';
import { Upload, Trash2 } from 'lucide-vue-next';
import { Menu } from '@/types/models'
// import { Input } from '@/components/ui/input'
// import InputError from '@/components/InputError.vue';
// import { Label } from '@/components/ui/label'


const props = defineProps<{
  menu: Menu
}>()

const previewImage = ref<string | null>(props.menu.img_url)

const form = useForm({
  id: props.menu.id,
  name: props.menu.name,
  price: props.menu.price,
  image: null,
});

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files?.length) {
    const file = input.files[0]
    // imageFile.value = file
    previewImage.value = URL.createObjectURL(file)
  }
}

function removeImage() {
    previewImage.value = null
    form.image = null
        
}


</script>

<template>
    <div class="flex flex-col gap-2">
          <div v-if="previewImage" class="flex justify-center">
            <img :src="previewImage" class="w-32 h-32 object-cover border rounded-lg" />
          </div>
    <form  class="space-y-4">
    <!-- File Input -->
        <!-- <div class="grid gap-2">
            <Label for="name" class="text-woosoo-dark-gray">Featured Image</Label>
            <Input id="image" type="file" accept="image/*" @input="form.image = $event.target.files[0]"
            @change="onFileChange" />
            <InputError :message="form.errors.image" />
        </div> -->
    </form>
      <!-- <label for="image" class="text-woosoo-dark-gray">Image</label> -->
      <div class="flex gap-2">
        <input type="file" id="image" accept="image/*" class="hidden" @change="onFileChange" ref="fileInput" />
        <Button class="hover:bg-woosoo-primary-light hover:text-woosoo-primary-dark bg-woosoo-accent cursor-pointer text-gray-100 w-50" @click="onFileChange">
          <Upload class="w-4 h-4 mr-2" />
          <span>Upload</span>
        </Button>
        <Button class="hover:bg-woosoo-primary-light hover:text-woosoo-primary-dark bg-woosoo-accent cursor-pointer text-gray-100 w-50" @click="removeImage" v-if="previewImage">
          <Trash2 class="w-4 h-4 mr-2" />
          <span>Remove</span>
        </Button>
      </div>
    
    </div>
</template>