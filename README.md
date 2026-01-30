# 🃏 Scorepile

**Card game score keeper for game nights, parties, and anywhere you need to keep track of who's winning.**

Built with CakePHP 5 + Vue 3 + TypeScript. Created from [cakevue-starter](https://github.com/Noahbrat/cakevue-starter).

## Prerequisites

- PHP 8.1+, Composer 2.x
- Node.js 18+, npm or pnpm
- MySQL 8+ (or MariaDB / SQLite for dev)

## Quick Start

```bash
# API setup
cd api
cp .env.example .env                    # Edit with your DB credentials
cp config/app_local.example.php config/app_local.php
composer install
bin/cake migrations migrate

# Frontend setup
cd ../frontend
npm install
npm run dev                             # → http://localhost:5173

# API server (separate terminal)
cd api
bin/cake server -p 8765                 # → http://localhost:8765
```

## License

[MIT](LICENSE)
