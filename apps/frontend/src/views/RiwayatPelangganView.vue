<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import SearchInput from '@/components/ui/SearchInput.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { customerHistoryApi, type CustomerHistoryItem } from '@/services/api'

const search = ref('')
const loading = ref(true)
const items = ref<CustomerHistoryItem[]>([])

async function load() {
  loading.value = true
  try {
    const { data } = await customerHistoryApi.list({ search: search.value || undefined })
    items.value = data.data
  } finally {
    loading.value = false
  }
}

function formatDate(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('id-ID')
}

const eventVariant = (type: string) => {
  const map: Record<string, 'success' | 'warning' | 'danger' | 'info' | 'secondary'> = {
    activation: 'success', dismantle: 'warning', ticket: 'danger', activity: 'info',
  }
  return map[type] ?? 'secondary'
}

let searchTimeout: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(load, 400)
})

onMounted(load)
</script>

<template>
  <AppLayout title="Riwayat Pelanggan" subtitle="Histori perubahan data pelanggan">
    <div class="mb-6">
      <SearchInput v-model="search" placeholder="Cari nama atau kode pelanggan..." class="max-w-sm" />
    </div>

    <Skeleton v-if="loading" class="h-96 rounded-[18px]" />

    <div v-else class="space-y-4">
      <Card v-for="item in items" :key="item.id" class="p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 class="font-semibold text-foreground">{{ item.name }}</h3>
            <p class="text-sm text-primary font-mono">{{ item.customer_code }}</p>
            <p class="mt-1 text-xs text-muted">{{ item.package }} · {{ item.status }}</p>
          </div>
          <Badge :variant="item.status === 'active' ? 'success' : item.status === 'suspended' ? 'danger' : 'secondary'">
            {{ item.status }}
          </Badge>
        </div>
        <div v-if="item.events.length" class="mt-4 space-y-2 border-t border-border pt-4">
          <div v-for="(ev, idx) in item.events" :key="idx" class="flex items-start gap-3 text-sm">
            <Badge :variant="eventVariant(ev.type)" class="shrink-0 capitalize">{{ ev.type }}</Badge>
            <div>
              <p class="text-foreground">{{ ev.title }}</p>
              <p class="text-xs text-muted">{{ formatDate(ev.date) }}</p>
            </div>
          </div>
        </div>
        <p v-else class="mt-4 text-sm text-muted">Belum ada riwayat aktivitas.</p>
      </Card>
      <Card v-if="!items.length" class="py-16 text-center text-muted">Tidak ada data pelanggan.</Card>
    </div>
  </AppLayout>
</template>
