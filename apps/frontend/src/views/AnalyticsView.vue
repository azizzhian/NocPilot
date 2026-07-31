<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import ChartCard from '@/components/dashboard/ChartCard.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { reportApi } from '@/services/api'
import { TrendingUp, AlertTriangle } from 'lucide-vue-next'

const loading = ref(true)
const revenue = ref({ categories: [] as string[], data: [] as number[] })
const customerGrowth = ref({ categories: [] as string[], data: [] as number[] })
const complaintTrend = ref({ categories: [] as string[], data: [] as number[] })
const ticketByPriority = ref({ critical: 0, high: 0, medium: 0, low: 0 })
const activationVsDismantle = ref({ activations: 0, dismantles: 0 })

onMounted(async () => {
  try {
    const { data } = await reportApi.analytics()
    revenue.value = data.revenue as { categories: string[]; data: number[] }
    customerGrowth.value = data.customer_growth as { categories: string[]; data: number[] }
    complaintTrend.value = data.complaint_trend as { categories: string[]; data: number[] }
    ticketByPriority.value = data.ticket_by_priority as typeof ticketByPriority.value
    activationVsDismantle.value = data.activation_vs_dismantle as typeof activationVsDismantle.value
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <AppLayout title="Analytics" subtitle="Business Intelligence Dashboard">
    <div v-if="loading" class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
      <Skeleton v-for="i in 6" :key="i" class="h-64 rounded-[18px]" />
    </div>

    <template v-else>
      <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        <ChartCard
          title="Revenue (Juta Rp)"
          :categories="revenue.categories"
          :series="[{ name: 'Rp (Juta)', data: revenue.data, color: '#22C55E' }]"
          type="area"
          :height="220"
        />
        <ChartCard
          title="Pertumbuhan Pelanggan"
          :categories="customerGrowth.categories"
          :series="[{ name: 'Pelanggan', data: customerGrowth.data, color: '#4F46E5' }]"
          type="line"
          :height="220"
        />
        <ChartCard
          title="Tren Komplain"
          :categories="complaintTrend.categories"
          :series="[{ name: 'Ticket', data: complaintTrend.data, color: '#EF4444' }]"
          type="line"
          :height="220"
        />
        <ChartCard
          title="Ticket by Priority"
          :categories="['Critical', 'High', 'Medium', 'Low']"
          :series="[{
            name: 'Ticket',
            data: [ticketByPriority.critical, ticketByPriority.high, ticketByPriority.medium, ticketByPriority.low],
            color: '#F59E0B',
          }]"
          type="bar"
          :height="220"
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
          :height="220"
        />
        <ChartCard
          title="Revenue Trend"
          :categories="revenue.categories"
          :series="[{ name: 'Growth %', data: revenue.data.map((v, i, arr) => i ? Math.round(((v - arr[i-1]) / arr[i-1]) * 100) : 0), color: '#8B5CF6' }]"
          type="line"
          :height="220"
          unit="%"
        />
      </div>

      <div class="mt-6 grid gap-6 md:grid-cols-3">
        <Card>
          <div class="flex items-center gap-2 mb-4">
            <TrendingUp class="h-4 w-4 text-primary" />
            <h3 class="text-sm font-semibold">Total Pelanggan</h3>
          </div>
          <p class="text-2xl font-bold text-primary">{{ customerGrowth.data.at(-1) ?? 0 }}</p>
          <p class="text-xs text-muted mt-1">Akumulasi pelanggan terdaftar</p>
        </Card>
        <Card>
          <div class="flex items-center gap-2 mb-4">
            <TrendingUp class="h-4 w-4 text-info" />
            <h3 class="text-sm font-semibold">Aktivasi Bulan Ini</h3>
          </div>
          <p class="text-2xl font-bold text-info">{{ activationVsDismantle.activations }}</p>
          <p class="text-xs text-muted mt-1">Total permintaan aktivasi</p>
        </Card>
        <Card>
          <div class="flex items-center gap-2 mb-4">
            <AlertTriangle class="h-4 w-4 text-warning" />
            <h3 class="text-sm font-semibold">Ticket Critical</h3>
            <Badge variant="warning">{{ ticketByPriority.critical }}</Badge>
          </div>
          <div class="space-y-2 text-sm text-muted">
            <p>High: {{ ticketByPriority.high }}</p>
            <p>Medium: {{ ticketByPriority.medium }}</p>
            <p>Low: {{ ticketByPriority.low }}</p>
          </div>
        </Card>
      </div>
    </template>
  </AppLayout>
</template>
