# Frontend modules

Feature-owned code lives here, one folder per domain area. Everything outside
`modules/` is shared plumbing: `components/ui` (shadcn), `layouts`, `lib`,
`hooks`, `types`, and the Inertia `pages` entry points.

## Anatomy

```
modules/<feature>/
├── components/      # feature-only React components
├── hooks/           # feature-only hooks
├── layouts/         # shells this feature owns (optional)
├── types.ts         # prop contracts, mirrored by the PHP controller
└── index.ts         # barrel — the module's public surface
```

## The two import rules

1. **Inside a module, import relatively** (`./company-logo`, `../types`). This
   keeps a module movable and makes its internal wiring obvious.
2. **Across modules, import the barrel** (`@/modules/company`) — never reach
   into another module's files. If you need something that the barrel does not
   export, that is the signal to decide whether it is really public.

```tsx
// pages/dashboard.tsx
import { useCompanyBrand } from '@/modules/company';
import { DashboardHero, TodaysSalesTable } from '@/modules/dashboard';
```

Anything two modules both need moves out to `lib/` or `components/ui` instead
of being cross-imported.

## Pages

Inertia resolves pages from `resources/js/pages/**`, so a page file stays
there. Keep it thin: read props, compose module components, render. The page
is the seam between a route and a module, not a place for feature logic.

## Current modules

| Module      | Owns                                                         |
| ----------- | ------------------------------------------------------------ |
| `company`   | Tenant shell: branding, top navigation, `CompanyLayout`      |
| `dashboard` | The company landing screen: hero banner, today's sales table |
| `settings`  | Company settings surfaces (name, logo)                       |

Starter-kit code (auth, teams, profile/security settings) still lives in the
flat `pages/` + `components/` layout. It migrates into modules as it is
touched — not in a big-bang move.
