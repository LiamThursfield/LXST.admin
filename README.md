# LXST.admin - Multitenant Platform

Welcome to the LXST.admin platform, a robust multitenant application built for modular deployment (CMS, CRM, and more).

## Tech Stack
- **Backend**: [Laravel](https://laravel.com/)
- **Multitenancy**: [Tenancy for Laravel v4](https://v4.tenancyforlaravel.com/i)
- **Frontend**: [Vue 3](https://vuejs.org/) + [Inertia.js v2](https://inertiajs.com/) (using strictly **TypeScript**)
- **UI Library**: [Nuxt UI (Vue/Inertia adaptation)](https://ui.nuxt.com/)
- **Styling**: [Tailwind CSS v4](https://tailwindcss.com/)
- **Provider Environment**: Laravel Sail (MySQL, Redis, MinIO, Mailpit)
- **Code Quality**: Laravel Pint, Larastan, ESLint, and Prettier

## Project Structure

This application is split into two distinct contextual domains, each utilizing its own Vite config for optimized asset bundling:

### 1. Central App (`vite.central.config.ts`)
The central administrative hub run by the platform owner.
- **Purpose**: Tenant management, provisioning, platform-wide analytics, and administrative oversight.
- **Scope**: Operates on the central database connection.

### 2. Tenant App (`vite.tenant.config.ts`)
The core product experience used by individual tenants.
- **Purpose**: A modular system featuring a built-in CMS for website management, CRM capabilities, and API endpoints for connecting detached websites (which can fetch pages and submit forms).
- **Scope**: Operates on individual tenant database connections.

## Local Development

Since this project features **Laravel Sail**, you should run your backend services using Sail. The stack includes MySQL, Redis, MinIO, and Mailpit.

1. Install backend dependencies (if you haven't already aliased sail, you may need a workaround to install composer first, or just run it via PHP locally):
   ```bash
   composer install
   ```
2. Start the Sail environment:
   ```bash
   ./vendor/bin/sail up -d
   ```
3. Install frontend dependencies:
   ```bash
   pnpm install
   ```
4. Set up your `.env` file:
   ```bash
   cp .env.example .env
   ./vendor/bin/sail artisan key:generate
   ```
5. Run migrations for the central database:
   ```bash
   ./vendor/bin/sail artisan migrate
   ```
6. *(Optional)* Run migrations for tenant databases:
   ```bash
   ./vendor/bin/sail artisan tenants:migrate
   ```

### Running the asset dev servers

Since there are two Vite configs, you can start the development servers independently or concurrently.

To run both concurrently:
```bash
pnpm run dev
```

To run them individually:
- **Central App**: `pnpm run dev:central`
- **Tenant App**: `pnpm run dev:tenant`

### Building for production

To build both apps:
```bash
pnpm run build
```

Or individually:
- `pnpm run build:central`
- `pnpm run build:tenant`


## Acknowledgments / Credits
This project was built with inspiration and code from several open-source repositories:

- [laravel-nuxt-ui-starter-kit (unofficial)](https://github.com/jkque/laravel-nuxt-ui-starter-kit/) - Used to help get the auth/dashboard setup
- [dashboard-vue](https://github.com/nuxt-ui-templates/dashboard-vue) - Used to help  get the auth/dashboard setup

## License
This project is open-sourced software licensed under the MIT license.
