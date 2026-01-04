<script setup lang="ts">
import { computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Image from '@tiptap/extension-image'
import Link from '@tiptap/extension-link'
import DOMPurify from 'dompurify'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import { useToast } from '@/composables/useToast'

type Guide = {
  id: string
  section: string
  title: string
  summary: string
  content: string
}

type Section = {
  title: string
  description: string
}

const props = defineProps<{
  guide: Guide
  sections: Record<string, Section>
}>()

const { toast } = useToast()

const form = useForm({
  content: props.guide.content,
})

// Configure TipTap editor
const editor = useEditor({
  content: props.guide.content,
  extensions: [
    StarterKit.configure({
      heading: {
        levels: [1, 2, 3, 4],
      },
    }),
    Link.configure({
      openOnClick: false,
      HTMLAttributes: {
        class: 'text-primary underline',
      },
    }),
    Image.configure({
      HTMLAttributes: {
        class: 'max-w-full h-auto rounded-md',
      },
    }),
  ],
  editorProps: {
    attributes: {
      class: 'prose prose-sm sm:prose lg:prose-lg xl:prose-xl max-w-none focus:outline-none min-h-[500px] p-4',
    },
  },
  onUpdate: ({ editor }) => {
    form.content = editor.getHTML()
  },
})

// Image upload handler
const uploadImage = () => {
  const input = document.createElement('input')
  input.type = 'file'
  input.accept = 'image/png,image/jpeg,image/jpg'
  
  input.onchange = async (e) => {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return

    if (file.size > 2 * 1024 * 1024) {
      toast({
        title: 'Error',
        description: 'Image must be less than 2MB',
        variant: 'destructive',
      })
      return
    }

    const formData = new FormData()
    formData.append('image', file)

    try {
      const response = await fetch(route('manual.upload.image'), {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: formData,
      })

      if (!response.ok) throw new Error('Upload failed')

      const data = await response.json()
      
      // Insert image into editor
      if (editor.value && data.url) {
        editor.value.chain().focus().setImage({ src: data.url }).run()
      }

      toast({
        title: 'Success',
        description: 'Image uploaded successfully',
      })
    } catch {
      toast({
        title: 'Error',
        description: 'Failed to upload image',
        variant: 'destructive',
      })
    }
  }

  input.click()
}

// Toolbar actions
const toggleBold = () => editor.value?.chain().focus().toggleBold().run()
const toggleItalic = () => editor.value?.chain().focus().toggleItalic().run()
const toggleHeading = (level: 1 | 2 | 3 | 4) => {
  editor.value?.chain().focus().toggleHeading({ level }).run()
}
const toggleBulletList = () => editor.value?.chain().focus().toggleBulletList().run()
const toggleOrderedList = () => editor.value?.chain().focus().toggleOrderedList().run()
const toggleBlockquote = () => editor.value?.chain().focus().toggleBlockquote().run()
const toggleCodeBlock = () => editor.value?.chain().focus().toggleCodeBlock().run()
const setHorizontalRule = () => editor.value?.chain().focus().setHorizontalRule().run()

// Link handling
const setLink = () => {
  const url = window.prompt('Enter URL:')
  if (url) {
    editor.value?.chain().focus().setLink({ href: url }).run()
  }
}

// Live preview (convert HTML to markdown-style rendering)
const previewHtml = computed(() => {
  return DOMPurify.sanitize(form.content)
})

// Save handler
const save = () => {
  form.put(route('manual.update', props.guide.id), {
    preserveScroll: true,
    onSuccess: () => {
      toast({
        title: 'Success',
        description: 'Guide updated successfully',
      })
    },
    onError: () => {
      toast({
        title: 'Error',
        description: 'Failed to update guide',
        variant: 'destructive',
      })
    },
  })
}

// Cancel handler
const cancel = () => {
  router.visit(route('manual.index'))
}

const sectionInfo = computed(() => props.sections[props.guide.section])
</script>

<template>
  <AppLayout>
    <div class="container mx-auto py-6 space-y-6">
      <!-- Header -->
      <div>
        <div class="flex items-center gap-2 text-sm text-muted-foreground mb-2">
          <a href="/manual" class="hover:underline">Manual</a>
          <span>/</span>
          <span>{{ sectionInfo?.title }}</span>
          <span>/</span>
          <span>Edit</span>
        </div>
        <h1 class="text-3xl font-bold">{{ guide.title }}</h1>
        <p class="text-muted-foreground mt-1">{{ guide.summary }}</p>
      </div>

      <!-- Editor Toolbar -->
      <Card>
        <CardHeader>
          <CardTitle>Content Editor</CardTitle>
          <CardDescription>Use the toolbar below to format your guide content</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="flex flex-wrap gap-1 p-2 border rounded-md bg-muted/50 mb-4">
            <Button 
              type="button" 
              size="sm" 
              variant="ghost" 
              @click="toggleHeading(1)"
              :class="{ 'bg-accent': editor?.isActive('heading', { level: 1 }) }"
            >
              H1
            </Button>
            <Button 
              type="button" 
              size="sm" 
              variant="ghost" 
              @click="toggleHeading(2)"
              :class="{ 'bg-accent': editor?.isActive('heading', { level: 2 }) }"
            >
              H2
            </Button>
            <Button 
              type="button" 
              size="sm" 
              variant="ghost" 
              @click="toggleHeading(3)"
              :class="{ 'bg-accent': editor?.isActive('heading', { level: 3 }) }"
            >
              H3
            </Button>
            <Separator orientation="vertical" class="h-8" />
            <Button 
              type="button" 
              size="sm" 
              variant="ghost" 
              @click="toggleBold"
              :class="{ 'bg-accent': editor?.isActive('bold') }"
            >
              <strong>B</strong>
            </Button>
            <Button 
              type="button" 
              size="sm" 
              variant="ghost" 
              @click="toggleItalic"
              :class="{ 'bg-accent': editor?.isActive('italic') }"
            >
              <em>I</em>
            </Button>
            <Separator orientation="vertical" class="h-8" />
            <Button 
              type="button" 
              size="sm" 
              variant="ghost" 
              @click="toggleBulletList"
              :class="{ 'bg-accent': editor?.isActive('bulletList') }"
            >
              • List
            </Button>
            <Button 
              type="button" 
              size="sm" 
              variant="ghost" 
              @click="toggleOrderedList"
              :class="{ 'bg-accent': editor?.isActive('orderedList') }"
            >
              1. List
            </Button>
            <Separator orientation="vertical" class="h-8" />
            <Button 
              type="button" 
              size="sm" 
              variant="ghost" 
              @click="toggleBlockquote"
              :class="{ 'bg-accent': editor?.isActive('blockquote') }"
            >
              Quote
            </Button>
            <Button 
              type="button" 
              size="sm" 
              variant="ghost" 
              @click="toggleCodeBlock"
              :class="{ 'bg-accent': editor?.isActive('codeBlock') }"
            >
              Code
            </Button>
            <Separator orientation="vertical" class="h-8" />
            <Button 
              type="button" 
              size="sm" 
              variant="ghost" 
              @click="uploadImage"
            >
              Image
            </Button>
            <Button 
              type="button" 
              size="sm" 
              variant="ghost" 
              @click="setLink"
            >
              Link
            </Button>
            <Button 
              type="button" 
              size="sm" 
              variant="ghost" 
              @click="setHorizontalRule"
            >
              HR
            </Button>
          </div>

          <!-- Split View: Editor | Preview -->
          <div class="grid grid-cols-2 gap-4">
            <!-- Editor Pane -->
            <div class="border rounded-md">
              <div class="bg-muted px-4 py-2 font-medium text-sm">Editor</div>
              <EditorContent :editor="editor" class="min-h-[600px]" />
            </div>

            <!-- Preview Pane -->
            <div class="border rounded-md">
              <div class="bg-muted px-4 py-2 font-medium text-sm">Preview</div>
              <div 
                class="prose prose-sm sm:prose lg:prose-lg xl:prose-xl max-w-none p-4 min-h-[600px]" 
                v-html="previewHtml"
              />
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Actions -->
      <div class="flex justify-end gap-3">
        <Button type="button" variant="outline" @click="cancel" :disabled="form.processing">
          Cancel
        </Button>
        <Button type="button" @click="save" :disabled="form.processing">
          {{ form.processing ? 'Saving...' : 'Save Changes' }}
        </Button>
      </div>
    </div>
  </AppLayout>
</template>
