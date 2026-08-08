<script setup lang="ts">
import { watch, onUnmounted } from 'vue'
import { cn } from '@/lib/utils'
import { X } from 'lucide-vue-next'

const props = defineProps<{
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

function lockBody(lock: boolean) {
  document.body.style.overflow = lock ? 'hidden' : ''
}

watch(
  () => props.open,
  (open) => lockBody(open),
  { immediate: true },
)

onUnmounted(() => lockBody(false))
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="open"
        class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm overscroll-none"
        @click="emit('close')"
        @wheel.prevent
        @touchmove.prevent
      />
    </Transition>
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="open"
        :class="cn(
          'fixed left-1/2 top-1/2 z-[51] flex max-h-[90vh] w-[calc(100%-1.5rem)] -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden rounded-[18px] border border-border bg-card card-shadow-hover',
          sizeClass[size ?? 'md'],
        )"
        role="dialog"
        aria-modal="true"
        @click.stop
      >
        <div class="flex shrink-0 items-start justify-between border-b border-border px-5 py-4 sm:p-6">
          <div class="min-w-0 pr-3">
            <h2 class="text-lg font-semibold">{{ title }}</h2>
            <p v-if="subtitle" class="mt-1 text-sm text-muted">{{ subtitle }}</p>
          </div>
          <button
            type="button"
            class="shrink-0 rounded-lg p-1 text-muted hover:bg-slate-100 dark:hover:bg-slate-800"
            @click="emit('close')"
          >
            <X class="h-5 w-5" />
          </button>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-4 sm:p-6">
          <slot />
        </div>
        <div
          v-if="$slots.footer"
          class="flex shrink-0 justify-end gap-2 border-t border-border p-4"
        >
          <slot name="footer" />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
