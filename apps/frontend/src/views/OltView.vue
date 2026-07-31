<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import ResourceListView from '@/components/network/ResourceListView.vue'
import { oltApi, popApi } from '@/services/api'

const popOptions = ref<{ value: number; label: string }[]>([])

onMounted(async () => {
  const { data } = await popApi.list({ per_page: 100 })
  popOptions.value = (data.data as { id: number; name: string }[]).map((p) => ({ value: p.id, label: p.name }))
})

const columns = [
  { key: 'name', label: 'Nama' },
  { key: 'ip', label: 'IP' },
  { key: 'pop', label: 'POP', render: (r: Record<string, unknown>) => (r.pop as { name?: string })?.name ?? '—' },
  { key: 'status', label: 'Status' },
  { key: 'pon_ports', label: 'PON Ports' },
]

const fields = computed(() => [
  { key: 'pop_id', label: 'POP', type: 'select' as const, required: true, options: popOptions.value },
  { key: 'name', label: 'Nama OLT', required: true },
  { key: 'ip', label: 'IP Address' },
  { key: 'status', label: 'Status', type: 'select' as const, options: [
    { value: 'online', label: 'Online' }, { value: 'offline', label: 'Offline' }, { value: 'maintenance', label: 'Maintenance' },
  ]},
  { key: 'capacity', label: 'Kapasitas', type: 'number' as const, default: 128 },
  { key: 'pon_ports', label: 'Jumlah PON', type: 'number' as const, default: 8 },
])
</script>

<template>
  <ResourceListView
    title="OLT"
    subtitle="Manajemen Optical Line Terminal"
    :columns="columns"
    :fields="fields"
    :list="oltApi.list"
    :create="oltApi.create"
    :update="oltApi.update"
    :destroy="oltApi.destroy"
  />
</template>
