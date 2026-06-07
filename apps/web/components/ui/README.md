# apps/web/components/ui

This folder is the audited custom composite exception layer for the shadcn factory rewrite.

Use it only when factory shadcn primitives do not provide a practical equivalent.

Required file comment:

```ts
// shadcn-factory-exception: <reason>; primitives=<PrimitiveA, PrimitiveB>; routes=<route or feature>
```

Allowed groups:

- `headers`
- `graph`
- `scorecard`
- `procurement-table`
- `workflow-state`

Do not add generic Button, Card, Dialog, Form, Table, Badge, Alert, Sheet, Popover, Select, Tabs, or Tooltip wrappers here. Import those from `@cognify/ui`.
