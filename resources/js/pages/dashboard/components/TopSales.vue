<script setup lang="ts">
import {
  Avatar,
  AvatarFallback,
  AvatarImage,
} from '@/components/ui/avatar'
</script>

<template>
  <div class="space-y-8">
    <div class="flex items-center">
      <Avatar class="h-9 w-9">
        <AvatarImage src="/avatars/01.png" alt="Avatar" />
        <AvatarFallback>OM</AvatarFallback>
      </Avatar>
      <div class="ml-4 space-y-1">
        <p class="text-sm font-medium leading-none">
          Olivia Martin
        </p>
        <p class="text-sm text-muted-foreground">
          olivia.martin@email.com
        </p>
      </div>
      <div class="ml-auto font-medium">
        +$1,999.00
      </div>
    </div>
    <div class="flex items-center">
      <Avatar class="flex h-9 w-9 items-center justify-center space-y-0 border">
        <AvatarImage src="/avatars/02.png" alt="Avatar" />
        <AvatarFallback>JL</AvatarFallback>
      </Avatar>
      <div class="ml-4 space-y-1">
        <p class="text-sm font-medium leading-none">
          Jackson Lee
        </p>
        <p class="text-sm text-muted-foreground">
          jackson.lee@email.com
        </p>
      </div>
      <div class="ml-auto font-medium">
        +$39.00
      </div>
    </div>
    <div class="flex items-center">
      <Avatar class="flex h-9 w-9 items-center justify-center space-y-0 border">
        <AvatarImage src="/avatars/02.png" alt="Avatar" />
        <AvatarFallback>JL</AvatarFallback>
      </Avatar>
      <div class="ml-4 space-y-1">
        <p class="text-sm font-medium leading-none">
          Jackson Lee
        </p>
        <p class="text-sm text-muted-foreground">
          jackson.lee@email.com
        </p>
      </div>
      <div class="ml-auto font-medium">
        +$39.00
      </div>
    </div>
  </div>
</template>