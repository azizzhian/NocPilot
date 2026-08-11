<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import KpiCard from '@/components/dashboard/KpiCard.vue'
import ChartCard from '@/components/dashboard/ChartCard.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Select from '@/components/ui/Select.vue'
import DateRangePicker from '@/components/ui/DateRangePicker.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { dashboardApi, odcApi, type DashboardStats, type DashboardSpecialist } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import { todayInput } from '@/lib/date-input'
import { Activity, Trophy, Award, Cable } from 'lucide-vue-next'

const auth = useAuthStore()
const appStore = useAppStore()
const fromDate = ref(todayInput())
const toDate = ref(todayInput())
const userId = ref<number | ''>('')
const odcName = ref('')
const complaintOdcName = ref('')
const clientShareSource = ref<'all' | 'complaint' | 'ticket'>('all')
const complaintView = ref<'pie' | 'bars'>('bars')
const odcs = ref<{ id: number; name: string }[]>([])
const loading = ref(true)

const periodLabel = ref('')
const periodDays = ref(1)
const kpis = ref<DashboardStats['kpis']>([])
const specialists = ref<DashboardSpecialist[]>([])
const nocPerformance = ref<DashboardStats['noc_performance']>([])
const odcStats = ref<NonNullable<DashboardStats['odc_stats']>>([])
const complaintClientShare = ref<NonNullable<DashboardStats['complaint_client_share']>>({
  total: 0,
  complaints_total: 0,
  tickets_total: 0,
  source: 'all',
  rows: [],
})
const charts = ref<DashboardStats['charts'] | null>(null)
const heatmap = ref<DashboardStats['heatmap']>({ days: [], rows: [] })
const recentActivities = ref<DashboardStats['recent_activities']>([])
const nocUsers = ref<DashboardStats['noc_users']>([])

const showKpis = computed(() => auth.can('dashboard.widget.kpis'))
const showClearByType = computed(() => auth.can('dashboard.widget.clear_by_type'))
const showClearByNoc = computed(() => auth.can('dashboard.widget.clear_by_noc'))
const showNocPerformance = computed(() => auth.can('dashboard.widget.noc_performance'))
const showRecent = computed(() => auth.can('dashboard.widget.recent'))

const kpiHref: Record<string, string> = {
  complaints: '/komplain',
  activations: '/aktivasi',
  cctv: '/aktivasi',
  tickets: '/report/ticket',
  dismantles: '/report/dismantle',
  noc_updates: '/update-noc',
}

const emptyCharts = (): DashboardStats['charts'] => ({
  clear_by_noc: { categories: [], series: [{ name: 'Total Clear', data: [] }] },
  stacked_by_noc: {
    categories: [],
    series: [
      { name: 'Komplain', data: [], color: '#EF4444' },
      { name: 'Aktivasi', data: [], color: '#22C55E' },
      { name: 'Ticket', data: [], color: '#3498DB' },
      { name: 'Dismantle', data: [], color: '#E67E22' },
      { name: 'CCTV', data: [], color: '#9B59B6' },
    ],
  },
  clear_by_type: { categories: [], series: [{ name: 'Clear', data: [] }], colors: [] },
  contribution: { categories: [], series: [{ name: 'Kontribusi', data: [] }], colors: [] },
})

const subtitle = computed(() =>
  periodLabel.value
    ? `Kinerja NOC — ${periodLabel.value}`
    : 'Dashboard kinerja operasional NOC',
)

const stacked = computed(() => charts.value?.stacked_by_noc ?? emptyCharts().stacked_by_noc!)
const contribution = computed(() => charts.value?.contribution ?? emptyCharts().contribution!)

const medal = (idx: number) => {
  if (idx === 0) return '🥇'
  if (idx === 1) return '🥈'
  if (idx === 2) return '🥉'
  return String(idx + 1)
}

const specialistColor: Record<string, string> = {
  danger: 'border-danger/30 bg-danger/5',
  success: 'border-success/30 bg-success/5',
  info: 'border-info/30 bg-info/5',
  primary: 'border-primary/30 bg-primary/5',
  warning: 'border-warning/30 bg-warning/5',
}

function nocHeatClass(value: number, max: number) {
  if (value <= 0 || max <= 0) return 'bg-muted/30 text-muted'
  const ratio = value / max
  if (ratio >= 0.75) return 'bg-success text-white'
  if (ratio >= 0.5) return 'bg-success/70 text-white'
  if (ratio >= 0.25) return 'bg-success/40 text-foreground'
  return 'bg-success/20 text-foreground'
}

const heatmapMax = computed(() => {
  const values = heatmap.value?.rows.flatMap((r) => r.values) ?? []
  return Math.max(1, ...values, 0)
})

const listPerPage = 10
const odcPage = ref(1)

const odcLastPage = computed(() => Math.max(1, Math.ceil(odcStats.value.length / listPerPage)))
const pagedOdcStats = computed(() => {
  const start = (odcPage.value - 1) * listPerPage
  return odcStats.value.slice(start, start + listPerPage)
})

function rankLabel(page: number, idx: number) {
  return medal((page - 1) * listPerPage + idx)
}

const complaintPieColors = [
  '#3B82F6', '#22C55E', '#F59E0B', '#EF4444', '#8B5CF6',
  '#06B6D4', '#EC4899', '#84CC16', '#F97316', '#64748B',
]

const complaintPieChart = computed(() => {
  const rows = complaintClientShare.value.rows.slice(0, 10)
  return {
    categories: rows.map((r) => r.name),
    series: [{ name: 'Komplain', data: rows.map((r) => r.count) }],
    colors: complaintPieColors.slice(0, Math.max(1, rows.length)),
  }
})

const complaintBarRows = computed(() => complaintClientShare.value.rows.slice(0, 10))

const complaintShareMaxPct = computed(() =>
  Math.max(1, ...(complaintBarRows.value.map((r) => r.pct) || [0])),
)

const complaintPieOptions = computed(() => ({
  chart: {
    type: 'pie' as const,
    toolbar: { show: false },
    fontFamily: 'Inter, sans-serif',
    background: 'transparent',
    animations: { enabled: true, easing: 'easeinout', speed: 800 },
  },
  labels: complaintPieChart.value.categories,
  colors: complaintPieChart.value.colors,
  dataLabels: {
    enabled: true,
    formatter: (val: number) => `${Math.round(val)}%`,
  },
  legend: {
    position: 'bottom' as const,
    labels: { colors: '#94a3b8' },
  },
  stroke: { width: 0 },
  tooltip: {
    theme: (appStore.isDark ? 'dark' : 'light') as 'dark' | 'light',
    y: {
      formatter: (val: number) => `${val}x`,
    },
  },
}))

const complaintShareSubtitle = computed(() => {
  const total = complaintClientShare.value.total
  const c = complaintClientShare.value.complaints_total ?? 0
  const t = complaintClientShare.value.tickets_total ?? 0
  if (clientShareSource.value === 'complaint') {
    return total ? `Top 10 client komplain · total ${total}` : 'Top 10 client berdasarkan komplain'
  }
  if (clientShareSource.value === 'ticket') {
    return total ? `Top 10 client tiket · total ${total}` : 'Top 10 client berdasarkan tiket'
  }
  return total
    ? `Top 10 client · total ${total} (Komplain ${c} · Tiket ${t})`
    : 'Top 10 client berdasarkan komplain & tiket'
})

function clientShareMeta(row: NonNullable<DashboardStats['complaint_client_share']>['rows'][number]) {
  const parts: string[] = []
  parts.push(row.customer_code || (row.is_gamas ? 'Gamas' : '—'))
  if (row.odc_name) parts.push(row.odc_name)
  if (clientShareSource.value === 'all') {
    parts.push(`Komplain ${row.complaints_count ?? 0}x`)
    parts.push(`Tiket ${row.tickets_count ?? 0}x`)
  } else if (clientShareSource.value === 'complaint') {
    parts.push(`Komplain ${row.complaints_count ?? row.count}x`)
  } else {
    parts.push(`Tiket ${row.tickets_count ?? row.count}x`)
  }
  return parts.join(' · ')
}

async function loadOdcs() {
  try {
    const res = await odcApi.list({ per_page: 200 })
    odcs.value = res.data.data.map((o) => ({
      id: o.id as number,
      name: String(o.name ?? ''),
    }))
  } catch {
    odcs.value = []
  }
}

async function load(opts?: { soft?: boolean }) {
  if (!opts?.soft) loading.value = true
  try {
    const { data } = await dashboardApi.stats({
      period: 'custom',
      from: fromDate.value,
      to: toDate.value,
      user_id: userId.value === '' ? undefined : Number(userId.value),
      odc_name: odcName.value || undefined,
      complaint_odc_name: complaintOdcName.value || undefined,
      client_share_source: clientShareSource.value,
    })
    periodLabel.value = data.period.label
    periodDays.value = data.period.days ?? 1
    kpis.value = data.category_kpis ?? data.kpis
    specialists.value = data.specialists ?? []
    nocPerformance.value = data.noc_performance
    odcStats.value = data.odc_stats ?? []
    complaintClientShare.value = data.complaint_client_share ?? {
      total: 0,
      complaints_total: 0,
      tickets_total: 0,
      source: clientShareSource.value,
      rows: [],
    }
    odcPage.value = 1
    charts.value = data.charts ?? emptyCharts()
    heatmap.value = data.heatmap ?? { days: [], rows: [] }
    recentActivities.value = data.recent_activities
    nocUsers.value = data.noc_users
  } catch {
    if (!opts?.soft) {
      periodLabel.value = ''
      periodDays.value = 1
      kpis.value = []
      specialists.value = []
      nocPerformance.value = []
      odcStats.value = []
      charts.value = emptyCharts()
      heatmap.value = { days: [], rows: [] }
      recentActivities.value = []
    }
    complaintClientShare.value = {
      total: 0,
      complaints_total: 0,
      tickets_total: 0,
      source: clientShareSource.value,
      rows: [],
    }
  } finally {
    if (!opts?.soft) loading.value = false
  }
}

watch([fromDate, toDate, userId, odcName], () => {
  void load()
})

watch([complaintOdcName, clientShareSource], () => {
  void load({ soft: true })
})

onMounted(async () => {
  await loadOdcs()
  await load()
})
</script>

<template>
  <AppLayout title="Dashboard Kinerja NOC" :subtitle="subtitle">
    <div class="mb-6 flex flex-wrap items-end gap-3">
      <DateRangePicker v-model:from="fromDate" v-model:to="toDate" class="w-64" />
      <div>
        <label class="mb-1.5 block text-xs font-medium text-muted">ODC / Site</label>
        <Select v-model="odcName" class="w-48">
          <option value="">Semua ODC</option>
          <option v-for="o in odcs" :key="o.id" :value="o.name">{{ o.name }}</option>
        </Select>
      </div>
      <div>
        <label class="mb-1.5 block text-xs font-medium text-muted">NOC</label>
        <Select :model-value="String(userId)" class="w-48" @update:model-value="(v) => userId = v === '' ? '' : Number(v)">
          <option value="">Semua NOC</option>
          <option v-for="u in nocUsers" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
        </Select>
      </div>
      <Button variant="outline" :disabled="loading" @click="load">Refresh</Button>
    </div>

    <!-- 1. KPI Cards -->
    <div v-if="loading && showKpis" class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6">
      <Skeleton v-for="i in 6" :key="i" class="h-32" />
    </div>
    <div v-else-if="showKpis" class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6">
      <KpiCard
        v-for="(kpi, i) in kpis"
        :key="kpi.key"
        :label="kpi.label"
        :value="kpi.value"
        :open="kpi.open"
        :clear="kpi.clear"
        :split-status="Boolean(kpi.split_status)"
        :color="kpi.color"
        :icon="kpi.icon ?? 'router'"
        :top-name="kpi.top?.name"
        :top-count="kpi.top?.count"
        :delay="i * 40"
        :to="kpiHref[kpi.key] ?? null"
      />
    </div>

    <!-- 2. Composition + Badge Spesialis -->
    <div
      v-if="loading && (showClearByType || showNocPerformance)"
      class="mt-6 grid gap-6 lg:grid-cols-3"
    >
      <Skeleton v-if="showClearByType" class="h-72" />
      <Skeleton v-if="showNocPerformance" class="h-72 lg:col-span-2" />
    </div>
    <div
      v-else-if="!loading && (showClearByType || showNocPerformance)"
      class="mt-6 grid gap-6"
      :class="showClearByType && showNocPerformance ? 'lg:grid-cols-3' : ''"
    >
      <ChartCard
        v-if="showClearByType && charts"
        title="Persentase Penyelesaian"
        subtitle="Komplain · Aktivasi · Ticket · Dismantle · CCTV"
        :categories="charts.clear_by_type.categories"
        :series="charts.clear_by_type.series"
        :colors="charts.clear_by_type.colors"
        type="donut"
        :height="260"
      />

      <Card
        v-if="showNocPerformance"
        class="p-5"
        :class="showClearByType ? 'lg:col-span-2' : ''"
      >
        <div class="mb-4 flex items-center gap-2">
          <Award class="h-4 w-4 text-primary" />
          <div>
            <h3 class="text-sm font-semibold text-foreground">Lencana</h3>
            <p class="text-xs text-muted">Top performer per kategori</p>
          </div>
        </div>
        <div v-if="specialists.length" class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-3">
          <div
            v-for="badge in specialists"
            :key="badge.key"
            class="rounded-xl border px-3 py-2.5"
            :class="specialistColor[badge.color] ?? 'border-border bg-muted/20'"
          >
            <p class="text-xs font-semibold text-foreground">{{ badge.emoji }} {{ badge.title }}</p>
            <p class="mt-0.5 text-sm font-medium">{{ badge.name }}</p>
            <p class="text-[11px] text-muted">{{ badge.count }} {{ badge.unit }}</p>
          </div>
        </div>
        <p v-else class="py-10 text-center text-sm text-muted">Belum ada spesialis di periode ini.</p>
      </Card>
    </div>

    <!-- 3. Stacked bar -->
    <div v-if="!loading && showClearByNoc" class="mt-6">
      <ChartCard
        title="Performa NOC per Kategori"
        subtitle="Stacked bar — pembagian kerja tiap orang"
        :categories="stacked.categories"
        :series="stacked.series"
        type="bar"
        horizontal
        stacked
        :height="Math.max(240, (stacked.categories.length || 1) * 42 + 90)"
      />
    </div>

    <!-- 4. Leaderboard + Contribution -->
    <div v-if="!loading && showNocPerformance" class="mt-6 grid gap-6 lg:grid-cols-3">
      <Card class="p-5 lg:col-span-2">
        <div class="mb-4">
          <div class="flex items-center gap-2">
            <Trophy class="h-4 w-4 text-primary" />
            <h3 class="text-sm font-semibold text-foreground">Leaderboard</h3>
          </div>
          <p class="mt-1 text-xs text-muted">On-Progress vs Clear per kategori — ranking by total clear</p>
        </div>
        <div v-if="nocPerformance.length" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border text-left text-xs text-muted">
                <th class="pb-2 pr-2" rowspan="2">#</th>
                <th class="pb-2 pr-3" rowspan="2">Nama</th>
                <th class="pb-1 pr-2 text-center text-[#EF4444]" colspan="2">Komplain</th>
                <th class="pb-1 pr-2 text-center text-[#22C55E]" colspan="2">Aktivasi</th>
                <th class="pb-1 pr-2 text-center text-[#3498DB]" colspan="2">Ticket</th>
                <th class="pb-2 pr-2 text-right text-[#9B59B6]" rowspan="2">CCTV</th>
                <th class="pb-2 pr-2 text-right text-[#E67E22]" rowspan="2">Dismantle</th>
                <th class="pb-2 text-right" rowspan="2">Total Clear</th>
              </tr>
              <tr class="border-b border-border text-left text-[10px] text-muted">
                <th class="pb-2 pr-2 text-right font-medium">OP</th>
                <th class="pb-2 pr-2 text-right font-medium">Clear</th>
                <th class="pb-2 pr-2 text-right font-medium">OP</th>
                <th class="pb-2 pr-2 text-right font-medium">Clear</th>
                <th class="pb-2 pr-2 text-right font-medium">OP</th>
                <th class="pb-2 pr-2 text-right font-medium">Clear</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, idx) in nocPerformance"
                :key="row.user_id"
                class="border-b border-border/60"
              >
                <td class="py-2.5 pr-2">{{ medal(idx) }}</td>
                <td class="py-2.5 pr-3 font-medium text-foreground">{{ row.name }}</td>
                <td class="py-2.5 pr-2 text-right text-warning">{{ row.complaints_open ?? 0 }}</td>
                <td class="py-2.5 pr-2 text-right">{{ row.complaints_clear }}</td>
                <td class="py-2.5 pr-2 text-right text-warning">{{ row.activations_open ?? 0 }}</td>
                <td class="py-2.5 pr-2 text-right">{{ row.activations_clear }}</td>
                <td class="py-2.5 pr-2 text-right text-warning">{{ row.tickets_open ?? 0 }}</td>
                <td class="py-2.5 pr-2 text-right">{{ row.tickets_clear ?? 0 }}</td>
                <td class="py-2.5 pr-2 text-right">{{ row.cctv_clear ?? row.cctv ?? 0 }}</td>
                <td class="py-2.5 pr-2 text-right">{{ row.dismantles_clear }}</td>
                <td class="py-2.5 text-right font-semibold">{{ row.total }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="py-8 text-center text-sm text-muted">Belum ada data di periode ini.</p>
      </Card>

      <ChartCard
        title="Kontribusi"
        :subtitle="`Persentase total clear — ${periodLabel || 'periode'}`"
        :categories="contribution.categories"
        :series="contribution.series"
        :colors="contribution.colors"
        type="pie"
        unit="%"
        :height="260"
      />
    </div>

    <!-- 4b. Statistik ODC + Persentase client komplain -->
    <div v-if="!loading && showNocPerformance" class="mt-6 grid gap-6 xl:grid-cols-2">
      <Card class="p-5">
        <div class="mb-4">
          <div class="flex items-center gap-2">
            <Cable class="h-4 w-4 text-primary" />
            <h3 class="text-sm font-semibold text-foreground">Statistik ODC</h3>
          </div>
          <p class="mt-1 text-xs text-muted">
            On-Progress vs Clear per ODC — diurutkan dari total clear terbanyak
          </p>
        </div>
        <div v-if="odcStats.length" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border text-left text-xs text-muted">
                <th class="pb-2 pr-2" rowspan="2">#</th>
                <th class="pb-2 pr-3" rowspan="2">ODC</th>
                <th class="pb-1 pr-2 text-center text-[#EF4444]" colspan="2">Komplain</th>
                <th class="pb-1 pr-2 text-center text-[#3498DB]" colspan="2">Ticket</th>
                <th class="pb-1 pr-2 text-center text-[#64748B]" colspan="2">Update NOC</th>
                <th class="pb-1 pr-2 text-center text-[#E67E22]" colspan="2">Dismantle</th>
                <th class="pb-2 text-right" rowspan="2">Total Clear</th>
              </tr>
              <tr class="border-b border-border text-left text-[10px] text-muted">
                <th class="pb-2 pr-2 text-right font-medium">OP</th>
                <th class="pb-2 pr-2 text-right font-medium">Clear</th>
                <th class="pb-2 pr-2 text-right font-medium">OP</th>
                <th class="pb-2 pr-2 text-right font-medium">Clear</th>
                <th class="pb-2 pr-2 text-right font-medium">OP</th>
                <th class="pb-2 pr-2 text-right font-medium">Clear</th>
                <th class="pb-2 pr-2 text-right font-medium">OP</th>
                <th class="pb-2 pr-2 text-right font-medium">Clear</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, idx) in pagedOdcStats"
                :key="row.odc_name"
                class="border-b border-border/60"
              >
                <td class="py-2.5 pr-2">{{ rankLabel(odcPage, idx) }}</td>
                <td class="py-2.5 pr-3 font-medium text-foreground">{{ row.odc_name }}</td>
                <td class="py-2.5 pr-2 text-right text-warning">{{ row.complaints_open }}</td>
                <td class="py-2.5 pr-2 text-right">{{ row.complaints_clear }}</td>
                <td class="py-2.5 pr-2 text-right text-warning">{{ row.tickets_open }}</td>
                <td class="py-2.5 pr-2 text-right">{{ row.tickets_clear }}</td>
                <td class="py-2.5 pr-2 text-right text-warning">{{ row.noc_updates_open }}</td>
                <td class="py-2.5 pr-2 text-right">{{ row.noc_updates_clear }}</td>
                <td class="py-2.5 pr-2 text-right text-warning">{{ row.dismantles_open }}</td>
                <td class="py-2.5 pr-2 text-right">{{ row.dismantles_clear }}</td>
                <td class="py-2.5 text-right font-semibold">{{ row.total }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="odcLastPage > 1" class="mt-4 flex justify-center gap-2">
          <Button variant="outline" size="sm" :disabled="odcPage <= 1" @click="odcPage -= 1">
            Sebelumnya
          </Button>
          <span class="flex items-center px-3 text-sm text-muted">{{ odcPage }} / {{ odcLastPage }}</span>
          <Button variant="outline" size="sm" :disabled="odcPage >= odcLastPage" @click="odcPage += 1">
            Selanjutnya
          </Button>
        </div>
        <p v-else-if="!odcStats.length" class="py-8 text-center text-sm text-muted">Belum ada data ODC di periode ini.</p>
      </Card>

      <Card class="p-5">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 class="text-sm font-semibold text-foreground">Persentase Client</h3>
            <p class="mt-1 text-xs text-muted">{{ complaintShareSubtitle }}</p>
          </div>
          <div class="flex flex-wrap items-end gap-3">
            <div>
              <label class="mb-1.5 block text-xs font-medium text-muted">Sumber</label>
              <Select v-model="clientShareSource" class="w-36">
                <option value="all">Semua</option>
                <option value="complaint">Komplain</option>
                <option value="ticket">Tiket</option>
              </Select>
            </div>
            <div>
              <label class="mb-1.5 block text-xs font-medium text-muted">Tampilan</label>
              <div class="flex gap-1 rounded-xl border border-border p-1">
                <button
                  type="button"
                  :class="[
                    'rounded-lg px-3 py-1.5 text-xs font-medium transition-colors',
                    complaintView === 'bars' ? 'bg-primary text-white' : 'text-muted hover:bg-muted',
                  ]"
                  @click="complaintView = 'bars'"
                >
                  Bar
                </button>
                <button
                  type="button"
                  :class="[
                    'rounded-lg px-3 py-1.5 text-xs font-medium transition-colors',
                    complaintView === 'pie' ? 'bg-primary text-white' : 'text-muted hover:bg-muted',
                  ]"
                  @click="complaintView = 'pie'"
                >
                  Pie
                </button>
              </div>
            </div>
            <div>
              <label class="mb-1.5 block text-xs font-medium text-muted">Filter ODC</label>
              <Select v-model="complaintOdcName" class="w-44">
                <option value="">Semua ODC</option>
                <option v-for="o in odcs" :key="o.id" :value="o.name">{{ o.name }}</option>
              </Select>
            </div>
          </div>
        </div>

        <template v-if="complaintBarRows.length">
          <div v-if="complaintView === 'bars'" class="space-y-3">
            <div
              v-for="(row, idx) in complaintBarRows"
              :key="row.key"
              class="space-y-1"
            >
              <div class="flex items-start justify-between gap-3 text-sm">
                <div class="min-w-0">
                  <p class="truncate font-medium text-foreground">
                    <span class="mr-1.5 text-muted">{{ medal(idx) }}</span>{{ row.name }}
                  </p>
                  <p class="truncate text-[11px] text-muted">
                    {{ clientShareMeta(row) }}
                  </p>
                </div>
                <p class="shrink-0 text-sm font-semibold text-danger">{{ row.pct }}%</p>
              </div>
              <div class="h-2 overflow-hidden rounded-full bg-muted/40">
                <div
                  class="h-full rounded-full bg-danger/80 transition-all"
                  :style="{ width: `${Math.max(4, (row.pct / complaintShareMaxPct) * 100)}%` }"
                />
              </div>
            </div>
          </div>

          <div v-else class="-mx-1 -mb-1">
            <VueApexCharts
              type="pie"
              :height="300"
              :options="complaintPieOptions"
              :series="complaintPieChart.series[0]?.data ?? []"
            />
          </div>
        </template>
        <p v-else class="py-10 text-center text-sm text-muted">Belum ada data di periode ini.</p>
      </Card>
    </div>

    <!-- 5. Heatmap NOC -->
    <Card v-if="!loading && showNocPerformance" class="mt-6 p-5">
      <div class="mb-4">
        <h3 class="text-sm font-semibold text-foreground">Heatmap Mingguan</h3>
        <p class="text-xs text-muted">Produktivitas clear 7 hari terakhir — semakin hijau semakin aktif</p>
      </div>
      <div v-if="heatmap?.rows?.length" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs text-muted">
              <th class="pb-2 pr-3 font-medium">Nama</th>
              <th
                v-for="(d, i) in heatmap.days"
                :key="i"
                class="pb-2 px-1 text-center font-medium"
              >
                {{ d }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in heatmap.rows" :key="row.user_id">
              <td class="py-1.5 pr-3 font-medium text-foreground whitespace-nowrap">{{ row.name }}</td>
              <td
                v-for="(val, i) in row.values"
                :key="i"
                class="px-1 py-1.5"
              >
                <div
                  class="mx-auto flex h-8 w-8 items-center justify-center rounded-md text-xs font-semibold"
                  :class="nocHeatClass(val, heatmapMax)"
                >
                  {{ val }}
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="py-10 text-center text-sm text-muted">Belum ada aktivitas 7 hari terakhir.</p>
    </Card>

    <!-- 6. Detail table -->
    <Card v-if="!loading && showNocPerformance" class="mt-6 p-5">
      <div class="mb-4">
        <h3 class="text-sm font-semibold text-foreground">Detail Performa Seluruh NOC</h3>
        <p class="text-xs text-muted">
          OP = On-Progress · Clear = selesai · Rata-rata/hari dari {{ periodDays }} hari (berdasarkan total clear)
        </p>
      </div>
      <div v-if="nocPerformance.length" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border text-left text-xs text-muted">
              <th class="pb-2 pr-3" rowspan="2">Nama</th>
              <th class="pb-1 pr-2 text-center" colspan="2">Komplain</th>
              <th class="pb-1 pr-2 text-center" colspan="2">Aktivasi</th>
              <th class="pb-1 pr-2 text-center" colspan="2">Ticket</th>
              <th class="pb-2 pr-2 text-right" rowspan="2">CCTV</th>
              <th class="pb-2 pr-2 text-right" rowspan="2">Dismantle</th>
              <th class="pb-2 pr-2 text-right" rowspan="2">Total Clear</th>
              <th class="pb-2 text-right" rowspan="2">Rata²/Hari</th>
            </tr>
            <tr class="border-b border-border text-left text-[10px] text-muted">
              <th class="pb-2 pr-2 text-right font-medium">OP</th>
              <th class="pb-2 pr-2 text-right font-medium">Clear</th>
              <th class="pb-2 pr-2 text-right font-medium">OP</th>
              <th class="pb-2 pr-2 text-right font-medium">Clear</th>
              <th class="pb-2 pr-2 text-right font-medium">OP</th>
              <th class="pb-2 pr-2 text-right font-medium">Clear</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in nocPerformance"
              :key="`detail-${row.user_id}`"
              class="border-b border-border/60"
            >
              <td class="py-2.5 pr-3 font-medium text-foreground">{{ row.name }}</td>
              <td class="py-2.5 pr-2 text-right text-warning">{{ row.complaints_open ?? 0 }}</td>
              <td class="py-2.5 pr-2 text-right">{{ row.complaints_clear }}</td>
              <td class="py-2.5 pr-2 text-right text-warning">{{ row.activations_open ?? 0 }}</td>
              <td class="py-2.5 pr-2 text-right">{{ row.activations_clear }}</td>
              <td class="py-2.5 pr-2 text-right text-warning">{{ row.tickets_open ?? 0 }}</td>
              <td class="py-2.5 pr-2 text-right">{{ row.tickets_clear ?? 0 }}</td>
              <td class="py-2.5 pr-2 text-right">{{ row.cctv_clear ?? row.cctv ?? 0 }}</td>
              <td class="py-2.5 pr-2 text-right">{{ row.dismantles_clear }}</td>
              <td class="py-2.5 pr-2 text-right font-semibold">{{ row.total }}</td>
              <td class="py-2.5 text-right text-muted">{{ row.avg_per_day ?? 0 }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="py-8 text-center text-sm text-muted">Belum ada data performa.</p>
    </Card>

    <!-- Recent activity -->
    <div v-if="!loading && showRecent" class="mt-6">
      <Card class="p-5">
        <div class="mb-4 flex items-center gap-2">
          <Activity class="h-4 w-4 text-primary" />
          <h3 class="text-sm font-semibold text-foreground">Aktivitas Terbaru</h3>
        </div>
        <div v-if="recentActivities.length" class="space-y-3">
          <div v-for="activity in recentActivities" :key="activity.id" class="flex gap-3">
            <div
              class="mt-1 h-2 w-2 shrink-0 rounded-full"
              :class="{
                'bg-success': activity.severity === 'success',
                'bg-info': activity.severity === 'info',
                'bg-warning': activity.severity === 'warning',
              }"
            />
            <div class="min-w-0 flex-1">
              <p class="text-sm text-foreground">{{ activity.message }}</p>
              <p class="text-[10px] text-muted">{{ activity.time }}</p>
            </div>
          </div>
        </div>
        <p v-else class="py-6 text-center text-sm text-muted">Belum ada aktivitas.</p>
      </Card>
    </div>
  </AppLayout>
</template>
