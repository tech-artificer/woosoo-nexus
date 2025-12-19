import { toast as sonnerToast } from 'vue-sonner'

export const useToast = () => {
  return {
    toast: sonnerToast,
    success: (message: string) => sonnerToast({ title: 'Success', description: message }),
    error: (message: string) => sonnerToast({ title: 'Error', description: message, variant: 'destructive' }),
  }
}