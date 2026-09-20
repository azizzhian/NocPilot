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
  type?: 'area' | 'line' | 'bar' | 'donut' | 'radar' | 'pie'
  height?: number
  unit?: string
  horizontal?: boolean
  stacked?: boolean
  colors?: string[]
  /** Aktifkan klik data point (bar/area/line) */
  clickable?: boolean
}>()

const emit = defineEmits<{
  pointClick: [payload: {
    category: string
    seriesName: string
    value: number
    seriesIndex: number
    dataPointIndex: number
  }]
}>()

const appStore = useAppStore()
const isDonut = computed(() => props.type === 'donut' || props.type === 'pie')
const isRadar = computed(() => props.type === 'radar')

function emitPointClick(seriesIndex: number, dataPointIndex: number) {
  if (!props.clickable || isDonut.value || isRadar.value) return
  if (seriesIndex < 0 || dataPointIndex < 0) return

  const category = props.categories[dataPointIndex] ?? ''
  const seriesName = props.series[seriesIndex]?.name ?? ''
  const value = Number(props.series[seriesIndex]?.data[dataPointIndex] ?? 0)
  if (!category || !seriesName) return

  emit('pointClick', { category, seriesName, value, seriesIndex, dataPointIndex })
}

const chartEvents = computed(() => {
  if (!props.clickable || isDonut.value || isRadar.value) return undefined

  const handle = (
    _event: unknown,
    _ctx: unknown,
    config: { seriesIndex?: number; dataPointIndex?: number },
  ) => {
    const seriesIndex = Number(config.seriesIndex ?? -1)
    const dataPointIndex = Number(config.dataPointIndex ?? -1)
    if (dataPointIndex < 0) return
    emitPointClick(Math.max(0, seriesIndex), dataPointIndex)
  }

  return {
    dataPointSelection: handle,
    click: handle,
  }
})

const chartOptions = computed(() => {
  const palette = props.colors?.length
    ? props.colors
    : props.series.map((s) => s.color ?? '#4F46E5')

  if (isDonut.value) {
    return {
      chart: {
        type: (props.type === 'pie' ? 'pie' : 'donut') as 'pie' | 'donut',
        toolbar: { show: false },
        fontFamily: 'Inter, sans-serif',
        background: 'transparent',
        animations: { enabled: true, easing: 'easeinout', speed: 800 },
      },
      labels: props.categories,
      colors: palette.length ? palette : ['#22C55E', '#EF4444', '#F59E0B'],
      dataLabels: {
        enabled: true,
        formatter: (val: number) => `${Math.round(val)}%`,
      },
      legend: {
        position: 'bottom' as const,
        labels: { colors: '#94a3b8' },
      },
      stroke: { width: 0 },
      plotOptions: {
        pie: {
          donut: {
            size: props.type === 'pie' ? '0%' : '65%',
            labels: {
              show: props.type !== 'pie',
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
        y: {
          formatter: (val: number) => `${val}${props.unit ?? '%'}`,
        },
      },
    }
  }

  if (isRadar.value) {
    return {
      chart: {
        type: 'radar' as const,
        toolbar: { show: false },
        fontFamily: 'Inter, sans-serif',
        background: 'transparent',
        animations: { enabled: true, easing: 'easeinout', speed: 800 },
      },
      colors: palette,
      xaxis: {
        categories: props.categories,
        labels: { style: { colors: Array(props.categories.length).fill('#94a3b8'), fontSize: '11px' } },
      },
      yaxis: { show: false },
      stroke: { width: 2 },
      fill: { opacity: 0.15 },
      markers: { size: 3 },
      legend: {
        position: 'bottom' as const,
        labels: { colors: '#94a3b8' },
      },
      tooltip: {
        theme: (appStore.isDark ? 'dark' : 'light') as 'dark' | 'light',
      },
    }
  }

  return {
    chart: {
      type: props.type ?? 'area',
      stacked: props.stacked ?? false,
      toolbar: { show: false },
      zoom: { enabled: false },
      fontFamily: 'Inter, sans-serif',
      background: 'transparent',
      animations: { enabled: true, easing: 'easeinout', speed: 800 },
      events: chartEvents.value,
    },
    colors: palette,
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth' as const, width: props.type === 'bar' ? 0 : 2 },
    fill: props.type === 'bar'
      ? { type: 'solid' as const, opacity: 0.95 }
      : {
          type: 'gradient',
          gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] },
        },
    plotOptions: {
      bar: {
        horizontal: props.horizontal ?? false,
        borderRadius: props.stacked ? 2 : 4,
        columnWidth: '55%',
        barHeight: '70%',
      },
    },
    grid: {
      borderColor: appStore.isDark ? '#1e293b' : '#f1f5f9',
      strokeDashArray: 4,
      xaxis: { lines: { show: false } },
    },
    xaxis: {
      categories: props.categories,
      labels: {
        style: {
          colors: appStore.isDark ? '#94a3b8' : '#334155',
          fontSize: '11px',
          fontWeight: 500,
        },
      },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: {
      labels: {
        style: {
          colors: appStore.isDark ? '#94a3b8' : '#334155',
          fontSize: '11px',
          fontWeight: 500,
        },
        formatter: (val: number) => `${val}${props.unit ?? ''}`,
      },
    },
    legend: {
      position: 'top' as const,
      horizontalAlign: 'right' as const,
      labels: { colors: appStore.isDark ? '#94a3b8' : '#64748B' },
      markers: { size: 4, shape: 'circle' as const },
    },
    tooltip: {
      theme: (appStore.isDark ? 'dark' : 'light') as 'dark' | 'light',
      x: { show: true },
    },
    states: props.clickable
      ? {
          active: {
            filter: { type: 'none' as const },
          },
        }
      : undefined,
  }
})

const chartSeries = computed(() => {
  if (isDonut.value) {
    return props.series[0]?.data ?? []
  }
  return props.series.map((s) => ({ name: s.name, data: s.data }))
})

const hasData = computed(() => {
  if (!props.categories.length && !isRadar.value) return false
  if (isDonut.value) return props.series[0]?.data?.some((n) => n > 0) ?? false
  if (isRadar.value) return props.series.some((s) => s.data.some((n) => n > 0))
  return props.series.some((s) => s.data.some((n) => n > 0))
})
</script>

<template>
  <Card>
    <div class="mb-4 flex items-center justify-between">
      <div>
        <h3 class="text-sm font-semibold">{{ title }}</h3>
        <p v-if="subtitle" class="text-xs text-muted">{{ subtitle }}</p>
        <p v-if="clickable && hasData" class="mt-0.5 text-[11px] text-muted">
          Klik bar untuk membuka detail data
        </p>
      </div>
    </div>
    <div
      v-if="!hasData"
      class="flex h-[220px] items-center justify-center text-sm text-muted"
    >
      Belum ada data untuk grafik ini.
    </div>
    <div v-else :class="clickable ? 'cursor-pointer' : ''">
      <VueApexCharts
        :type="type ?? 'area'"
        :height="height ?? 280"
        :options="chartOptions"
        :series="chartSeries"
      />
    </div>
  </Card>
</template>
