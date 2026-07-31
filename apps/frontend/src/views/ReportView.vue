<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import ChartCard from '@/components/dashboard/ChartCard.vue'
import Button from '@/components/ui/Button.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { reportApi, downloadCsv } from '@/services/api'
import { FileText, Download, Printer } from 'lucide-vue-next'

const loading = ref(true)
const exporting = ref(false)
const topAreas = ref<{ name: string; total: number }[]>([])
const complaintTrend = ref({ categories: [] as string[], data: [] as number[] })
const customerGrowth = ref({ categories: [] as string[], data: [] as number[] })
const activationVsDismantle = ref({ activations: 0, dismantles: 0 })

async function fetchAnalytics() {
  loading.value = true
  try {
    const { data } = await reportApi.analytics()
    topAreas.value = data.top_areas as { name: string; total: number }[]
    complaintTrend.value = data.complaint_trend as { categories: string[]; data: number[] }
    customerGrowth.value = data.customer_growth as { categories: string[]; data: number[] }
    activationVsDismantle.value = data.activation_vs_dismantle as { activations: number; dismantles: number }
  } finally {
    loading.value = false
  }
}

async function exportCsv(type: string) {
  exporting.value = true
  try {
    await downloadCsv(`/reports/export?type=${type}`, `report-${type}-${Date.now()}.csv`)
  } finally {
    exporting.value = false
  }
}

onMounted(fetchAnalytics)
</script>

<template>
  <AppLayout title="Dashboard Report" subtitle="Laporan analitik jaringan dan operasional">
    <div class="flex flex-wrap gap-2 mb-6">
      <div class="flex-1" />
      <Button variant="outline" size="sm" :disabled="exporting" @click="exportCsv('complaints')">
        <Download class="h-4 w-4" /> Export Komplain
      </Button>
      <Button variant="outline" size="sm" :disabled="exporting" @click="exportCsv('customers')">
        <Download class="h-4 w-4" /> Export Pelanggan
      </Button>
      <Button variant="outline" size="sm" :disabled="exporting" @click="exportCsv('routers')">
        <Download class="h-4 w-4" /> Export Router
      </Button>
      <Button variant="outline" size="sm"><FileText class="h-4 w-4" /> PDF</Button>
      <Button variant="outline" size="sm"><Printer class="h-4 w-4" /> Print</Button>
    </div>

    <div v-if="loading" class="grid gap-6 md:grid-cols-2">
      <Skeleton v-for="i in 4" :key="i" class="h-72 rounded-[18px]" />
    </div>

    <div v-else class="grid gap-6 md:grid-cols-2">
      <ChartCard
        title="Top Complaint Area"
        :categories="topAreas.map(a => a.name)"
        :series="[{ name: 'Pelanggan', data: topAreas.map(a => a.total), color: '#EF4444' }]"
        type="bar"
        :height="250"
      />
      <ChartCard
        title="Tren Komplain Harian"
        :categories="complaintTrend.categories"
        :series="[{ name: 'Komplain', data: complaintTrend.data, color: '#4F46E5' }]"
        :height="250"
      />
      <ChartCard
        title="Pertumbuhan Pelanggan"
        :categories="customerGrowth.categories"
        :series="[{ name: 'Pelanggan', data: customerGrowth.data, color: '#22C55E' }]"
        type="line"
        :height="250"
      />
      <ChartCard
        title="Aktivasi vs Dismantle"
        :categories="['Aktivasi', 'Dismantle']"
        :series="[{
          name: 'Total',
          data: [activationVsDismantle.activations, activationVsDismantle.dismantles],
          color: '#3B82F6',
        }]"
        type="bar"
        :height="250"
      />
    </div>
  </AppLayout>
</template>
