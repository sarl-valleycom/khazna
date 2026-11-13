<script setup lang="ts">
defineProps<{
    text: string | null | undefined;
}>();
</script>

<template>
    <bdi dir="auto">{{ text }}</bdi>
</template>
