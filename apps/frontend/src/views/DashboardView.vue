<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import KpiCard from '@/components/dashboard/KpiCard.vue'
import ChartCard from '@/components/dashboard/ChartCard.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Select from '@/components/ui/Select.vue'
import Input from '@/components/ui/Input.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { dashboardApi, odcApi, type DashboardStats, type DashboardSpecialist } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { Activity, Trophy, Award } from 'lucide-vue-next'

const auth = useAuthStore()
const fromDate = ref(new Date().toISOString().slice(0, 10))
const toDate = ref(new Date().toISOString().slice(0, 10))
const userId = ref<number | ''>('')
const odcName = ref('')
const odcs = ref<{ id: number; name: string }[]>([])
const loading = ref(true)

const periodLabel = ref('')
const periodDays = ref(1)
const kpis = ref<DashboardStats['kpis']>([])
const specialists = ref<DashboardSpecialist[]>([])
const nocPerformance = ref<DashboardStats['noc_performance']>([])
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

function heatClass(value: number, max: number) {
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

async function load() {
  loading.value = true
  try {
    const { data } = await dashboardApi.stats({
      period: 'custom',
      from: fromDate.value,
      to: toDate.value,
      user_id: userId.value === '' ? undefined : Number(userId.value),
      odc_name: odcName.value || undefined,
    })
    periodLabel.value = data.period.label
    periodDays.value = data.period.days ?? 1
    kpis.value = data.category_kpis ?? data.kpis
    specialists.value = data.specialists ?? []
    nocPerformance.value = data.noc_performance
    charts.value = data.charts ?? emptyCharts()
    heatmap.value = data.heatmap ?? { days: [], rows: [] }
    recentActivities.value = data.recent_activities
    nocUsers.value = data.noc_users
  } catch {
    periodLabel.value = ''
    periodDays.value = 1
    kpis.value = []
    specialists.value = []
    nocPerformance.value = []
    charts.value = emptyCharts()
    heatmap.value = { days: [], rows: [] }
    recentActivities.value = []
  } finally {
    loading.value = false
  }
}

watch([fromDate, toDate, userId, odcName], () => {
  void load()
})

onMounted(async () => {
  await loadOdcs()
  await load()
})
</script>

<template>
  <AppLayout title="Dashboard Kinerja NOC" :subtitle="subtitle">
    <div class="mb-6 flex flex-wrap items-end gap-3">
      <div>
        <label class="mb-1.5 block text-xs font-medium text-muted">Dari</label>
        <Input v-model="fromDate" type="date" class="w-40" />
      </div>
      <div>
        <label class="mb-1.5 block text-xs font-medium text-muted">Sampai</label>
        <Input v-model="toDate" type="date" class="w-40" />
      </div>
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
    <div v-if="loading && showKpis" class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5">
      <Skeleton v-for="i in 5" :key="i" class="h-32" />
    </div>
    <div v-else-if="showKpis" class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5">
      <KpiCard
        v-for="(kpi, i) in kpis"
        :key="kpi.key"
        :label="kpi.label"
        :value="kpi.value"
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
          <p class="mt-1 text-xs text-muted">Peringkat Clear / Close per kategori</p>
        </div>
        <div v-if="nocPerformance.length" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border text-left text-xs text-muted">
                <th class="pb-2 pr-2">#</th>
                <th class="pb-2 pr-3">Nama</th>
                <th class="pb-2 pr-2 text-right text-[#EF4444]">Komplain</th>
                <th class="pb-2 pr-2 text-right text-[#22C55E]">Aktivasi</th>
                <th class="pb-2 pr-2 text-right text-[#3498DB]">Ticket</th>
                <th class="pb-2 pr-2 text-right text-[#9B59B6]">CCTV</th>
                <th class="pb-2 pr-2 text-right text-[#E67E22]">Dismantle</th>
                <th class="pb-2 text-right">Total</th>
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
                <td class="py-2.5 pr-2 text-right">{{ row.complaints_clear }}</td>
                <td class="py-2.5 pr-2 text-right">{{ row.activations_clear }}</td>
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

    <!-- 5. Heatmap -->
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
                  :class="heatClass(val, heatmapMax)"
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
          Rata-rata/hari dihitung dari {{ periodDays }} hari dalam periode
        </p>
      </div>
      <div v-if="nocPerformance.length" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border text-left text-xs text-muted">
              <th class="pb-2 pr-3">Nama</th>
              <th class="pb-2 pr-2 text-right">Komplain</th>
              <th class="pb-2 pr-2 text-right">Aktivasi</th>
              <th class="pb-2 pr-2 text-right">Ticket</th>
              <th class="pb-2 pr-2 text-right">CCTV</th>
              <th class="pb-2 pr-2 text-right">Dismantle</th>
              <th class="pb-2 pr-2 text-right">Total</th>
              <th class="pb-2 text-right">Rata²/Hari</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in nocPerformance"
              :key="`detail-${row.user_id}`"
              class="border-b border-border/60"
            >
              <td class="py-2.5 pr-3 font-medium text-foreground">{{ row.name }}</td>
              <td class="py-2.5 pr-2 text-right">{{ row.complaints_clear }}</td>
              <td class="py-2.5 pr-2 text-right">{{ row.activations_clear }}</td>
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
