import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"
import { cn } from "@/lib/utils"
import { Slot } from "radix-ui"

const buttonVariants = cva('pwl-button', {variants:{variant:{default:'pwl-button--primary',destructive:'pwl-button--danger',outline:'pwl-button--secondary',secondary:'pwl-button--secondary',ghost:'pwl-button--ghost',link:'pwl-button--ghost'},size:{default:'',xs:'pwl-button--compact',sm:'pwl-button--compact',lg:'',icon:'', 'icon-xs':'','icon-sm':'','icon-lg':''}},defaultVariants:{variant:'default',size:'default'}})

function Button({
  className,
  variant = "default",
  size = "default",
  asChild = false,
  ...props
}: React.ComponentProps<"button"> &
  VariantProps<typeof buttonVariants> & {
    asChild?: boolean
  }) {
  const Comp = asChild ? Slot.Root : "button"

  return (
    <Comp
      data-slot="button"
      data-variant={variant}
      data-size={size}
      className={cn(buttonVariants({ variant, size, className }))}
      {...props}
    />
  )
}

export { Button, buttonVariants }
