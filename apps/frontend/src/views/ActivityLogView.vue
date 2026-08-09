<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import SearchInput from '@/components/ui/SearchInput.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { activityApi, type ActivityLogItem } from '@/services/api'
import {
  Download,
  LogIn,
  Settings,
  User,
  Router,
  HardDriveDownload,
  Ticket,
  AlertTriangle,
  Zap,
  Trash2,
  Radio,
  Shield,
} from 'lucide-vue-next'

const route = useRoute()
const search = ref('')
const logs = ref<ActivityLogItem[]>([])
const loading = ref(true)
const exporting = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)

const scope = computed<'audit' | 'activity'>(() =>
  route.meta.logScope === 'audit' ? 'audit' : 'activity',
)

const pageTitle = computed(() => (scope.value === 'audit' ? 'Audit Log' : 'Activity Log'))
const pageSubtitle = computed(() =>
  scope.value === 'audit'
    ? 'Login, kelola user, role, dan pengaturan'
    : 'Tambah, edit, dan hapus data operasional',
)

const typeIcons: Record<string, typeof LogIn> = {
  login: LogIn,
  config: Settings,
  settings: Settings,
  user: User,
  role: Shield,
  customer: User,
  backup: HardDriveDownload,
  router: Router,
  ticket: Ticket,
  complaint: AlertTriangle,
  activation: Zap,
  dismantle: Trash2,
  cctv: Radio,
  noc: Settings,
  network: Router,
  report: HardDriveDownload,
}

const typeVariant = (t: string) => {
  const map: Record<string, 'info' | 'warning' | 'success' | 'secondary' | 'danger'> = {
    login: 'info',
    config: 'warning',
    settings: 'warning',
    user: 'danger',
    role: 'danger',
    customer: 'secondary',
    backup: 'success',
    router: 'info',
    ticket: 'warning',
    complaint: 'danger',
    activation: 'success',
    dismantle: 'warning',
    cctv: 'info',
    noc: 'secondary',
  }
  return map[t] ?? 'secondary'
}

function formatTime(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('id-ID', { hour: '2-digit', minute: '2-digit', day: 'numeric', month: 'short' })
}

async function fetchLogs(page = currentPage.value) {
  loading.value = true
  try {
    const { data } = await activityApi.list({
      scope: scope.value,
      search: search.value || undefined,
      page,
      per_page: 50,
    })
    logs.value = data.data
    const meta = data.meta
    currentPage.value = meta?.current_page ?? page
    lastPage.value = meta?.last_page ?? 1
  } finally {
    loading.value = false
  }
}

async function handleExport() {
  exporting.value = true
  try {
    await activityApi.export({ scope: scope.value })
  } finally {
    exporting.value = false
  }
}

let debounce: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(debounce)
  debounce = setTimeout(() => fetchLogs(1), 300)
})

watch(scope, () => {
  search.value = ''
  currentPage.value = 1
  void fetchLogs(1)
})

onMounted(() => fetchLogs(1))
</script>

<template>
  <AppLayout :title="pageTitle" :subtitle="pageSubtitle">
    <div class="mb-6 flex flex-wrap items-center gap-3">
      <SearchInput
        v-model="search"
        :placeholder="scope === 'audit' ? 'Cari login, user, role...' : 'Cari tambah, edit, hapus...'"
        class="max-w-sm"
      />
      <Button variant="outline" size="sm" :disabled="exporting" @click="handleExport">
        <Download class="h-4 w-4" /> Export CSV
      </Button>
    </div>

    <Card class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-border text-left text-xs text-muted">
            <th class="pb-3 pr-4 font-medium">Tipe</th>
            <th class="pb-3 pr-4 font-medium">User</th>
            <th class="pb-3 pr-4 font-medium">Aktivitas</th>
            <th class="pb-3 pr-4 font-medium">IP Address</th>
            <th class="pb-3 pr-4 font-medium">Browser</th>
            <th class="pb-3 pr-4 font-medium">Device</th>
            <th class="pb-3 font-medium">Waktu</th>
          </tr>
        </thead>
        <tbody v-if="loading">
          <tr v-for="i in 5" :key="i">
            <td colspan="7" class="py-3"><Skeleton class="h-8 w-full" /></td>
          </tr>
        </tbody>
        <tbody v-else-if="logs.length">
          <tr
            v-for="log in logs"
            :key="log.id"
            class="border-b border-border/50 hover:bg-slate-50 dark:hover:bg-slate-800/50"
          >
            <td class="py-3 pr-4">
              <div class="flex items-center gap-2">
                <component :is="typeIcons[log.type] ?? Settings" class="h-4 w-4 text-muted" />
                <Badge :variant="typeVariant(log.type)" class="capitalize">{{ log.type }}</Badge>
              </div>
            </td>
            <td class="py-3 pr-4 font-medium">{{ log.user }}</td>
            <td class="py-3 pr-4">{{ log.action }}</td>
            <td class="py-3 pr-4 font-mono text-xs text-muted">{{ log.ip_address ?? '—' }}</td>
            <td class="py-3 pr-4 text-muted">{{ log.browser ?? '—' }}</td>
            <td class="py-3 pr-4 text-muted">{{ log.device ?? '—' }}</td>
            <td class="py-3 text-xs text-muted">{{ formatTime(log.created_at) }}</td>
          </tr>
        </tbody>
        <tbody v-else>
          <tr>
            <td colspan="7" class="py-8 text-center text-muted">
              {{ scope === 'audit'
                ? 'Belum ada audit log (login / user / role).'
                : 'Belum ada activity log. Coba tambah/edit/hapus data operasional.' }}
            </td>
          </tr>
        </tbody>
      </table>
    </Card>

    <div v-if="lastPage > 1" class="mt-4 flex justify-center gap-2">
      <Button variant="outline" size="sm" :disabled="currentPage <= 1 || loading" @click="fetchLogs(currentPage - 1)">
        Sebelumnya
      </Button>
      <span class="flex items-center px-3 text-sm text-muted">{{ currentPage }} / {{ lastPage }}</span>
      <Button variant="outline" size="sm" :disabled="currentPage >= lastPage || loading" @click="fetchLogs(currentPage + 1)">
        Selanjutnya
      </Button>
    </div>
  </AppLayout>
</template>
