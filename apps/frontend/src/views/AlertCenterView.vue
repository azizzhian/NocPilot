<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { alertApi } from '@/services/api'
import { Bell, AlertTriangle } from 'lucide-vue-next'

type AlertIssue = {
  id: string
  name: string
  condition: string
  status: string
  triggered: number
  severity: string
}

type AlertEvent = {
  id: number
  title: string
  message: string | null
  severity: string
  created_at: string | null
}

const loading = ref(true)
const issues = ref<AlertIssue[]>([])
const events = ref<AlertEvent[]>([])
const counts = ref({ router_offline: 0, high_cpu: 0, onu_problem: 0 })

const severityVariant = (s: string) => {
  const map: Record<string, 'danger' | 'warning' | 'info' | 'secondary'> = {
    critical: 'danger', warning: 'warning', info: 'info',
  }
  return map[s] ?? 'secondary'
}

function formatTime(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('id-ID')
}

onMounted(async () => {
  try {
    const { data } = await alertApi.index()
    issues.value = data.issues
    events.value = data.events
    counts.value = data.counts
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <AppLayout title="Alert Center" subtitle="Masalah jaringan aktif dan event realtime">
    <div v-if="loading" class="grid gap-4 md:grid-cols-3">
      <Skeleton v-for="i in 3" :key="i" class="h-24" />
    </div>
    <div v-else class="mb-6 grid grid-cols-3 gap-4">
      <Card padding="sm">
        <p class="text-2xl font-bold text-danger">{{ counts.router_offline }}</p>
        <p class="text-xs text-muted">Router Offline</p>
      </Card>
      <Card padding="sm">
        <p class="text-2xl font-bold text-warning">{{ counts.high_cpu }}</p>
        <p class="text-xs text-muted">CPU Tinggi</p>
      </Card>
      <Card padding="sm">
        <p class="text-2xl font-bold text-warning">{{ counts.onu_problem }}</p>
        <p class="text-xs text-muted">ONU Bermasalah</p>
      </Card>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="lg:col-span-2">
        <Card>
          <div class="mb-4 flex items-center gap-2">
            <AlertTriangle class="h-4 w-4 text-danger" />
            <h3 class="text-sm font-semibold">Masalah Aktif</h3>
            <Badge variant="danger">{{ issues.length }}</Badge>
          </div>
          <div v-if="issues.length" class="space-y-2">
            <div
              v-for="issue in issues"
              :key="issue.id"
              class="flex items-center justify-between rounded-xl border border-border p-3 transition-colors hover:bg-muted/30"
            >
              <div>
                <p class="text-sm font-medium">{{ issue.name }}</p>
                <p class="text-xs text-muted">{{ issue.condition }}</p>
              </div>
              <div class="flex items-center gap-3">
                <Badge v-if="issue.triggered > 0" variant="danger">{{ issue.triggered }}</Badge>
                <Badge :variant="severityVariant(issue.severity)">{{ issue.severity }}</Badge>
              </div>
            </div>
          </div>
          <p v-else class="py-8 text-center text-sm text-muted">Tidak ada masalah aktif.</p>
        </Card>
      </div>

      <Card>
        <div class="mb-4 flex items-center gap-2">
          <Bell class="h-4 w-4 text-primary" />
          <h3 class="text-sm font-semibold">Event Terbaru</h3>
        </div>
        <div v-if="events.length" class="max-h-[500px] space-y-3 overflow-y-auto">
          <div
            v-for="event in events"
            :key="event.id"
            class="rounded-xl bg-muted/20 p-3"
          >
            <div class="flex items-start justify-between gap-2">
              <p class="text-sm font-medium">{{ event.title }}</p>
              <Badge :variant="severityVariant(event.severity)" class="shrink-0 text-[10px]">{{ event.severity }}</Badge>
            </div>
            <p v-if="event.message" class="mt-1 text-xs text-muted">{{ event.message }}</p>
            <p class="mt-2 text-[10px] text-muted">{{ formatTime(event.created_at) }}</p>
          </div>
        </div>
        <p v-else class="py-8 text-center text-sm text-muted">Belum ada event.</p>
      </Card>
    </div>
  </AppLayout>
</template>
