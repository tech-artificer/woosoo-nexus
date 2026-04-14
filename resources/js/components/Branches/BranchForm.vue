<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import InputError from '@/components/InputError.vue'
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'

const props = defineProps<{
  open: boolean
  branch?: {
    id: number
    name: string
    location: string | null
  } | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const form = useForm({
  name: props.branch?.name || '',
  location: props.branch?.location || '',
})

const submit = () => {
  if (props.branch) {
    // Update existing branch
    form.put(route('branches.update', props.branch.id), {
      preserveScroll: true,
      onSuccess: () => {
        emit('update:open', false)
        form.reset()
      },
    })
  } else {
    // Create new branch
    form.post(route('branches.store'), {
      preserveScroll: true,
      onSuccess: () => {
        emit('update:open', false)
        form.reset()
      },
    })
  }
}

// Watch for prop changes to update form
const updateForm = () => {
  form.name = props.branch?.name || ''
  form.location = props.branch?.location || ''
  form.clearErrors()
}

// Call updateForm when branch prop changes
import { watch } from 'vue'
watch(() => props.branch, updateForm)
watch(() => props.open, (newValue) => {
  if (newValue) {
    updateForm()
  }
})
</script>

<template>
  <Sheet :open="open" @update:open="emit('update:open', $event)">
    <SheetContent class="sm:max-w-135">
      <SheetHeader>
        <SheetTitle>{{ branch ? 'Edit Branch' : 'Add Branch' }}</SheetTitle>
        <SheetDescription>
          {{ branch ? 'Update branch details' : 'Create a new branch location' }}
        </SheetDescription>
      </SheetHeader>

      <form @submit.prevent="submit" class="flex flex-col gap-6 py-6">
        <div class="space-y-2">
          <Label for="name">
            Branch Name <span class="text-destructive">*</span>
          </Label>
          <Input
            id="name"
            v-model="form.name"
            placeholder="Enter branch name"
            required
          />
          <InputError :message="form.errors.name" />
        </div>

        <div class="space-y-2">
          <Label for="location">Location</Label>
          <Textarea
            id="location"
            v-model="form.location"
            placeholder="Enter branch location/address"
            rows="3"
          />
          <InputError :message="form.errors.location" />
        </div>

        <SheetFooter>
          <SheetClose as-child>
            <Button type="button" variant="outline" :disabled="form.processing">
              Cancel
            </Button>
          </SheetClose>
          <Button type="submit" :disabled="form.processing">
            {{ form.processing ? 'Saving…' : (branch ? 'Update Branch' : 'Create Branch') }}
          </Button>
        </SheetFooter>
      </form>
    </SheetContent>
  </Sheet>
</template>
