<p align="center">
  <h1 align="center">🍰 CakeVue Starter</h1>
  <p align="center">
    <strong>Full-stack starter template — CakePHP 5 API + Vue 3 SPA</strong>
  </p>
  <p align="center">
    <a href="#-features">Features</a> •
    <a href="#-getting-started">Getting Started</a> •
    <a href="#-project-structure">Structure</a> •
    <a href="#-adding-crud-resources">Add Resources</a> •
    <a href="#-scripts">Scripts</a>
  </p>
  <p align="center">
    <img src="https://img.shields.io/badge/CakePHP-5.x-D33C43?logo=cakephp" alt="CakePHP 5">
    <img src="https://img.shields.io/badge/Vue-3.5-4FC08D?logo=vue.js" alt="Vue 3">
    <img src="https://img.shields.io/badge/TypeScript-5.x-3178C6?logo=typescript" alt="TypeScript">
    <img src="https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php" alt="PHP 8.1+">
    <img src="https://img.shields.io/badge/License-MIT-blue" alt="License">
  </p>
</p>

---

Stop starting from scratch. CakeVue Starter gives you a production-ready foundation with authentication, CRUD patterns, and a modern frontend — so you can skip the boilerplate and start building your actual app.

> All code lives in your project. Fork it, gut it, make it yours. There's no framework lock-in — just patterns you can follow or ignore.

## ✨ Features

- 🔐 **JWT Authentication** — Login, register, token refresh, logout with blacklisting
- 🗄️ **REST API Backend** — CakePHP 5 with FriendsOfCake/Crud plugin for rapid resource creation
- ⚡ **Vue 3 + Vite** — Composition API, `<script setup>`, hot module replacement
- 🦾 **TypeScript Everywhere** — Shared types between frontend and backend
- 🎨 **PrimeVue + Tailwind CSS** — Beautiful UI components with utility-first styling
- 🍍 **Pinia State Management** — Typed stores with CRUD patterns built in
- 🛡️ **Middleware Stack** — CORS, rate limiting, admin authorization out of the box
- 🔄 **Axios Interceptors** — Auto token injection, proactive refresh, 401 retry
- 🧭 **Vue Router Guards** — Auth/guest route protection with redirect
- 📦 **Sample CRUD Resource** — Complete `Items` example (migration → model → controller → store → view)
- 🧪 **Testing Infrastructure** — Vitest + Playwright (frontend), PHPUnit + PHPStan (backend)
- 🌙 **Dark/Light Mode** — PrimeVue Aura theme with system preference detection

## 📋 Prerequisites

| Requirement | Version |
|------------|---------|
| PHP | 8.1+ |
| Composer | 2.x |
| Node.js | 18+ |
| MySQL | 8+ (or MariaDB / SQLite for dev) |

## 🚀 Getting Started

### Create Your Project

This is a **GitHub Template Repository**. Click the green **"Use this template"** button above, or:

```bash
# Option 1: GitHub CLI (recommended)
gh repo create my-app --template Noahbrat/cakevue-starter --clone --public
cd my-app

# Option 2: degit (no git history)
npx degit Noahbrat/cakevue-starter my-app
cd my-app
git init && git add . && git commit -m "Initial commit"
```

> ⚠️ **Don't clone this repo directly** — you'll end up pushing to the template. Use the template button, `gh repo create --template`, or `degit`.

### Backend Setup

```bash
cd api
cp .env.example .env                    # Edit with your DB credentials
cp config/app_local.example.php config/app_local.php
composer install
bin/cake migrations migrate             # Create users + items tables
```

### Frontend Setup

```bash
cd frontend
npm install                             # or pnpm install
npm run dev                             # Vite dev server → http://localhost:5173
```

### Start the API

```bash
cd api
bin/cake server -p 8765                 # PHP dev server → http://localhost:8765
```

The Vite dev server proxies `/api` requests to `localhost:8765` automatically.

### First Run

1. Open `http://localhost:5173`
2. Register a new account
3. Log in — you'll see the dashboard with the Items CRUD demo
4. Start building your app! 🎉

## 🧱 Tech Stack

| Layer | Technology |
|-------|-----------|
| **Backend Framework** | CakePHP 5 + FriendsOfCake/Crud |
| **Frontend Framework** | Vue 3 (Composition API) + Vite |
| **Language** | TypeScript (frontend) + PHP 8.1 (backend) |
| **UI Components** | PrimeVue 4 + PrimeIcons + Aura Theme |
| **Styling** | Tailwind CSS 4 |
| **State Management** | Pinia |
| **Routing** | Vue Router 4 with navigation guards |
| **HTTP Client** | Axios with JWT interceptors |
| **Authentication** | JWT (access + refresh tokens) + bcrypt |
| **Frontend Testing** | Vitest (unit) + Playwright (e2e) |
| **Backend Testing** | PHPUnit + PHPStan (static analysis) |
| **Code Quality** | ESLint (frontend) + PHP_CodeSniffer (backend) |

## 📁 Project Structure

```
cakevue-starter/
├── api/                              # CakePHP 5 REST API
│   ├── config/
│   │   ├── Migrations/               # Database migrations
│   │   ├── app.php                   # Core config
│   │   ├── app_local.example.php     # Local config template
│   │   ├── routes.php                # API route definitions
│   │   └── bootstrap.php
│   ├── src/
│   │   ├── Application.php           # Middleware stack setup
│   │   ├── Controller/Api/           # API controllers
│   │   │   ├── AppController.php     # Base controller (Crud integration)
│   │   │   ├── ItemsController.php   # Example CRUD resource
│   │   │   └── UsersController.php   # Auth endpoints
│   │   ├── Middleware/               # CORS, rate limiting, admin auth
│   │   └── Model/                    # Entities + Tables
│   └── composer.json
│
├── frontend/                         # Vue 3 SPA
│   ├── src/
│   │   ├── components/               # Reusable components (Navbar, etc.)
│   │   ├── layouts/                  # Page layout wrappers
│   │   ├── router/                   # Routes + auth guards
│   │   ├── services/                 # Axios client + token storage
│   │   ├── stores/                   # Pinia stores (auth, items)
│   │   ├── types/                    # TypeScript interfaces
│   │   ├── utils/                    # Helper functions
│   │   ├── views/                    # Page components
│   │   ├── App.vue                   # Root component
│   │   └── main.ts                   # Entry point
│   ├── package.json
│   └── vite.config.ts
│
├── shared/                           # Shared TypeScript types
│   └── src/types/api.ts
│
├── CLAUDE.md                         # AI-assisted dev conventions
└── README.md
```

## 🔐 Auth Flow

```
Register → POST /api/users/register → creates user (bcrypt password)
     ↓
Login → POST /api/users/jwt_login → returns access_token + refresh_token
     ↓
Requests → Axios interceptor auto-attaches Authorization: Bearer <token>
     ↓
Expiring? → Interceptor proactively refreshes via POST /api/users/jwt_refresh
     ↓
Logout → POST /api/users/logout → blacklists refresh token, clears storage
```

Tokens use **dual-write storage** (localStorage + cookie fallback) for Safari/iOS resilience.

## 🔨 Adding CRUD Resources

The starter includes a complete `Items` example. Here's how to add your own (e.g., `Projects`):

### 1. Backend

**Migration:**
```bash
bin/cake bake migration CreateProjects name:string description:text user_id:integer status:string created modified
bin/cake migrations migrate
```

**Model:**
```bash
bin/cake bake model Projects
```

**Controller** — copy the Items pattern:
```bash
cp src/Controller/Api/ItemsController.php src/Controller/Api/ProjectsController.php
# Edit: rename class, update $modelClass
```

**Route** — add to `config/routes.php`:
```php
$routes->resources('Projects', ['controller' => 'Api/Projects']);
```

### 2. Frontend

**Types** — add to `frontend/src/types/api.ts`:
```typescript
export interface Project {
    id: number;
    name: string;
    description?: string;
    status: 'active' | 'archived';
    created?: string;
}
export type ProjectInput = Omit<Project, 'id' | 'created'>;
```

**API** — add to `frontend/src/services/api.ts`:
```typescript
export const projectsApi = {
    getAll: (params?) => api.get('/projects.json', { params }),
    getById: (id: number) => api.get(`/projects/${id}.json`),
    create: (data: ProjectInput) => api.post('/projects.json', data),
    update: (id: number, data: Partial<ProjectInput>) => api.put(`/projects/${id}.json`, data),
    delete: (id: number) => api.delete(`/projects/${id}.json`),
};
```

**Store** — copy `stores/items.ts` → `stores/projects.ts`, adapt types.

**View** — copy `views/ItemsView.vue` → `views/ProjectsView.vue`, adapt store/types.

**Route** — add to `router/index.ts`:
```typescript
{ path: '/projects', name: 'projects', component: () => import('../views/ProjectsView.vue'), meta: { requiresAuth: true } }
```

That's it — full CRUD from database to UI in about 10 minutes.

## 📜 Scripts

### Frontend

| Command | Description |
|---------|-------------|
| `npm run dev` | Start Vite dev server with HMR |
| `npm run build` | Type-check + production build |
| `npm run preview` | Preview production build locally |
| `npm run test` | Run Vitest unit tests |
| `npm run test:e2e` | Run Playwright end-to-end tests |
| `npm run lint` | Lint + auto-fix with ESLint |
| `npm run type-check` | TypeScript type checking |

### Backend

| Command | Description |
|---------|-------------|
| `bin/cake server -p 8765` | Start PHP development server |
| `bin/cake migrations migrate` | Run pending migrations |
| `bin/cake bake model <Name>` | Generate model + entity + table |
| `bin/cake bake migration <Name>` | Generate a new migration |
| `vendor/bin/phpunit` | Run PHPUnit test suite |
| `vendor/bin/phpstan analyse` | Static analysis |

## ⚙️ Configuration

### Frontend Environment (`frontend/.env`)

| Variable | Description | Default |
|----------|-------------|---------|
| `VITE_API_BASE_URL` | API base URL for dev proxy | `/api` |
| `VITE_APP_NAME` | Application display name | `MyApp` |

### Backend Environment (`api/.env`)

| Variable | Description |
|----------|-------------|
| `DATABASE_URL` | Database connection string |
| `JWT_SECRET` | Secret key for JWT signing |
| `JWT_ACCESS_EXPIRY` | Access token TTL (default: 15 min) |
| `JWT_REFRESH_EXPIRY` | Refresh token TTL (default: 7 days) |

## 🤖 AI-Assisted Development

This project includes a `CLAUDE.md` with conventions for working with Claude Code or similar AI coding assistants. It documents the project's patterns so AI tools can generate consistent code.

## 🗺️ Roadmap

Some ideas for future additions (PRs welcome):

- [ ] Docker Compose setup (PHP-FPM + nginx + MySQL)
- [ ] GitHub Actions CI/CD pipeline
- [ ] Password reset flow
- [ ] Email verification
- [ ] Role-based access control (RBAC)
- [ ] File upload service pattern
- [ ] WebSocket support
- [ ] OpenAPI/Swagger auto-generation
- [ ] Database seeder with sample data

## 📄 License

[MIT](LICENSE) — do whatever you want with it.

---

<p align="center">
  Built by extracting battle-tested patterns from a production app.<br>
  If this saved you time, give it a ⭐
</p>
