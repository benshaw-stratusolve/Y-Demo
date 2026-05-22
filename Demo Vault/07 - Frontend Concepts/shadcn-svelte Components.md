# shadcn-svelte Components

> A collection of copy-paste UI components built on Bits UI primitives — fully owned, fully customisable, no node_modules blackbox.

---

## Concept Explained

shadcn-svelte is not a component library you install as a dependency — it's a CLI tool that copies component source code directly into your project (`resources/js/components/ui/`). You own every line. Components are built on top of Bits UI (accessible, headless Svelte primitives) and styled with Tailwind CSS. `components.json` configures the CLI (paths, aliases, style).

---

## How it's Used in Y

### Component location

All UI components live in `resources/js/components/ui/`:

```
components/ui/
├── alert/        ← Alert, AlertTitle, AlertDescription
├── avatar/       ← Avatar, AvatarFallback, AvatarImage
├── badge/        ← Badge
├── button/       ← Button (variant: default, outline, ghost, etc.)
├── card/         ← Card, CardHeader, CardContent, CardFooter
├── checkbox/     ← Checkbox
├── dialog/       ← Dialog, DialogTrigger, DialogContent, etc.
├── dropdown-menu/
├── input/        ← Input
├── label/        ← Label
├── pagination/   ← Pagination, PaginationLink, PaginationNext, etc.
├── select/       ← Select, SelectTrigger, SelectContent, SelectItem
├── separator/    ← Separator
├── sheet/        ← Sheet (slide-over panel)
├── sidebar/      ← Full sidebar system (20+ sub-components)
└── skeleton/     ← Skeleton loading placeholders
```

### Usage in Y

```svelte
<!-- Dashboard.svelte -->
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

<Badge variant="secondary">{post.likes_count}</Badge>
<Button onclick={submitPost} disabled={!postBody.trim() && !postImage}>Post</Button>
```

```svelte
<!-- Profile.svelte -->
import { Dialog, DialogContent, DialogTitle, DialogTrigger } from '@/components/ui/dialog';

<Dialog>
    <DialogTrigger>Delete account</DialogTrigger>
    <DialogContent>
        <DialogTitle>Are you sure?</DialogTitle>
    </DialogContent>
</Dialog>
```

### `components.json`

```json
{
    "style": "new-york",
    "tailwind": { "config": "tailwind.config.ts", "css": "resources/css/app.css" },
    "aliases": { "components": "@/components", "ui": "@/components/ui" }
}
```

This file tells the shadcn CLI where to install components and what import aliases to use.

---

## Key Code Snippet

```svelte
<!-- The sidebar system from resources/js/components/AppSidebar.svelte -->
import {
    Sidebar, SidebarContent, SidebarFooter, SidebarHeader,
    SidebarMenu, SidebarMenuButton, SidebarMenuItem,
} from '@/components/ui/sidebar';
```

The sidebar is 20+ components working together — all of it is in your `ui/sidebar/` folder, readable and editable.

---

## Why This Approach

Traditional component libraries (like Material UI or Chakra) are black boxes — you can't easily customise internals. shadcn's copy-paste approach means if a component doesn't do exactly what you need, you edit the source directly. The trade-off is that updates require re-running the CLI and merging changes manually — but given the full ownership, that's acceptable.

---

## Related Notes

- [[Svelte 5 Runes]]
- [[Dark Mode + Appearance]]
- [[Laravel + Inertia + Svelte]]
