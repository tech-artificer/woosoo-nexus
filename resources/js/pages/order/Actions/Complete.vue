<!-- resources/js/Pages/Menus/EditMenu.vue -->
<script setup lang="ts">
// import { onMounted, ref } from 'vue';
// import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { PackageCheck } from 'lucide-vue-next';
// import { Input } from '@/components/ui/input';
// import InputError from '@/components/InputError.vue';
// import { Label } from '@/components/ui/label';
// import { Menu } from '@/types/models';
// import { toast } from 'vue-sonner'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip';

const markAsComplete = () => {
  console.log('markAsComplete');
}

</script>
<template>

    <TooltipProvider :delay-duration="100">
      <Tooltip class="bg-woosoo-dark-gray">
        <TooltipTrigger as-child>
        
            <Button variant="ghost" class="cursor-pointer" @click="markAsComplete()" >
              <PackageCheck />
            </Button>
     
        </TooltipTrigger>
        <TooltipContent>
          <p>Mark as Complete</p>
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>

     
</template>

<style scoped>
.error {
  color: red;
  font-size: 0.8em;
}
</style>
