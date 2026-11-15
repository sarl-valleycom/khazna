<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const form = useForm({ code: '' });

function submit() {
    form.post('/work/enter', {
        onFinish: () => form.reset('code'),
    });
}
</script>

<template>
    <Head title="Check in" />

    <div class="flex min-h-[70svh] items-center justify-center">
        <Card class="w-full max-w-sm">
            <CardHeader>
                <CardTitle class="text-center text-xl">
                    Enter your code
                </CardTitle>
                <p class="text-center text-sm text-muted-foreground">
                    The 6 digit code the owner gave you.
                </p>
            </CardHeader>
            <CardContent>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-2">
                        <Label for="code" class="sr-only">Code</Label>
                        <Input
                            id="code"
                            v-model="form.code"
                            v-focus
                            type="password"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="12"
                            placeholder="------"
                            class="h-14 text-center text-2xl tracking-[0.5em]"
                        />
                        <InputError :message="form.errors.code" />
                    </div>
                    <Button
                        type="submit"
                        class="h-12 w-full text-base"
                        :disabled="form.processing || form.code.length < 6"
                    >
                        Continue
                    </Button>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
