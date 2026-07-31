<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import ResourceListView from '@/components/network/ResourceListView.vue'
import { onuApi, odpApi, oltApi } from '@/services/api'

const odpOptions = ref<{ value: number; label: string }[]>([])
const oltOptions = ref<{ value: number; label: string }[]>([])

onMounted(async () => {
  const [odpRes, oltRes] = await Promise.all([
    odpApi.list({ per_page: 100 }),
    oltApi.list({ per_page: 100 }),
  ])
  odpOptions.value = (odpRes.data.data as { id: number; name: string }[]).map((o) => ({ value: o.id, label: o.name }))
  oltOptions.value = (oltRes.data.data as { id: number; name: string }[]).map((o) => ({ value: o.id, label: o.name }))
})

const columns = [
  { key: 'name', label: 'Nama' },
  { key: 'serial', label: 'Serial' },
  { key: 'status', label: 'Status' },
  { key: 'rx_power', label: 'RX (dBm)' },
  { key: 'odp', label: 'ODP', render: (r: Record<string, unknown>) => (r.odp as { name?: string })?.name ?? '—' },
]

const fields = computed(() => [
  { key: 'name', label: 'Nama ONU', required: true },
  { key: 'serial', label: 'Serial Number' },
  { key: 'odp_id', label: 'ODP', type: 'select' as const, options: odpOptions.value },
  { key: 'olt_id', label: 'OLT', type: 'select' as const, options: oltOptions.value },
  { key: 'pon_port', label: 'PON Port' },
  { key: 'status', label: 'Status', type: 'select' as const, options: [
    { value: 'online', label: 'Online' }, { value: 'offline', label: 'Offline' },
    { value: 'los', label: 'LOS' }, { value: 'maintenance', label: 'Maintenance' },
  ]},
  { key: 'rx_power', label: 'RX Power', type: 'number' as const },
  { key: 'tx_power', label: 'TX Power', type: 'number' as const },
])
</script>

<template>
  <ResourceListView
    title="ONU"
    subtitle="Manajemen Optical Network Unit"
    :columns="columns"
    :fields="fields"
    :list="onuApi.list"
    :create="onuApi.create"
    :update="onuApi.update"
    :destroy="onuApi.destroy"
  />
</template>
