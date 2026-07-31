<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import { Construction } from 'lucide-vue-next'

defineProps<{
  title: string
  subtitle?: string
  description?: string
}>()
</script>

<template>
  <AppLayout :title="title" :subtitle="subtitle">
    <Card class="flex flex-col items-center justify-center py-20 text-center">
      <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10">
        <Construction class="h-8 w-8 text-primary" />
      </div>
      <h3 class="mt-4 text-lg font-semibold">{{ title }}</h3>
      <p class="mt-2 max-w-md text-sm text-muted">
        {{ description ?? 'Modul ini akan tersedia pada sprint berikutnya. Layout dan navigasi sudah siap.' }}
      </p>
    </Card>
  </AppLayout>
</template>
