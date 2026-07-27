import { cva, type VariantProps } from 'class-variance-authority'

export const buttonVariants = cva(
  'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg text-sm font-extrabold transition-[background-color,box-shadow,transform,color,border-color] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:pointer-events-none disabled:opacity-70 motion-safe:hover:-translate-y-0.5',
  {
    defaultVariants: {
      size: 'default',
      variant: 'default'
    },
    variants: {
      size: {
        default: 'min-h-12 px-5 py-3',
        sm: 'min-h-10 px-4 py-2',
        lg: 'min-h-13 px-7 py-4'
      },
      variant: {
        default: 'bg-primary text-primary-foreground shadow-[0_10px_24px_hsl(var(--primary)/0.24)] hover:bg-primary/92',
        destructive: 'bg-destructive text-destructive-foreground hover:bg-destructive/92',
        outline: 'border border-border bg-card text-foreground hover:bg-muted',
        secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/88',
        ghost: 'text-muted-foreground hover:bg-muted hover:text-foreground',
        link: 'h-auto min-h-0 p-0 text-primary underline-offset-4 hover:translate-y-0 hover:underline'
      }
    }
  }
)

export type ButtonVariants = VariantProps<typeof buttonVariants>
