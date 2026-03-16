/**
 * This is taken from: https://github.com/jkque/laravel-nuxt-ui-starter-kit/
 */
export function useAuth() {
    return computed(() => usePage().props.auth);
}
