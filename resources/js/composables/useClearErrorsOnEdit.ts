import { watch } from 'vue';

type ErrorForm = {
    data: () => Record<string, unknown>;
    errors: Record<string, unknown>;
    clearErrors: (...fields: never[]) => void;
};

/**
 * Drops a field's server error as soon as that field changes, so a message about
 * something the user has already fixed does not stay on screen until the next submit.
 */
export function useClearErrorsOnEdit(form: ErrorForm): void {
    watch(
        () => ({ ...form.data() }),
        (next, previous) => {
            for (const key of Object.keys(next)) {
                if (next[key] !== previous[key] && key in form.errors) {
                    (form.clearErrors as (...fields: string[]) => void)(key);
                }
            }
        },
    );
}
