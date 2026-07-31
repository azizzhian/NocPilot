<script setup lang="ts">
import { cn } from '@/lib/utils'

interface Props {
  class?: string
  hover?: boolean
  padding?: 'sm' | 'md' | 'lg'
}

const props = withDefaults(defineProps<Props>(), {
  hover: false,
  padding: 'md',
})

const paddingClass = {
  sm: 'p-4',
  md: 'p-6',
  lg: 'p-8',
}
</script>

<template>
  <div
    :class="cn(
      'bg-card rounded-[18px] border border-border card-shadow transition-all duration-200',
      paddingClass[padding],
      hover && 'hover:card-shadow-hover hover:-translate-y-0.5 cursor-pointer',
      props.class,
    )"
  >
    <slot />
  </div>
</template>
