"use client"

import * as React from "react"

import { cn } from "#lib/utils"

const Form = React.forwardRef<HTMLFormElement, React.ComponentProps<"form">>(
  ({ className, ...props }, ref) => {
    return <form ref={ref} data-slot="form" className={cn(className)} {...props} />
  }
)

Form.displayName = "Form"

export { Form }
