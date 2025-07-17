import { toast } from 'vue-sonner'

export const useToast = () => {
  return {
    success: (message: string) => toast({ title: 'Success', description: message }),
    error: (message: string) => toast({ title: 'Error', description: message, variant: 'destructive' }),
  }
}