<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import SearchInput from '@/components/ui/SearchInput.vue'
import Select from '@/components/ui/Select.vue'
import Modal from '@/components/ui/Modal.vue'
import {
  monitoringApi,
  type RouterDevice,
  type RouterInterfaceItem,
  type MonitoringSummary,
  type InterfaceTrafficLive,
} from '@/services/api'
import { cn } from '@/lib/utils'
import { Router, RefreshCw, Network, Settings2 } from 'lucide-vue-next'

const search = ref('')
const selectedPop = ref('all')
const routers = ref<RouterDevice[]>([])
const pops = ref<string[]>([])
const selectedRouter = ref<RouterDevice | null>(null)
const localInterfaces = ref<RouterInterfaceItem[]>([])
const liveTraffic = ref<InterfaceTrafficLive[]>([])
/** Traffic dari collector snapshot (DB), per router. */
const listTraffic = ref<Record<number, InterfaceTrafficLive[]>>({})
const summary = ref<MonitoringSummary | null>(null)
const syncing = ref(false)
const syncingInterfaces = ref(false)
const autoRefreshing = ref(false)
const snapshotPolling = ref(false)
const loading = ref(true)
const interfaceMessage = ref('')
const lastRefreshedAt = ref<Date | null>(null)
const lastSnapshotAt = ref<Date | null>(null)
const interfaceModalOpen = ref(false)

/** Status/CPU dari DB. */
const DASHBOARD_REFRESH_MS = 30_000
/** Traffic snapshot ringan dari DB (ala Zabbix jsrpc). */
const SNAPSHOT_MS = 5_000

const filteredRouters = computed(() => routers.value)

const monitoredInterfaces = computed(() =>
  localInterfaces.value.filter((i) => i.is_monitored),
)

const hidePppoe = ref(true)
const interfaceFilter = ref('')

const visibleInterfaces = computed(() => {
  const q = interfaceFilter.value.trim().toLowerCase()
  return localInterfaces.value.filter((iface) => {
    const name = iface.interface_name.toLowerCase()
    if (hidePppoe.value && (name.includes('pppoe') || name.startsWith('<pppoe'))) {
      return iface.is_monitored
    }
    if (q && !name.includes(q) && !(iface.label ?? '').toLowerCase().includes(q)) {
      return false
    }
    return true
  })
})

function syncLocalInterfaces(router: RouterDevice | null) {
  localInterfaces.value = router?.interfaces
    ? router.interfaces.map((i) => ({ ...i }))
    : []
}

function formatMbps(value: number) {
  if (!Number.isFinite(value)) return '0'
  if (value >= 100) return value.toFixed(0)
  if (value >= 10) return value.toFixed(1)
  return value.toFixed(2)
}

function shortIfaceName(name: string) {
  return name.length > 28 ? `${name.slice(0, 26)}…` : name
}

function seedListTrafficFromRouters(rows: RouterDevice[]) {
  const next: Record<number, InterfaceTrafficLive[]> = { ...listTraffic.value }
  for (const router of rows) {
    const monitored = (router.interfaces ?? []).filter((i) => i.is_monitored)
    if (!monitored.length) continue
    next[router.id] = monitored.map((i) => ({
      interface_name: i.interface_name,
      label: i.display_name || i.label || i.interface_name,
      rx_bps: i.rx_bps ?? 0,
      tx_bps: i.tx_bps ?? 0,
      rx_mbps: i.rx_mbps ?? 0,
      tx_mbps: i.tx_mbps ?? 0,
      is_running: i.is_running,
    }))
  }
  listTraffic.value = next
}

function routerListIfaces(router: RouterDevice): InterfaceTrafficLive[] {
  const live = listTraffic.value[router.id]
  if (live?.length) return live

  return (router.interfaces ?? [])
    .filter((i) => i.is_monitored)
    .map((i) => ({
      interface_name: i.interface_name,
      label: i.display_name || i.label || i.interface_name,
      rx_bps: i.rx_bps ?? 0,
      tx_bps: i.tx_bps ?? 0,
      rx_mbps: i.rx_mbps ?? 0,
      tx_mbps: i.tx_mbps ?? 0,
      is_running: i.is_running,
    }))
}

function applySnapshot(routersMap: Record<string, InterfaceTrafficLive[]>) {
  const next: Record<number, InterfaceTrafficLive[]> = { ...listTraffic.value }
  for (const [id, rows] of Object.entries(routersMap)) {
    next[Number(id)] = rows
  }
  listTraffic.value = next

  if (selectedRouter.value) {
    const rows = next[selectedRouter.value.id]
    if (rows) liveTraffic.value = rows
  }
}

async function loadData() {
  try {
    const [summaryRes, routersRes, popsRes] = await Promise.all([
      monitoringApi.summary(),
      monitoringApi.routers({
        search: search.value || undefined,
        pop: selectedPop.value !== 'all' ? selectedPop.value : undefined,
      }),
      monitoringApi.pops(),
    ])
    summary.value = summaryRes.data
    routers.value = routersRes.data.data
    pops.value = popsRes.data.data
    seedListTrafficFromRouters(routers.value)
    if (selectedRouter.value) {
      selectedRouter.value = routers.value.find((r) => r.id === selectedRouter.value?.id) ?? null
      syncLocalInterfaces(selectedRouter.value)
      if (selectedRouter.value) {
        liveTraffic.value = listTraffic.value[selectedRouter.value.id] ?? []
      }
    }
  } finally {
    loading.value = false
  }
}

/** Request kecil: baca traffic dari DB collector, tanpa SNMP. */
async function loadTrafficSnapshot() {
  if (snapshotPolling.value) return
  snapshotPolling.value = true
  try {
    const res = await monitoringApi.trafficSnapshot()
    applySnapshot(res.data.routers)
    lastSnapshotAt.value = new Date()
  } catch {
    // biarkan interval berikutnya
  } finally {
    snapshotPolling.value = false
  }
}

async function ensureInterfacesForSelected(silent = true) {
  if (!selectedRouter.value || selectedRouter.value.status !== 'online') return
  if (localInterfaces.value.length > 0 || syncingInterfaces.value) return
  await handleSyncInterfaces(silent)
}

async function refreshDashboard(options: { syncRouters?: boolean; silent?: boolean } = {}) {
  const { syncRouters = true, silent = true } = options
  if (syncRouters) {
    if (silent) autoRefreshing.value = true
    else syncing.value = true
  } else if (silent) {
    autoRefreshing.value = true
  }
  try {
    if (syncRouters) {
      await monitoringApi.syncAll()
    }
    await loadData()
    await loadTrafficSnapshot()
    if (interfaceModalOpen.value) {
      await ensureInterfacesForSelected(silent)
    }
    lastRefreshedAt.value = new Date()
  } finally {
    syncing.value = false
    autoRefreshing.value = false
  }
}

async function handleSyncAll() {
  loading.value = true
  await refreshDashboard({ syncRouters: true, silent: false })
  loading.value = false
}

async function openInterfaceModal(router: RouterDevice) {
  selectedRouter.value = router
  syncLocalInterfaces(router)
  interfaceMessage.value = ''
  interfaceFilter.value = ''
  interfaceModalOpen.value = true
  liveTraffic.value = listTraffic.value[router.id] ?? []
  await ensureInterfacesForSelected(false)
}

function closeInterfaceModal() {
  interfaceModalOpen.value = false
}

async function handleSyncInterfaces(silent = false) {
  if (!selectedRouter.value) return
  syncingInterfaces.value = true
  interfaceMessage.value = ''
  try {
    const res = await monitoringApi.syncInterfaces(selectedRouter.value.id)
    if (!silent) {
      interfaceMessage.value = res.data.message
    }
    selectedRouter.value = {
      ...selectedRouter.value,
      interfaces: res.data.data,
    }
    syncLocalInterfaces(selectedRouter.value)
    const idx = routers.value.findIndex((r) => r.id === selectedRouter.value?.id)
    if (idx >= 0 && selectedRouter.value) {
      routers.value[idx] = { ...routers.value[idx], interfaces: res.data.data }
    }
  } catch (e: unknown) {
    if (!silent) {
      const err = e as { response?: { data?: { message?: string } } }
      interfaceMessage.value = err.response?.data?.message ?? 'Gagal sync interface.'
    }
  } finally {
    syncingInterfaces.value = false
  }
}

async function toggleMonitor(iface: RouterInterfaceItem) {
  if (!selectedRouter.value) return
  const next = !iface.is_monitored
  try {
    const res = await monitoringApi.updateInterface(selectedRouter.value.id, iface.id, {
      is_monitored: next,
    })
    const updated = res.data.data
    localInterfaces.value = localInterfaces.value.map((i) =>
      i.id === updated.id ? updated : i,
    )
    if (selectedRouter.value.interfaces) {
      selectedRouter.value.interfaces = localInterfaces.value
    }
    const idx = routers.value.findIndex((r) => r.id === selectedRouter.value?.id)
    if (idx >= 0) {
      routers.value[idx] = {
        ...routers.value[idx],
        interfaces: localInterfaces.value,
      }
    }
    interfaceMessage.value = res.data.message
    await loadTrafficSnapshot()
  } catch {
    interfaceMessage.value = 'Gagal update interface.'
  }
}

function formatRefreshTime(date: Date | null) {
  if (!date) return '-'
  return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

let searchTimeout: ReturnType<typeof setTimeout>
let dashboardTimer: ReturnType<typeof setInterval>
let snapshotTimer: ReturnType<typeof setInterval>

watch([search, selectedPop], () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(async () => {
    await loadData()
    await loadTrafficSnapshot()
  }, 400)
})

onMounted(async () => {
  loading.value = true
  await loadData()
  await loadTrafficSnapshot()
  lastRefreshedAt.value = new Date()
  loading.value = false

  dashboardTimer = setInterval(() => {
    void refreshDashboard({ syncRouters: false, silent: true })
  }, DASHBOARD_REFRESH_MS)

  snapshotTimer = setInterval(() => {
    void loadTrafficSnapshot()
  }, SNAPSHOT_MS)
})

onUnmounted(() => {
  clearInterval(dashboardTimer)
  clearInterval(snapshotTimer)
  clearTimeout(searchTimeout)
})
</script>

<template>
  <AppLayout title="Monitoring Jaringan" subtitle="Pemantauan real-time perangkat MikroTik & infrastruktur">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <p class="text-xs text-muted">
        <RefreshCw v-if="autoRefreshing || syncing || snapshotPolling" class="mr-1 inline h-3.5 w-3.5 animate-spin" />
        Collector 30 dtk · traffic snapshot 5 dtk
        <span v-if="lastRefreshedAt"> · status {{ formatRefreshTime(lastRefreshedAt) }}</span>
        <span v-if="lastSnapshotAt"> · traffic {{ formatRefreshTime(lastSnapshotAt) }}</span>
      </p>
      <Button variant="outline" size="sm" :disabled="syncing || autoRefreshing" @click="handleSyncAll">
        <RefreshCw :class="cn('h-4 w-4', (syncing || autoRefreshing) && 'animate-spin')" />
        {{ syncing ? 'Memperbarui...' : 'Sync Semua Sekarang' }}
      </Button>
    </div>

    <div v-if="summary" class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3">
      <Card
        v-for="item in [
          { label: 'Router Online', value: summary.router_online, color: 'text-success' },
          { label: 'Router Offline', value: summary.router_offline, color: 'text-danger' },
          { label: 'CPU Rata-rata', value: `${summary.cpu_average}%`, color: 'text-warning' },
        ]"
        :key="item.label"
        padding="sm"
      >
        <p :class="cn('text-xl font-bold', item.color)">{{ item.value }}</p>
        <p class="text-xs text-muted">{{ item.label }}</p>
      </Card>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-3">
      <SearchInput v-model="search" placeholder="Cari router..." class="max-w-sm flex-1" />
      <Select v-model="selectedPop" class="w-40 text-xs">
        <option value="all">Semua POP</option>
        <option v-for="pop in pops" :key="pop" :value="pop">{{ pop }}</option>
      </Select>
    </div>

    <div v-if="loading" class="py-12 text-center text-sm text-muted">Memuat data...</div>

    <div v-else-if="!filteredRouters.length" class="rounded-[18px] border border-border bg-card p-10 text-center text-sm text-muted">
      Belum ada router.
    </div>

    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <Card
        v-for="router in filteredRouters"
        :key="router.id"
        class="flex flex-col"
      >
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <Router class="h-4 w-4 shrink-0" :class="router.status === 'online' ? 'text-success' : 'text-danger'" />
              <h3 class="truncate font-semibold text-foreground">{{ router.name }}</h3>
            </div>
            <p class="mt-1 text-sm text-muted">{{ router.ip }}</p>
          </div>
          <Badge :variant="router.status === 'online' ? 'success' : 'danger'">
            {{ router.status === 'online' ? 'Online' : 'Offline' }}
          </Badge>
        </div>

        <div class="mt-3 flex-1 space-y-1.5">
          <div
            v-for="iface in routerListIfaces(router)"
            :key="iface.interface_name"
            class="rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-800/50"
          >
            <p class="truncate text-xs font-medium text-foreground" :title="iface.interface_name">
              {{ shortIfaceName(iface.label || iface.interface_name) }}
            </p>
            <p class="mt-0.5 text-xs tabular-nums text-muted">
              ↓ {{ formatMbps(iface.rx_mbps) }} · ↑ {{ formatMbps(iface.tx_mbps) }} Mbps
            </p>
          </div>
          <p v-if="!routerListIfaces(router).length" class="py-2 text-xs text-muted">
            Belum pilih interface — klik tombol di bawah.
          </p>
        </div>

        <div class="mt-4 border-t border-border pt-3">
          <Button
            type="button"
            variant="outline"
            size="sm"
            class="w-full"
            @click="openInterfaceModal(router)"
          >
            <Settings2 class="h-3.5 w-3.5" />
            Pilih Interface
          </Button>
        </div>
      </Card>
    </div>

    <Modal
      :open="interfaceModalOpen"
      :title="selectedRouter ? `Pilih Interface — ${selectedRouter.name}` : 'Pilih Interface'"
      :subtitle="selectedRouter ? `${selectedRouter.ip} · Centang interface yang ingin dimonitor` : undefined"
      size="lg"
      @close="closeInterfaceModal"
    >
      <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <p class="text-xs text-muted">
            {{ localInterfaces.length }} interface · {{ monitoredInterfaces.length }} dimonitor
            <template v-if="hidePppoe"> · PPPoE disembunyikan</template>
          </p>
          <Button
            variant="outline"
            size="sm"
            :disabled="syncingInterfaces || selectedRouter?.status !== 'online'"
            @click="handleSyncInterfaces(false)"
          >
            <RefreshCw :class="cn('h-3.5 w-3.5', syncingInterfaces && 'animate-spin')" />
            {{ syncingInterfaces ? 'Sync...' : 'Sync Interface' }}
          </Button>
        </div>

        <p v-if="interfaceMessage" class="text-xs text-primary">{{ interfaceMessage }}</p>

        <div v-if="localInterfaces.length > 0" class="flex flex-wrap items-center gap-2">
          <input
            v-model="interfaceFilter"
            type="search"
            placeholder="Cari interface..."
            class="h-9 min-w-[10rem] flex-1 rounded-lg border border-border bg-background px-3 text-sm"
          />
          <label class="flex items-center gap-1.5 text-xs text-muted">
            <input v-model="hidePppoe" type="checkbox" class="rounded border-slate-300 text-primary" />
            Sembunyikan PPPoE
          </label>
        </div>

        <p v-if="localInterfaces.length === 0" class="py-6 text-center text-sm text-muted">
          <template v-if="syncingInterfaces">Mengambil daftar interface dari perangkat...</template>
          <template v-else-if="selectedRouter?.status !== 'online'">Router offline — tidak bisa sync interface.</template>
          <template v-else>Belum ada interface — klik Sync Interface.</template>
        </p>

        <div v-else class="max-h-[50vh] space-y-1 overflow-y-auto rounded-xl border border-border p-2">
          <label
            v-for="iface in visibleInterfaces"
            :key="iface.id"
            class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-2 text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
            :class="iface.is_monitored ? 'font-medium' : 'text-muted'"
          >
            <input
              type="checkbox"
              :checked="iface.is_monitored"
              class="rounded border-slate-300 text-primary"
              @change="toggleMonitor(iface)"
            />
            <Network class="h-3.5 w-3.5 shrink-0 text-muted" />
            <span class="truncate">{{ iface.display_name || iface.interface_name }}</span>
            <Badge
              v-if="iface.is_running"
              variant="success"
              class="ml-auto shrink-0 text-[9px]"
            >
              Up
            </Badge>
            <Badge v-else variant="danger" class="ml-auto shrink-0 text-[9px]">Down</Badge>
          </label>
          <p v-if="visibleInterfaces.length === 0" class="py-4 text-center text-xs text-muted">
            Tidak ada interface yang cocok filter.
          </p>
        </div>

        <p
          v-if="monitoredInterfaces.length === 0 && localInterfaces.length > 0"
          class="text-xs text-warning"
        >
          Belum ada interface tercentang — trafik tidak akan ditampilkan di kartu.
        </p>
      </div>
      <template #footer>
        <Button variant="outline" @click="closeInterfaceModal">Tutup</Button>
      </template>
    </Modal>
  </AppLayout>
</template>
