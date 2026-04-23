import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

export const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-xl text-sm font-medium transition-all duration-200 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-[rgb(246_181_109_/_0.22)] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive",
  {
    variants: {
      variant: {
        default:
          "bg-woosoo-accent text-woosoo-dark-gray shadow-[0_18px_40px_-24px_rgba(176,128,71,0.7)] hover:bg-woosoo-accent/92 hover:-translate-y-0.5",
        destructive:
          "bg-destructive text-white shadow-xs hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60",
        outline:
          "border border-black/10 bg-white/72 text-foreground shadow-[0_18px_40px_-30px_rgba(37,37,37,0.45)] hover:border-woosoo-accent/50 hover:bg-white dark:border-white/10 dark:bg-white/[0.04] dark:hover:bg-white/[0.08]",
        secondary:
          "bg-secondary/80 text-secondary-foreground shadow-xs hover:bg-secondary",
        ghost:
          "text-foreground/80 hover:bg-black/5 hover:text-foreground dark:text-foreground/80 dark:hover:bg-white/[0.07]",
        link: "text-primary underline-offset-4 hover:underline",
        brand:
          "bg-woosoo-accent text-woosoo-dark-gray shadow-xs hover:bg-woosoo-accent/90 focus-visible:ring-woosoo-accent/40",
      },
      size: {
        default: "h-10 px-4 py-2 has-[>svg]:px-3",
        sm: "h-9 gap-1.5 px-3 has-[>svg]:px-2.5",
        lg: "h-11 px-6 has-[>svg]:px-4",
        icon: "size-10",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
)

export type ButtonVariants = VariantProps<typeof buttonVariants>
