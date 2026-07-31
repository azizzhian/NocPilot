<script setup lang="ts">
import { cn } from '@/lib/utils'
import { X } from 'lucide-vue-next'

defineProps<{
  open: boolean
  title: string
  subtitle?: string
  size?: 'sm' | 'md' | 'lg'
}>()

const emit = defineEmits<{ close: [] }>()

const sizeClass = {
  sm: 'max-w-md',
  md: 'max-w-lg',
  lg: 'max-w-2xl',
}
</script>

<template>
  <Teleport to="body">
    <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
      leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
      <div v-if="open" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm" @click="emit('close')" />
    </Transition>
    <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 scale-95 translate-y-4"
      enter-to-class="opacity-100 scale-100 translate-y-0" leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
      <div v-if="open" :class="cn('fixed left-1/2 top-1/2 z-[51] w-full -translate-x-1/2 -translate-y-1/2 rounded-[18px] border border-border bg-card card-shadow-hover', sizeClass[size ?? 'md'])">
        <div class="flex items-start justify-between border-b border-border p-6">
          <div>
            <h2 class="text-lg font-semibold">{{ title }}</h2>
            <p v-if="subtitle" class="mt-1 text-sm text-muted">{{ subtitle }}</p>
          </div>
          <button class="rounded-lg p-1 text-muted hover:bg-slate-100 dark:hover:bg-slate-800" @click="emit('close')">
            <X class="h-5 w-5" />
          </button>
        </div>
        <div class="p-6">
          <slot />
        </div>
        <div v-if="$slots.footer" class="border-t border-border p-4 flex justify-end gap-2">
          <slot name="footer" />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
