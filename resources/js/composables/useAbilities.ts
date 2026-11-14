import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useAbilities() {
    const page = usePage();

    const abilities = computed<Record<string, boolean>>(
        () => page.props.auth?.user?.abilities ?? {},
    );

    const can = (ability: string): boolean => abilities.value[ability] === true;

    return { abilities, can };
}
