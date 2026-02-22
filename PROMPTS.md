# AI Prompts & Workflow Templates

This repository includes custom architecture definitions (found in `README.md` and `AI.md`) and ecosystem guardrails (found in `GEMINI.md`).

When starting a new session with an AI assistant (specifically **Antigravity** or **PhpStorm Junie**), use these templates to get the best, most context-aware code generation.

---

### 1. General Feature Development (Central)
Use this when you are building a feature specifically for the administrative/central app.

> **Prompt:**
> "Please read `@AI.md` for our platform architecture. I need to build a new feature for the **Central** app. We are using Vue 3, Inertia v2, and Nuxt UI.
> 
> Task: [Describe your feature, e.g., 'Create a Dashboard component that lists all active tenants and their subscription status.']
> 
> Please ensure this relies on the central database connection and uses the components from the Central UI structure."

---

### 2. General Feature Development (Tenant)
Use this when you are building a feature that exists inside a tenant's modular instance (CMS/CRM).

> **Prompt:**
> "Please read `@AI.md` to understand our strict multitenancy structure. I need to build a new feature for the **Tenant** app. We are using Vue 3, Inertia v2, and Nuxt UI.
> 
> Task: [Describe your feature, e.g., 'Create a CRM Contacts page where a tenant can add and edit their customers.']
> 
> Ensure any queries use the tenant connection, and DO NOT let central domain logic leak into this implementation."

---

### 3. Database & Model Scaffolding
Use this when you need to generate a new model, factory, seeder, and migration.

> **Prompt:**
> "Please review our context in `@AI.md` and `@GEMINI.md`. I need to scaffold a new model for the [Central / Tenant] app called **[ModelName]**.
> 
> Fields required:
> - [Field 1] (type)
> - [Field 2] (type)
> 
> Please run the necessary Sail artisan commands to generate the Model, Migration, Factory, and Seeder. Ensure you apply the correct code quality standards (strict types, Larastan rules) and, if it's a tenant model, ensure it extends our base Tenant model or uses the tenancy trait."

---

### 4. Code Refactoring or Bug Fixing
Use this when you need help debugging an error or refactoring code to match rules.

> **Prompt:**
> "I have an issue in `[FilePath]`. According to our rules in `@GEMINI.md` and `@AI.md`, we use strictly TypeScript, Vue 3, and Inertia v2.
> 
> Here is the error/issue:
> [Paste Error or Describe the Issue]
> 
> Please fix this ensuring it passes our ESLint, Prettier, and Larastan checks."
