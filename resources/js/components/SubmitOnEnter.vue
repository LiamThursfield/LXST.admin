<script setup lang="ts">
/**
 * A hidden submit button, so Enter in a field submits the form it's in when the visible Save
 * button is outside the form (e.g. in the editor layout's navbar).
 */
</script>

<template>
    <button type="submit" class="sr-only" aria-hidden="true" tabindex="-1" />
</template>
