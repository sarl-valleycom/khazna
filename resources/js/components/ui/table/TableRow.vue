<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "cn"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <tr
    data-slot="table-row"
    :class="cn('hover:bg-accent/50 data-[state=selected]:bg-muted border-b last:border-b-0 transition-colors', props.class)"
  >
    <slot />
  </tr>
</template>
