<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { marked } from 'marked'
import DOMPurify from 'dompurify'
import { Button } from '@/components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Pencil } from 'lucide-vue-next'

type Guide = {
  id: string
  section: string
  title: string
  summary: string
  markdown: string
}

type Section = {
  title: string
  description: string
}

const props = defineProps<{
  guides: Guide[]
  sections: Record<string, Section>
}>()

// Configure marked for proper rendering
marked.setOptions({
  breaks: true,
  gfm: true,
})

// Render markdown safely
const renderMarkdown = (markdown: string) => {
  const html = marked.parse(markdown) as string
  return DOMPurify.sanitize(html, {
    ALLOWED_TAGS: [
      'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
      'p', 'br', 'strong', 'em', 'u', 'strike',
      'ul', 'ol', 'li',
      'a', 'img',
      'code', 'pre',
      'blockquote',
      'table', 'thead', 'tbody', 'tr', 'th', 'td',
      'hr',
    ],
    ALLOWED_ATTR: ['href', 'src', 'alt', 'class', 'id', 'target', 'rel'],
  })
}

// Group guides by section
const adminGuides = computed(() => props.guides.filter(g => g.section === 'admin'))
const tabletGuides = computed(() => props.guides.filter(g => g.section === 'tablet'))
const relayGuides = computed(() => props.guides.filter(g => g.section === 'relay'))
</script>

<template>
  <Head title="Manual" />

  <AppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="rounded-lg border bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-semibold">Manual & Guides</h1>
        <p class="mt-2 text-sm text-muted-foreground">
          Comprehensive documentation for all three systems: Admin Dashboard, Tablet Ordering, and Printer Relay.
        </p>
        <p class="mt-2 text-xs text-muted-foreground">
          Click "Edit" on any guide to update content using the WYSIWYG editor. Images can be embedded directly in the content.
        </p>
      </div>

      <!-- Tabbed Interface -->
      <Tabs default-value="admin" class="w-full">
        <TabsList class="grid w-full grid-cols-3">
          <TabsTrigger value="admin">Admin Dashboard</TabsTrigger>
          <TabsTrigger value="tablet">Tablet Ordering</TabsTrigger>
          <TabsTrigger value="relay">Printer Relay</TabsTrigger>
        </TabsList>

        <!-- Admin Dashboard Guides -->
        <TabsContent value="admin" class="mt-6 space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>{{ sections.admin.title }}</CardTitle>
              <p class="text-sm text-muted-foreground">{{ sections.admin.description }}</p>
            </CardHeader>
          </Card>

          <div class="space-y-4">
            <Card v-for="guide in adminGuides" :key="guide.id">
              <CardHeader class="flex flex-row items-start justify-between space-y-0">
                <div class="space-y-1">
                  <CardTitle class="text-lg">{{ guide.title }}</CardTitle>
                  <p class="text-sm text-muted-foreground">{{ guide.summary }}</p>
                </div>
                <Link :href="route('manual.edit', guide.id)">
                  <Button size="sm" variant="outline">
                    <Pencil class="w-4 h-4 mr-2" />
                    Edit
                  </Button>
                </Link>
              </CardHeader>
              <CardContent>
                <div 
                  class="prose prose-sm max-w-none prose-headings:font-semibold prose-a:text-primary prose-img:rounded-md prose-img:shadow-sm"
                  v-html="renderMarkdown(guide.markdown)"
                />
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        <!-- Tablet Ordering Guides -->
        <TabsContent value="tablet" class="mt-6 space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>{{ sections.tablet.title }}</CardTitle>
              <p class="text-sm text-muted-foreground">{{ sections.tablet.description }}</p>
            </CardHeader>
          </Card>

          <div class="space-y-4">
            <Card v-for="guide in tabletGuides" :key="guide.id">
              <CardHeader class="flex flex-row items-start justify-between space-y-0">
                <div class="space-y-1">
                  <CardTitle class="text-lg">{{ guide.title }}</CardTitle>
                  <p class="text-sm text-muted-foreground">{{ guide.summary }}</p>
                </div>
                <Link :href="route('manual.edit', guide.id)">
                  <Button size="sm" variant="outline">
                    <Pencil class="w-4 h-4 mr-2" />
                    Edit
                  </Button>
                </Link>
              </CardHeader>
              <CardContent>
                <div 
                  class="prose prose-sm max-w-none prose-headings:font-semibold prose-a:text-primary prose-img:rounded-md prose-img:shadow-sm"
                  v-html="renderMarkdown(guide.markdown)"
                />
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        <!-- Printer Relay Guides -->
        <TabsContent value="relay" class="mt-6 space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>{{ sections.relay.title }}</CardTitle>
              <p class="text-sm text-muted-foreground">{{ sections.relay.description }}</p>
            </CardHeader>
          </Card>

          <div class="space-y-4">
            <Card v-for="guide in relayGuides" :key="guide.id">
              <CardHeader class="flex flex-row items-start justify-between space-y-0">
                <div class="space-y-1">
                  <CardTitle class="text-lg">{{ guide.title }}</CardTitle>
                  <p class="text-sm text-muted-foreground">{{ guide.summary }}</p>
                </div>
                <Link :href="route('manual.edit', guide.id)">
                  <Button size="sm" variant="outline">
                    <Pencil class="w-4 h-4 mr-2" />
                    Edit
                  </Button>
                </Link>
              </CardHeader>
              <CardContent>
                <div 
                  class="prose prose-sm max-w-none prose-headings:font-semibold prose-a:text-primary prose-img:rounded-md prose-img:shadow-sm"
                  v-html="renderMarkdown(guide.markdown)"
                />
              </CardContent>
            </Card>
          </div>
        </TabsContent>
      </Tabs>
    </div>
  </AppLayout>
</template>
