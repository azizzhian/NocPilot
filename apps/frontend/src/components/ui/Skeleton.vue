<script setup lang="ts">
import { cn } from '@/lib/utils'

interface Props {
  class?: string
}

const props = defineProps<Props>()
</script>

<template>
  <div :class="cn('animate-pulse rounded-[18px] bg-slate-200 dark:bg-slate-700', props.class)" />
</template>
