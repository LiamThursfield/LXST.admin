# Factories and Seeders

Any tenant can be reset to a realistic dataset to test every feature (#164):

```bash
php artisan tenants:refresh {tenant} --seed
```

Sign in afterwards as one of the role users in `Database\Seeders\RoleUserSeeder`, e.g. `admin@example.com`.

- Every model has a factory **and** is covered by a seeder. A task that adds or changes a model, a column, a status or a feature must update the factory (with states for the cases the feature relies on) and its module's seeder **in the same PR**.
- Module seeders are called from `Database\Seeders\TenantDatabaseSeeder`, and only when the tenant has the module.
- Seeders must be **safe to re-run**: skip data that already exists, or update it in place. The seeder test runs `TenantDatabaseSeeder` twice and fails if the second run creates anything.
- Seeders give the **same data every run** (a fixed random seed), and set times **relative to now**, so time-based features (a gig on now, requests open, now playing) always have something to show.
- Seed through the real services where they exist (e.g. the library importer, the canonical matcher), so seeded data looks like production data.
- Seeded credentials are local test accounts only. Never seed real people's details or real credentials.
- List any factory or seeder changes in the PR description.
