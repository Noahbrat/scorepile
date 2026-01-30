# CLAUDE.md — Project Conventions

This file defines conventions for AI-assisted development on this project.

## Project Overview

Full-stack starter template: **CakePHP 5** API + **Vue 3** SPA.  
Example CRUD resource: "Items" — use this as the pattern for adding new resources.

## Architecture

```
api/          → CakePHP 5 REST API (JSON only, no HTML views)
frontend/     → Vue 3 + Vite SPA (PrimeVue UI, Pinia state, Tailwind CSS)
shared/       → TypeScript types shared between frontend and backend
```

## Backend Conventions (CakePHP)

- **Controllers** live in `src/Controller/Api/` and extend `Api\AppController`
- **All responses** are JSON — use CakePHP's `RequestHandler` + `.json` extensions
- **JWT auth** — use `JwtAuthTrait` for authenticated endpoints
- **Routes** defined in `config/routes.php` using `$routes->resources()`
- **Migrations** in `config/Migrations/` — never edit the database manually
- **Models** use CakePHP ORM with Table + Entity classes
- **UUIDs** for user IDs, auto-increment integers for resource IDs

## Frontend Conventions (Vue 3)

### Components & Views
- **Always** use `<script setup lang="ts">` (Composition API)
- **Views** = page-level components in `views/` (one per route)
- **Components** = reusable UI in `components/`
- **Layouts** = page wrappers in `layouts/`

### State Management (Pinia)
- One store per domain: `stores/auth.ts`, `stores/items.ts`, etc.
- Use `defineStore("name", () => { ... })` setup syntax
- Keep stores thin — business logic in actions, computed in getters
- Ref for state, computed for getters, async functions for actions

### API Layer
- All HTTP calls go through `services/api.ts` (Axios instance)
- Export one API object per resource: `authApi`, `itemsApi`, etc.
- Types in `types/api.ts` — keep them in sync with backend responses
- Axios interceptors handle token injection and 401 refresh automatically

### Routing
- Routes in `router/index.ts`
- Use `meta: { requiresAuth: true }` for protected routes
- Use `meta: { requiresGuest: true }` for login/register (redirects if already logged in)
- Lazy-load all views except HomeView

### UI Components (PrimeVue)
- Import PrimeVue components individually (no global registration)
- Use `DataTable` for list views with server-side pagination
- Use `Dialog` for create/edit modals
- Use `Toast` (via `useToast()`) for success/error notifications
- Use `Tag` for status badges
- Use PrimeIcons (`pi pi-*`) for all icons

### Styling
- Tailwind CSS 4 for utility classes
- PrimeVue theme tokens for colours (`text-primary`, `text-muted-color`, `bg-surface-*`)
- Scoped `<style scoped>` per component — avoid global CSS
- Use `:deep()` for PrimeVue component overrides

### TypeScript
- Strict mode enabled
- Define interfaces for all API responses and request payloads
- Use `type` imports where possible: `import type { ... } from ...`
- Path alias: `@/` → `frontend/src/`

## Adding a New CRUD Resource

1. **Backend**: Migration → Model (Table + Entity) → Controller → Route
2. **Frontend**: Type → API service → Pinia store → View → Route
3. Follow the Items pattern exactly — copy and rename

## Git Conventions

- Feature branches off `main`
- Conventional commits: `feat:`, `fix:`, `refactor:`, `docs:`, `chore:`
- Keep commits atomic and descriptive

## Don't

- Don't add global component registration — import individually
- Don't use Options API — always Composition API with `<script setup>`
- Don't store secrets in code — use `.env` files (gitignored)
- Don't modify PrimeVue theme globally — use CSS variables and scoped styles
- Don't use `any` type — always define proper interfaces
