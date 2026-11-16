<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import BookingsMock from '@/central/components/marketing/BookingsMock.vue';
import WebLayout from '@/central/layouts/WebLayout.vue';
import {
    demoIndustries,
    marketingModules,
    moduleIcons,
    useIndustryTerms,
    useTranslationValue,
    type DemoIndustry,
} from '@/lib/marketing';
import { login } from '@/routes/central';
import { dashboard } from '@/routes/central/admin';
import { show as moduleShow } from '@/routes/central/modules';

defineOptions({ layout: WebLayout });

const { trans } = useTrans();
const translationValue = useTranslationValue();
const term = useIndustryTerms();
const auth = useAuth();

const isSignedIn = computed(() => Boolean(auth.value.user));

const demoIndustry = ref<DemoIndustry>('beauty');

const modules = computed(() =>
    marketingModules.map((module) => ({
        key: module,
        name: trans(`marketing.modules.${module}.name`),
        summary: trans(`marketing.modules.${module}.summary`),
        highlights: translationValue<string[]>(
            `marketing.modules.${module}.highlights`,
            [],
        ),
        icon: moduleIcons[module],
        href: moduleShow.url(module),
    })),
);

const bookingTypes = [
    'appointment',
    'class',
    'table',
    'hire',
    'visit',
    'ticket',
    'enquiry',
];

const developerPoints = computed(() =>
    translationValue<string[]>('marketing.home.developers.points', []),
);

const industries = [
    'hospitality',
    'beauty',
    'health',
    'fitness',
    'events',
    'trades',
    'pets',
    'education',
    'rentals',
    'professional',
    'retail',
];

function industryWords(industry: string): string {
    return [
        term(industry, 'customer'),
        term(industry, 'booking'),
        term(industry, 'staff_member'),
    ].join(', ');
}

/** A trimmed real response from the catalogue API */
const apiResponse = `{
  "data": {
    "name": "Lunch",
    "schedule": { "summary": "Mon–Fri, 12:00–15:00" },
    "applies_now": true,
    "sections": [
      {
        "name": "Mains",
        "items": [
          {
            "name": "Pan-roasted hake",
            "price": { "formatted": "£16.50" },
            "sold_out": false,
            "tags": [{ "name": "GF", "kind": "dietary" }]
          }
        ]
      }
    ]
  }
}`;
</script>

<template>
    <div>
        <Head :title="trans('marketing.home.head')" />

        <!-- Hero -->
        <UContainer class="pb-10 pt-16 sm:pt-24">
            <div class="max-w-3xl">
                <h1
                    class="text-highlighted text-balance text-4xl font-semibold tracking-tight sm:text-6xl"
                >
                    {{ trans('marketing.home.title') }}
                </h1>
                <p class="text-muted mt-6 max-w-2xl text-pretty text-lg">
                    {{ trans('marketing.home.description') }}
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <UButton
                        to="#modules"
                        size="lg"
                        trailing-icon="i-lucide-arrow-down"
                        :label="trans('marketing.home.see_modules')"
                    />
                    <UButton
                        as="Link"
                        :href="isSignedIn ? dashboard.url() : login.url()"
                        size="lg"
                        color="neutral"
                        variant="ghost"
                        :label="
                            isSignedIn
                                ? trans('marketing.home.go_to_dashboard')
                                : trans('marketing.home.sign_in')
                        "
                    />
                </div>
            </div>
        </UContainer>

        <!-- The industry demo: the same admin, in each industry's words -->
        <UContainer class="pb-24">
            <div
                class="flex flex-wrap items-center gap-x-3 gap-y-2"
                role="group"
                :aria-label="trans('marketing.home.demo.label')"
            >
                <span class="text-muted text-sm">
                    {{ trans('marketing.home.demo.label') }}
                </span>
                <div class="flex flex-wrap gap-1.5">
                    <UButton
                        v-for="industry in demoIndustries"
                        :key="industry"
                        size="sm"
                        :color="
                            industry === demoIndustry ? 'primary' : 'neutral'
                        "
                        :variant="industry === demoIndustry ? 'soft' : 'ghost'"
                        :aria-pressed="industry === demoIndustry"
                        :label="
                            trans(`marketing.home.demo.examples.${industry}`)
                        "
                        @click="demoIndustry = industry"
                    />
                </div>
            </div>

            <div class="mt-4">
                <BookingsMock :industry="demoIndustry" />
            </div>
            <p class="text-dimmed mt-3 text-sm">
                {{ trans('marketing.home.demo.caption') }}
            </p>
        </UContainer>

        <!-- Modules -->
        <section id="modules" class="border-default scroll-mt-16 border-t">
            <UContainer
                class="grid gap-12 py-20 sm:py-24 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)]"
            >
                <div class="lg:sticky lg:top-28 lg:self-start">
                    <p class="text-primary text-sm font-medium">
                        {{ trans('marketing.home.modules.eyebrow') }}
                    </p>
                    <h2
                        class="text-highlighted mt-3 text-balance text-3xl font-semibold tracking-tight sm:text-4xl"
                    >
                        {{ trans('marketing.home.modules.title') }}
                    </h2>
                    <p class="text-muted mt-4 text-pretty">
                        {{ trans('marketing.home.modules.description') }}
                    </p>
                </div>

                <ul class="divide-default border-default divide-y border-y">
                    <li v-for="module in modules" :key="module.key">
                        <Link :href="module.href" class="group flex gap-5 py-7">
                            <span
                                class="border-default text-toned group-hover:border-primary group-hover:text-primary flex size-10 shrink-0 items-center justify-center rounded-lg border transition-colors"
                            >
                                <UIcon :name="module.icon" class="size-5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="text-highlighted flex items-center gap-2 text-xl font-semibold"
                                >
                                    {{ module.name }}
                                    <UIcon
                                        name="i-lucide-arrow-right"
                                        class="text-dimmed group-hover:text-primary size-4 transition-transform group-hover:translate-x-1"
                                    />
                                </span>
                                <span class="text-muted mt-1 block text-pretty">
                                    {{ module.summary }}
                                </span>
                                <span
                                    class="text-toned mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm"
                                >
                                    <span
                                        v-for="highlight in module.highlights"
                                        :key="highlight"
                                        class="flex items-center gap-1.5"
                                    >
                                        <span
                                            class="bg-primary size-1 rounded-full"
                                        />
                                        {{ highlight }}
                                    </span>
                                </span>
                            </span>
                        </Link>
                    </li>
                </ul>
            </UContainer>
        </section>

        <!-- Booking types -->
        <section class="bg-elevated/40 border-default border-y">
            <UContainer class="py-20 sm:py-24">
                <div class="max-w-2xl">
                    <p class="text-primary text-sm font-medium">
                        {{ trans('marketing.home.booking_types.eyebrow') }}
                    </p>
                    <h2
                        class="text-highlighted mt-3 text-balance text-3xl font-semibold tracking-tight sm:text-4xl"
                    >
                        {{ trans('marketing.home.booking_types.title') }}
                    </h2>
                    <p class="text-muted mt-4 text-pretty">
                        {{ trans('marketing.home.booking_types.description') }}
                    </p>
                </div>

                <dl
                    class="border-default bg-(--ui-border) mt-12 grid gap-px overflow-hidden rounded-xl border sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div
                        v-for="type in bookingTypes"
                        :key="type"
                        class="bg-default p-5"
                    >
                        <dt class="text-highlighted font-semibold">
                            {{
                                trans(
                                    `marketing.home.booking_types.types.${type}.title`,
                                )
                            }}
                        </dt>
                        <dd class="text-muted mt-1.5 text-pretty text-sm">
                            {{
                                trans(
                                    `marketing.home.booking_types.types.${type}.description`,
                                )
                            }}
                        </dd>
                    </div>
                    <Link
                        :href="moduleShow.url('bookings')"
                        class="bg-default text-primary hover:bg-elevated flex items-end justify-between gap-2 p-5 font-medium transition-colors"
                    >
                        {{
                            trans('marketing.home.modules.explore', {
                                module: trans(
                                    'marketing.modules.bookings.name',
                                ),
                            })
                        }}
                        <UIcon name="i-lucide-arrow-right" class="size-4" />
                    </Link>
                </dl>
            </UContainer>
        </section>

        <!-- Developers -->
        <section id="developers" class="scroll-mt-16">
            <UContainer
                class="grid items-center gap-12 py-20 sm:py-24 lg:grid-cols-2"
            >
                <div>
                    <p class="text-primary text-sm font-medium">
                        {{ trans('marketing.home.developers.eyebrow') }}
                    </p>
                    <h2
                        class="text-highlighted mt-3 text-balance text-3xl font-semibold tracking-tight sm:text-4xl"
                    >
                        {{ trans('marketing.home.developers.title') }}
                    </h2>
                    <p class="text-muted mt-4 text-pretty">
                        {{ trans('marketing.home.developers.description') }}
                    </p>
                    <ul class="mt-8 space-y-3">
                        <li
                            v-for="point in developerPoints"
                            :key="point"
                            class="text-toned flex gap-3 text-sm"
                        >
                            <UIcon
                                name="i-lucide-check"
                                class="text-primary mt-0.5 size-4 shrink-0"
                            />
                            {{ point }}
                        </li>
                    </ul>
                </div>

                <div
                    class="overflow-hidden rounded-xl bg-zinc-950 text-sm ring-1 ring-zinc-800"
                >
                    <p
                        class="border-b border-zinc-800 px-4 py-2.5 font-mono text-xs text-zinc-400"
                    >
                        <span class="text-amber-400">GET</span>
                        /api/v1/catalogue/catalogues/lunch
                    </p>
                    <pre
                        class="overflow-x-auto p-4 font-mono text-[13px] leading-relaxed text-zinc-300"
                    ><code>{{ apiResponse }}</code></pre>
                </div>
            </UContainer>
        </section>

        <!-- Industries -->
        <section id="industries" class="border-default scroll-mt-16 border-t">
            <UContainer class="py-20 sm:py-24">
                <div class="max-w-2xl">
                    <p class="text-primary text-sm font-medium">
                        {{ trans('marketing.home.industries.eyebrow') }}
                    </p>
                    <h2
                        class="text-highlighted mt-3 text-balance text-3xl font-semibold tracking-tight sm:text-4xl"
                    >
                        {{ trans('marketing.home.industries.title') }}
                    </h2>
                    <p class="text-muted mt-4 text-pretty">
                        {{ trans('marketing.home.industries.description') }}
                    </p>
                </div>

                <dl
                    class="mt-12 grid gap-x-10 gap-y-8 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="industry in industries"
                        :key="industry"
                        class="border-default border-t pt-4"
                    >
                        <dt class="text-highlighted font-semibold">
                            {{ trans(`industries.names.${industry}`) }}
                        </dt>
                        <dd class="text-muted mt-1 text-pretty text-sm">
                            {{ trans(`industries.descriptions.${industry}`) }}
                        </dd>
                        <dd class="text-dimmed mt-2 text-xs italic">
                            {{ industryWords(industry) }}
                        </dd>
                    </div>
                </dl>
            </UContainer>
        </section>

        <!-- Closing -->
        <UContainer class="pb-24">
            <div
                class="border-default flex flex-col items-start justify-between gap-6 rounded-xl border p-8 sm:flex-row sm:items-center sm:p-10"
            >
                <div class="max-w-xl">
                    <h2 class="text-highlighted text-xl font-semibold">
                        {{ trans('marketing.home.closing.title') }}
                    </h2>
                    <p class="text-muted mt-2 text-pretty text-sm">
                        {{ trans('marketing.home.closing.description') }}
                    </p>
                </div>
                <UButton
                    as="Link"
                    :href="isSignedIn ? dashboard.url() : login.url()"
                    color="neutral"
                    variant="outline"
                    class="shrink-0"
                    :icon="isSignedIn ? undefined : 'i-lucide-log-in'"
                    :label="
                        isSignedIn
                            ? trans('marketing.home.go_to_dashboard')
                            : trans('marketing.home.closing.sign_in')
                    "
                />
            </div>
        </UContainer>
    </div>
</template>
