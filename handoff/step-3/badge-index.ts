import { cva, type VariantProps } from 'class-variance-authority'

export { default as Badge } from './Badge.vue'

// WOOSOO STEP 3: added `warning` variant; fixed dark-mode for success/active/accent

// Shared classes for `success` and `active` — identical green styling; single source of truth
const SHARED_SUCCESS_ACTIVE_CLASSES =
  'border-woosoo-green/25 bg-woosoo-green/12 text-woosoo-green dark:bg-woosoo-green/20 dark:text-woosoo-green-100 dark:border-woosoo-green/30'

export const badgeVariants = cva(
  'inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden',
  {
    variants: {
      variant: {
        default:
          'border-transparent bg-primary text-primary-foreground [a&]:hover:bg-primary/90',
        secondary:
          'border-transparent bg-secondary text-secondary-foreground [a&]:hover:bg-secondary/90',
        destructive:
          'border-transparent bg-destructive text-white [a&]:hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60',
        outline:
          'text-foreground [a&]:hover:bg-accent [a&]:hover:text-accent-foreground',
        success: SHARED_SUCCESS_ACTIVE_CLASSES,
        active:  SHARED_SUCCESS_ACTIVE_CLASSES,
        warning:
          'border-woosoo-accent/30 bg-woosoo-accent/12 text-woosoo-primary-dark dark:bg-woosoo-accent/18 dark:text-woosoo-accent dark:border-woosoo-accent/30',
        accent:
          'border-woosoo-blue/25 bg-woosoo-blue/10 text-woosoo-blue dark:bg-woosoo-blue/18 dark:border-woosoo-blue/30',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  },
)
export type BadgeVariants = VariantProps<typeof badgeVariants>
