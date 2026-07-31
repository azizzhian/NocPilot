<script setup lang="ts">
import { computed } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
import { useAppStore } from '@/stores/app'
import Card from '@/components/ui/Card.vue'

const props = defineProps<{
  title: string
  subtitle?: string
  categories: string[]
  series: { name: string; data: number[]; color?: string }[]
  type?: 'area' | 'line' | 'bar' | 'donut'
  height?: number
  unit?: string
  horizontal?: boolean
  colors?: string[]
}>()

const appStore = useAppStore()
const isDonut = computed(() => props.type === 'donut')

const chartOptions = computed(() => {
  const palette = props.colors?.length
    ? props.colors
    : props.series.map((s) => s.color ?? '#4F46E5')

  if (isDonut.value) {
    return {
      chart: {
        type: 'donut' as const,
        toolbar: { show: false },
        fontFamily: 'Inter, sans-serif',
        background: 'transparent',
        animations: { enabled: true, easing: 'easeinout', speed: 800 },
      },
      labels: props.categories,
      colors: palette.length ? palette : ['#22C55E', '#EF4444', '#F59E0B'],
      dataLabels: { enabled: true },
      legend: {
        position: 'bottom' as const,
        labels: { colors: '#94a3b8' },
      },
      stroke: { width: 0 },
      plotOptions: {
        pie: {
          donut: {
            size: '65%',
            labels: {
              show: true,
              total: {
                show: true,
                label: 'Total',
                color: '#94a3b8',
              },
            },
          },
        },
      },
      tooltip: {
        theme: (appStore.isDark ? 'dark' : 'light') as 'dark' | 'light',
      },
    }
  }

  return {
    chart: {
      type: props.type ?? 'area',
      toolbar: { show: false },
      zoom: { enabled: false },
      fontFamily: 'Inter, sans-serif',
      background: 'transparent',
      animations: { enabled: true, easing: 'easeinout', speed: 800 },
    },
    colors: palette,
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth' as const, width: props.type === 'bar' ? 0 : 2 },
    fill: props.type === 'bar'
      ? { type: 'solid' as const, opacity: 0.9 }
      : {
          type: 'gradient',
          gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] },
        },
    plotOptions: {
      bar: {
        horizontal: props.horizontal ?? false,
        borderRadius: 4,
        columnWidth: '55%',
      },
    },
    grid: {
      borderColor: appStore.isDark ? '#1e293b' : '#f1f5f9',
      strokeDashArray: 4,
      xaxis: { lines: { show: false } },
    },
    xaxis: {
      categories: props.categories,
      labels: { style: { colors: '#94a3b8', fontSize: '11px' } },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: {
      labels: {
        style: { colors: '#94a3b8', fontSize: '11px' },
        formatter: (val: number) => `${val}${props.unit ?? ''}`,
      },
    },
    legend: {
      position: 'top' as const,
      horizontalAlign: 'right' as const,
      labels: { colors: '#94a3b8' },
      markers: { size: 4, shape: 'circle' as const },
    },
    tooltip: {
      theme: (appStore.isDark ? 'dark' : 'light') as 'dark' | 'light',
      x: { show: true },
    },
  }
})

const chartSeries = computed(() => {
  if (isDonut.value) {
    return props.series[0]?.data ?? []
  }
  return props.series.map((s) => ({ name: s.name, data: s.data }))
})
</script>

<template>
  <Card>
    <div class="mb-4 flex items-center justify-between">
      <div>
        <h3 class="text-sm font-semibold">{{ title }}</h3>
        <p v-if="subtitle" class="text-xs text-muted">{{ subtitle }}</p>
      </div>
    </div>
    <div
      v-if="!categories.length || (isDonut ? !(series[0]?.data?.some((n) => n > 0)) : !series.some((s) => s.data.some((n) => n > 0)))"
      class="flex h-[220px] items-center justify-center text-sm text-muted"
    >
      Belum ada data untuk grafik ini.
    </div>
    <VueApexCharts
      v-else
      :type="type ?? 'area'"
      :height="height ?? 280"
      :options="chartOptions"
      :series="chartSeries"
    />
  </Card>
</template>
