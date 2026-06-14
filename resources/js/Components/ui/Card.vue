<script setup>
import { cn } from '@/lib/utils'

defineProps({
  class: String,
})
</script>

<template>
  <div :class="cn('rounded-xl border bg-card text-card-foreground shadow', $props.class)">
    <div v-if="$slots.header" :class="cn('flex flex-col space-y-1.5 p-6', $props.classHeader)">
      <slot name="header" />
    </div>
    <div :class="cn('p-6 pt-0', $props.classContent)">
      <slot />
    </div>
    <div v-if="$slots.footer" :class="cn('flex items-center p-6 pt-0', $props.classFooter)">
      <slot name="footer" />
    </div>
  </div>
</template>
