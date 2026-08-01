<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import KpiCard from '@/components/dashboard/KpiCard.vue'
import ChartCard from '@/components/dashboard/ChartCard.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Select from '@/components/ui/Select.vue'
import Input from '@/components/ui/Input.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { dashboardApi, type DashboardStats } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { FileText, Zap, Activity, Trophy } from 'lucide-vue-next'

const auth = useAuthStore()
const period = ref<'day' | 'week' | 'month' | 'year'>('day')
const date = ref(new Date().toISOString().slice(0, 10))
const userId = ref<number | ''>('')
const loading = ref(true)

const periodLabel = ref('')
const kpis = ref<DashboardStats['kpis']>([])
const nocPerformance = ref<DashboardStats['noc_performance']>([])
const charts = ref<DashboardStats['charts'] | null>(null)
const recentActivities = ref<DashboardStats['recent_activities']>([])
const nocUsers = ref<DashboardStats['noc_users']>([])

const showKpis = computed(() => auth.can('dashboard.widget.kpis'))
const showTrend = computed(() => auth.can('dashboard.widget.trend'))
const showClearByType = computed(() => auth.can('dashboard.widget.clear_by_type'))
const showClearByNoc = computed(() => auth.can('dashboard.widget.clear_by_noc'))
const showNocPerformance = computed(() => auth.can('dashboard.widget.noc_performance'))
const showQuickActions = computed(() => auth.can('dashboard.widget.quick_actions'))
const showRecent = computed(() => auth.can('dashboard.widget.recent'))
const showAnyChart = computed(() => showTrend.value || showClearByType.value)
const periodOptions = [
  { value: 'day', label: 'Harian' },
  { value: 'week', label: 'Mingguan' },
  { value: 'month', label: 'Bulanan' },
  { value: 'year', label: 'Tahunan' },
]

const emptyCharts = (): DashboardStats['charts'] => ({
  clear_by_noc: { categories: [], series: [{ name: 'Total Clear', data: [] }] },
  trend: { categories: [], series: [
    { name: 'Input', data: [] },
    { name: 'Clear', data: [] },
  ] },
  clear_by_type: { categories: [], series: [{ name: 'Clear', data: [] }], colors: [] },
})

const subtitle = computed(() =>
  periodLabel.value
    ? `Kinerja NOC — ${periodLabel.value}`
    : 'Dashboard kinerja Input Harian',
)

const trendSubtitle = computed(() => {
  if (period.value === 'day') return '7 hari terakhir (termasuk tanggal acuan)'
  if (period.value === 'year') return 'Tren bulanan dalam tahun'
  return 'Input vs Clear per hari dalam periode'
})

async function load() {
  loading.value = true
  try {
    const { data } = await dashboardApi.stats({
      period: period.value,
      date: date.value,
      user_id: userId.value === '' ? undefined : Number(userId.value),
    })
    periodLabel.value = data.period.label
    kpis.value = data.kpis
    nocPerformance.value = data.noc_performance
    charts.value = data.charts ?? emptyCharts()
    recentActivities.value = data.recent_activities
    nocUsers.value = data.noc_users
  } finally {
    loading.value = false
  }
}

watch([period, date, userId], () => {
  void load()
})

onMounted(load)
</script>

<template>
  <AppLayout title="Dashboard Kinerja NOC" :subtitle="subtitle">
    <div class="mb-6 flex flex-wrap items-end gap-3">
      <div>
        <label class="mb-1.5 block text-xs font-medium text-muted">Periode</label>
        <Select v-model="period" class="w-36">
          <option v-for="opt in periodOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </Select>
      </div>
      <div>
        <label class="mb-1.5 block text-xs font-medium text-muted">Tanggal acuan</label>
        <Input v-model="date" type="date" class="w-40" />
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

    <div v-if="loading && showKpis" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
      <Skeleton v-for="i in 8" :key="i" class="h-28" />
    </div>
    <div v-else-if="showKpis" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
      <KpiCard
        v-for="(kpi, i) in kpis"
        :key="kpi.key"
        :label="kpi.label"
        :value="kpi.value"
        :color="kpi.color"
        :icon="kpi.icon ?? 'router'"
        :delay="i * 40"
      />
    </div>

    <div v-if="loading && showAnyChart" class="mt-6 grid gap-6 lg:grid-cols-3">
      <Skeleton v-if="showTrend" class="h-72 lg:col-span-2" />
      <Skeleton v-if="showClearByType" class="h-72" />
    </div>
    <div v-else-if="charts && showAnyChart" class="mt-6 grid gap-6 lg:grid-cols-3">
      <ChartCard
        v-if="showTrend"
        class="lg:col-span-2"
        title="Tren Input vs Clear"
        :subtitle="trendSubtitle"
        :categories="charts.trend.categories"
        :series="charts.trend.series"
        type="area"
        :height="260"
      />
      <ChartCard
        v-if="showClearByType"
        title="Komposisi Clear"
        subtitle="Aktivasi · Komplain · Dismantle"
        :categories="charts.clear_by_type.categories"
        :series="charts.clear_by_type.series"
        :colors="charts.clear_by_type.colors"
        type="donut"
        :height="260"
      />
    </div>

    <div v-if="!loading && charts && showClearByNoc" class="mt-6">
      <ChartCard
        title="Clear per NOC"
        subtitle="Siapa yang menyelesaikan (Clear)"
        :categories="charts.clear_by_noc.categories"
        :series="charts.clear_by_noc.series"
        type="bar"
        horizontal
        :height="Math.max(220, (charts.clear_by_noc.categories.length || 1) * 36 + 80)"
      />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
      <Card v-if="showNocPerformance" class="lg:col-span-2 p-6">
        <div class="mb-4">
          <div class="flex items-center gap-2">
            <Trophy class="h-4 w-4 text-primary" />
            <h3 class="text-sm font-semibold text-foreground">Peringkat Kinerja NOC</h3>
          </div>
          <p class="mt-1 text-xs text-muted">Diurutkan dari jumlah Clear / Close (siapa yang menyelesaikan)</p>
        </div>
        <div v-if="nocPerformance.length" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border text-left text-xs text-muted">
                <th class="pb-2 pr-3">#</th>
                <th class="pb-2 pr-3">Nama</th>
                <th class="pb-2 pr-3 text-right">Aktivasi Clear</th>
                <th class="pb-2 pr-3 text-right">Komplain Clear</th>
                <th class="pb-2 pr-3 text-right">Dismantle Clear</th>
                <th class="pb-2 pr-3 text-right">Ticket Close</th>
                <th class="pb-2 text-right">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, idx) in nocPerformance"
                :key="row.user_id"
                class="border-b border-border/60"
              >
                <td class="py-2.5 pr-3 text-muted">{{ idx + 1 }}</td>
                <td class="py-2.5 pr-3 font-medium text-foreground">{{ row.name }}</td>
                <td class="py-2.5 pr-3 text-right text-success">{{ row.activations_clear }}</td>
                <td class="py-2.5 pr-3 text-right text-success">{{ row.complaints_clear }}</td>
                <td class="py-2.5 pr-3 text-right text-success">{{ row.dismantles_clear }}</td>
                <td class="py-2.5 pr-3 text-right text-success">{{ row.tickets_clear ?? 0 }}</td>
                <td class="py-2.5 text-right font-semibold">{{ row.total }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="py-8 text-center text-sm text-muted">
          Belum ada data Input Harian di periode ini.
        </p>
      </Card>

      <div class="space-y-6" :class="{ 'lg:col-span-2': !showNocPerformance, 'lg:col-span-1': showNocPerformance }">
        <Card v-if="showQuickActions" class="p-6">
          <h3 class="mb-4 text-sm font-semibold text-foreground">Aksi Cepat</h3>
          <div class="grid grid-cols-1 gap-3">
            <RouterLink
              v-if="auth.can('activation.view')"
              to="/aktivasi"
              class="inline-flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 text-sm font-medium transition hover:bg-muted/30"
            >
              <Zap class="h-5 w-5 text-primary" />
              Aktivasi
            </RouterLink>
            <RouterLink
              v-if="auth.can('complaint.view')"
              to="/komplain"
              class="inline-flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 text-sm font-medium transition hover:bg-muted/30"
            >
              <Activity class="h-5 w-5 text-danger" />
              Komplain
            </RouterLink>
            <RouterLink
              v-if="auth.can('report.generate')"
              to="/report/generate"
              class="inline-flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 text-sm font-medium transition hover:bg-muted/30"
            >
              <FileText class="h-5 w-5 text-success" />
              Generate Report
            </RouterLink>
          </div>
        </Card>

        <Card v-if="showRecent" class="p-6">
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
    </div>
  </AppLayout>
</template>
