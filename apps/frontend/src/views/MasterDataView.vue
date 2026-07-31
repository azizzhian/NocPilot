<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { masterDataApi } from '@/services/api'
import { formatNumber } from '@/lib/utils'
import { Database } from 'lucide-vue-next'

const loading = ref(true)
const summary = ref<{ key: string; label: string; count: number }[]>([])
const links = ref<{ label: string; to: string }[]>([])

onMounted(async () => {
  try {
    const { data } = await masterDataApi.index()
    summary.value = data.summary
    links.value = data.links
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <AppLayout title="Master Data" subtitle="Data referensi sistem">
    <Skeleton v-if="loading" class="h-64 rounded-[18px]" />

    <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <Card v-for="item in summary" :key="item.key" padding="sm" class="hover:shadow-md transition">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-2xl font-bold text-primary">{{ formatNumber(item.count) }}</p>
            <p class="text-sm text-muted">{{ item.label }}</p>
          </div>
          <Database class="h-8 w-8 text-muted opacity-30" />
        </div>
      </Card>
    </div>

    <Card class="mt-6 p-6">
      <h3 class="mb-4 text-sm font-semibold text-foreground">Kelola Master Data</h3>
      <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <RouterLink
          v-for="link in links"
          :key="link.to"
          :to="link.to"
          class="rounded-xl border border-border px-4 py-3 text-sm font-medium text-foreground transition hover:border-primary hover:bg-primary/5"
        >
          {{ link.label }}
        </RouterLink>
      </div>
    </Card>
  </AppLayout>
</template>
