<script setup lang="ts">
import MockWindow from './MockWindow.vue';

const { trans } = useTrans();

const nav = computed(() => [
    {
        label: trans('marketing.mock.catalogues'),
        icon: 'i-lucide-book-open',
        active: true,
    },
    { label: trans('marketing.mock.tags'), icon: 'i-lucide-tags' },
]);

/** Sample data: a restaurant's lunch menu */
const sections = [
    {
        name: 'Small plates',
        items: [
            {
                name: 'Smoked mackerel pâté',
                price: '£7.50',
                tags: ['Fish'],
                soldOut: false,
                variations: [],
            },
            {
                name: 'Charred hispi cabbage',
                price: '£6.00',
                tags: ['Vegan', 'GF'],
                soldOut: false,
                variations: [],
            },
        ],
    },
    {
        name: 'Mains',
        items: [
            {
                name: 'Pan-roasted hake',
                price: '£16.50',
                tags: ['GF'],
                soldOut: false,
                variations: [],
            },
            {
                name: 'Beef shin pie',
                price: '£15.00',
                tags: ['Gluten'],
                soldOut: true,
                variations: [],
            },
            {
                name: 'Wild mushroom risotto',
                price: 'from £11.00',
                tags: ['Veg'],
                soldOut: false,
                variations: ['Starter', 'Main'],
            },
        ],
    },
];
</script>

<template>
    <MockWindow
        business="The Copper Pan"
        domain="thecopperpan.co.uk"
        title="Lunch"
        :nav="nav"
    >
        <template #actions>
            <span class="text-muted text-xs">Mon–Fri, 12:00–15:00</span>
        </template>

        <div class="space-y-4">
            <section v-for="section in sections" :key="section.name">
                <p class="text-muted mb-2 text-xs font-medium">
                    {{ section.name }}
                </p>
                <ul
                    class="divide-default border-default divide-y rounded-lg border"
                >
                    <li
                        v-for="item in section.items"
                        :key="item.name"
                        class="flex items-center gap-3 px-3 py-2.5 text-sm"
                    >
                        <span class="min-w-0 flex-1">
                            <span
                                class="block truncate font-medium"
                                :class="
                                    item.soldOut
                                        ? 'text-muted line-through'
                                        : 'text-highlighted'
                                "
                            >
                                {{ item.name }}
                            </span>
                            <span class="mt-1 flex flex-wrap gap-1">
                                <span
                                    v-for="tag in item.tags"
                                    :key="tag"
                                    class="border-default text-toned rounded border px-1.5 text-[11px] leading-4"
                                >
                                    {{ tag }}
                                </span>
                                <span
                                    v-for="variation in item.variations"
                                    :key="variation"
                                    class="bg-elevated text-toned rounded px-1.5 text-[11px] leading-4"
                                >
                                    {{ variation }}
                                </span>
                            </span>
                        </span>
                        <UBadge
                            v-if="item.soldOut"
                            color="warning"
                            variant="subtle"
                            size="sm"
                            :label="trans('marketing.mock.sold_out')"
                        />
                        <span
                            class="text-highlighted shrink-0 text-sm tabular-nums"
                        >
                            {{ item.price }}
                        </span>
                    </li>
                </ul>
            </section>
        </div>
    </MockWindow>
</template>
