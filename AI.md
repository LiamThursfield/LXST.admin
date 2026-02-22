# Project Context for AI Assistants

This file contains project-specific context, conventions, and architectural rules for this application. It works alongside the Laravel Boost guidelines (`GEMINI.md`). **AI Assistants should always refer to this file to understand architectural constraints and general context.**

## Application Overview
- **Name**: LXST.admin
- **Architecture**: Multitenant SaaS Platform
- **Core Packages**: Laravel 12+, Tenancy for Laravel v4, Inertia.js v2, Vue 3, Nuxt UI, Tailwind CSS v4.
- **Local Infrastructure**: Laravel Sail (MySQL, Redis, MinIO, Mailpit)

## Domain Separation
The application uses two distinct frontend builds and routing domains:
1. **Central App**: For administrative management of tenants.
   - Configured via `vite.central.config.ts`.
   - Responsible for provisioning, billing, and platform analytics.
2. **Tenant App**: The end-user application containing modular features (CMS, CRM).
   - Configured via `vite.tenant.config.ts`.
   - APIs: Includes public-facing API endpoints for detached tenant websites to consume CMS data and submit forms.

## Rules & Conventions

### Tenancy
- When building tenant features, ensure they are tenancy-aware according to the `stancl/tenancy` v4 documentation.
- Use tenant database connections to retrieve and persist tenant-specific data.
- Tenant logic should sit within the correct domain and not leak into the Central application context.

### Frontend
- Keep Central and Tenant UI components properly separated or abstracted into shared generic components.
- Use the Vue/Inertia version of Nuxt UI for building components.
- Only utilize Tailwind CSS v4 for utility classes.

### Code Quality & Static Analysis
- **PHP**: Use Laravel Pint for formatting and Larastan for static analysis.
- **Frontend / TypeScript**: The frontend application strictly utilizes TypeScript. Use ESLint for linting and Prettier for code formatting.

### Code Organization
- Ensure API and Web routes strictly separate central vs. tenant logic.
- Modules (CMS, CRM, etc.) should be structured clearly to be extensible and maintainable. 

## Planned Features / Roadmap
- Modular CMS for tenant websites to manage their content (like a WordPress environment).
- CRM system for tenants.
- Headless API layer for custom web experiences.
- Central domain platform analytics.

*(Add specific rules, prompt instructions, or architectural decisions here as the project evolves)*
