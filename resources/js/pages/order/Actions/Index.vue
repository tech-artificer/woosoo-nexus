<!-- resources/js/Pages/Menus/EditMenu.vue -->
<script setup lang="ts">
// import { onMounted, ref } from 'vue';
// import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { PackageCheck, Printer, CaptionsOff } from 'lucide-vue-next';
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

defineProps<{
    row: any  
}>()

const complete = () => {
  console.log('markAsComplete');
}

const print = () => {
  console.log('print order');
}

const voidOrder = () => {
  console.log('print order');
}

</script>
<template>
  <div class="flex justify-end gap-2">
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger as-child>
        
            <Button variant="ghost" class="cursor-pointer size-8" @click="complete()" >
              <PackageCheck  class="text-woosoo-dark-gray" />
            </Button>
        </TooltipTrigger>
        <TooltipContent>
          <p>Mark as Complete</p>
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>

    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger as-child>
        
            <Button variant="ghost" class="cursor-pointer size-8" @click="print()" >
              <Printer class="text-woosoo-dark-gray" />
            </Button>
     
        </TooltipTrigger>
        <TooltipContent>
          <p>Print</p>
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>

    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger as-child>
        
            <Button variant="ghost" class="cursor-pointer size-8" @click="voidOrder()" disabled>
              <CaptionsOff class="text-woosoo-dark-gray" />
            </Button>
     
        </TooltipTrigger>
        <TooltipContent>
          <p class="">Void</p>
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>

  </div>

</template>

<style scoped>
.error {
  color: red;
  font-size: 0.8em;
}
</style>
