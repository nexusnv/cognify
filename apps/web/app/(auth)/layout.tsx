export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return <main id="main-content" className="min-h-svh bg-muted/40 text-foreground">{children}</main>;
}
