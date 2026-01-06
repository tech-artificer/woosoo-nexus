# Woosoo Nexus

A comprehensive restaurant management system built with Laravel and Vue.js, featuring real-time order management, table service coordination, and multi-device synchronization.

## Overview

Woosoo Nexus is a full-stack restaurant management platform that integrates with the Krypton POS system to provide:

- **Admin Dashboard** - Web-based management interface for orders, menus, devices, and staff
- **Tablet Ordering PWA** - Customer-facing progressive web app for table-side ordering
- **Relay Device** - Flutter-based device for real-time order relay and printing
- **Print Service** - Standalone Node.js service for thermal printer integration

## Tech Stack

### Backend
- **Laravel 12** (PHP 8.2+)
- **MySQL** - Dual database connections (app data + Krypton POS integration)
- **Laravel Sanctum** - API authentication
- **Laravel Reverb** - WebSocket server for real-time updates
- **Pest** - Testing framework

### Frontend
- **Vue 3** with TypeScript
- **Inertia.js** - SPA-like experience without building APIs
- **Vite** - Fast development and build tooling
- **Tailwind CSS v4** - Utility-first styling
- **shadcn-vue** - Reusable UI components
- **Laravel Echo** - WebSocket client

### Additional Services
- **Node.js** - Print service (Express server)
- **Flutter** - Relay device application

## Prerequisites

- **PHP** >= 8.2
- **Node.js** >= 18.x
- **Composer** >= 2.x
- **MySQL** >= 8.0
- **npm** or **yarn**

## Quick Start

### Installation

```powershell
# Clone the repository
git clone https://github.com/tech-artificer/woosoo-nexus.git
cd woosoo-nexus

# Install dependencies
composer install
npm ci

# Setup environment
cp .env.example .env
php artisan key:generate

# Configure database in .env
# DB_CONNECTION=mysql
# DB_DATABASE=woosoo_api
# DB_USERNAME=root
# DB_PASSWORD=

# Run migrations
php artisan migrate --seed

# Start development server (all-in-one)
composer dev
```

The `composer dev` command starts all necessary services:
- Laravel HTTP server (port 8000)
- Queue worker
- Vite dev server (port 5173)

### Alternative: Individual Services

```powershell
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Queue worker
php artisan queue:listen --tries=1

# Terminal 3: Vite dev server
npm run dev

# Terminal 4: WebSocket server
php artisan reverb:start

# Terminal 5: Print service (optional)
node print-service/index.js
```

## Development

### Available Commands

**Backend:**
```powershell
composer dev         # Start all services (serve + queue + vite)
composer dev:ssr     # Start with SSR support
composer test        # Run tests
php artisan tinker   # Interactive REPL
php artisan pail     # Real-time log viewer
```

**Frontend:**
```powershell
npm run dev          # Vite dev server with HMR
npm run build        # Production build
npm run build:ssr    # Build with SSR support
npm run format       # Format code with Prettier
npm run lint         # Lint code with ESLint
```

**Database:**
```powershell
php artisan migrate           # Run migrations
php artisan migrate:fresh     # Fresh migration
php artisan migrate:fresh --seed  # Fresh with seeders
php artisan db:show           # Database info
```

### Testing

```powershell
# Run all tests
composer test

# Run specific test file
./vendor/bin/pest tests/Feature/DeviceTableTest.php

# Run with filter
./vendor/bin/pest --filter=OrderServiceTest
```

Tests use SQLite in-memory database for speed. Configuration in `phpunit.xml`.

## Project Structure

```
woosoo-nexus/
├── app/
│   ├── Actions/              # Laravel Actions pattern
│   ├── Events/               # Broadcastable events
│   ├── Http/Controllers/
│   │   ├── Admin/           # Web controllers (Inertia)
│   │   └── Api/V1/          # API controllers (JSON)
│   ├── Models/
│   │   └── Krypton/         # POS integration models
│   ├── Repositories/        # Data access layer
│   └── Services/            # Business logic
├── database/
│   ├── migrations/          # Database schema
│   └── seeders/            # Data seeders
├── resources/
│   ├── js/
│   │   ├── components/     # Vue components
│   │   ├── composables/    # Vue composables
│   │   ├── layouts/        # App layouts
│   │   └── pages/          # Inertia pages
│   └── views/              # Blade templates
├── routes/
│   ├── web.php             # Web routes
│   ├── api.php             # API routes
│   └── channels.php        # Broadcast channels
├── tests/
│   ├── Feature/            # Feature tests
│   └── Unit/               # Unit tests
├── docs/                   # Documentation
├── print-service/          # Node.js print service
├── relay-device/           # Flutter relay app
└── tablet-ordering-pwa/    # Customer ordering PWA
```

## Key Features

### Real-time Updates
- WebSocket-based order notifications
- Live dashboard updates
- Device synchronization
- Service request notifications

### Multi-Database Integration
- Primary database for application data
- Read-only integration with Krypton POS system
- Stored procedure support for POS operations

### Device Management
- Dynamic device registration
- IP-based device discovery
- Token-based authentication
- Table assignment and tracking

### Order Management
- Order lifecycle tracking (CONFIRMED → COMPLETED/VOIDED)
- Print job management
- Order history and reporting
- Multi-item order support

### Printing System
- ESC/POS thermal printer support
- Queue-based print job processing
- Automatic retry mechanisms
- Print event broadcasting

## Configuration

### Environment Variables

Key configuration in `.env`:

```env
# Application
APP_NAME=Woosoo
APP_URL=http://localhost:8000

# Database (App)
DB_CONNECTION=mysql
DB_DATABASE=woosoo_api

# Database (POS Integration)
DB_POS_CONNECTION=pos
DB_POS_DATABASE=krypton_woosoo

# WebSocket (Reverb)
REVERB_HOST=127.0.0.1
REVERB_PORT=6001

# Print Service
VITE_DEV_SERVER_URL=http://localhost:3000
```

### Dual Database Setup

The application uses two MySQL connections:
- `mysql` (default) - Application data
- `pos` - Krypton POS system (read-only)

Configure both in `.env` and `config/database.php`.

## Documentation

Comprehensive documentation available in the `docs/` directory:

- **[API Map](docs/API_MAP.md)** - API endpoint reference
- **[Admin Manual](docs/admin_manual.md)** - Administrator guide
- **[Printer Manual](docs/printer_manual.md)** - Printer setup and usage
- **[Roles Implementation](docs/ROLES_IMPLEMENTATION_COMPLETE.md)** - Roles and permissions
- **[Branch CRUD](docs/BRANCH_CRUD_IMPLEMENTATION.md)** - Example CRUD implementation

## Production Deployment

### Windows Services

Production deployment uses NSSM to run services:
- `woosoo-scheduler` - Task scheduler
- `woosoo-reverb` - WebSocket server
- `woosoo-queue` - Queue worker
- `woosoo-printer` - Print service

### Build Process

```powershell
# Install dependencies
composer install --optimize-autoloader --no-dev
npm ci --production

# Build frontend
npm run build

# Optimize Laravel
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Code Style

- **PHP**: Follow PSR-12, use Laravel Pint (`composer pint`)
- **JavaScript/TypeScript**: Use ESLint and Prettier
- **Vue**: Composition API with `<script setup>`

## License

This project is proprietary software. All rights reserved.

## Support

For issues and questions:
- Create an issue on GitHub
- Check existing documentation in `docs/`
- Review API documentation at `/docs/api` (when server is running)

## Acknowledgments

- Built with [Laravel](https://laravel.com)
- UI components from [shadcn-vue](https://www.shadcn-vue.com)
- Integrates with Krypton POS system
