<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import PageHeader from '@/components/kit/PageHeader.vue';

const props = defineProps<{ page: App.Data.SettingsPageData }>();

const form = useForm({
    week_start: props.page.weekStart,
    reminder_template: props.page.reminderTemplate,
    currency_label: props.page.currencyLabel,
    salary_due_day: props.page.salaryDueDay,
});

function submit() {
    form.patch('/settings', { preserveScroll: true });
}
</script>

<template>
    <Head title="Settings" />
    <AppLayout :breadcrumbs="[{ title: 'Settings', href: '/settings' }]">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <PageHeader
                title="Settings"
                description="Company-wide defaults. Changes are written to the audit log."
            />

            <form
                class="max-w-2xl space-y-5 rounded-xl border bg-card p-5"
                @submit.prevent="submit"
            >
                <div class="space-y-2">
                    <Label>Week starts on</Label>
                    <Select v-model="form.week_start">
                        <SelectTrigger class="max-w-48">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="(label, value) in props.page
                                    .weekStartOptions"
                                :key="value"
                                :value="value"
                            >
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        Used by the triage "This week" tab.
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="currency-label">Currency label</Label>
                    <Input
                        id="currency-label"
                        v-model="form.currency_label"
                        maxlength="10"
                    />
                </div>

                <div class="space-y-2">
                    <Label for="salary-due-day">Salary due day</Label>
                    <Input
                        id="salary-due-day"
                        v-model.number="form.salary_due_day"
                        type="number"
                        min="1"
                        max="28"
                    />
                    <p class="text-xs text-muted-foreground">
                        Stored now; used when month-close arrives in phase 2.
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="reminder-template">WhatsApp reminder</Label>
                    <textarea
                        id="reminder-template"
                        v-model="form.reminder_template"
                        rows="4"
                        class="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <p class="text-xs text-muted-foreground">
                        Placeholders: {client}, {amount}, {contract}, {date}.
                    </p>
                    <p
                        v-if="form.errors.reminder_template"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.reminder_template }}
                    </p>
                </div>

                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : 'Save settings' }}
                </Button>
            </form>
        </div>
    </AppLayout>
</template>
