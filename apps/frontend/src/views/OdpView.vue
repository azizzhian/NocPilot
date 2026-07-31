<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import ResourceListView from '@/components/network/ResourceListView.vue'
import { odpApi, odcApi } from '@/services/api'

const odcOptions = ref<{ value: number; label: string }[]>([])

onMounted(async () => {
  const { data } = await odcApi.list({ per_page: 100 })
  odcOptions.value = (data.data as { id: number; name: string }[]).map((o) => ({ value: o.id, label: o.name }))
})

const columns = [
  { key: 'name', label: 'Nama' },
  { key: 'code', label: 'Kode' },
  { key: 'odc', label: 'ODC', render: (r: Record<string, unknown>) => (r.odc as { name?: string })?.name ?? '—' },
  { key: 'status', label: 'Status' },
  { key: 'used_ports', label: 'Terpakai' },
  { key: 'capacity', label: 'Kapasitas' },
]

const fields = computed(() => [
  { key: 'odc_id', label: 'ODC', type: 'select' as const, required: true, options: odcOptions.value },
  { key: 'name', label: 'Nama ODP', required: true },
  { key: 'code', label: 'Kode', required: true },
  { key: 'status', label: 'Status', type: 'select' as const, options: [
    { value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' },
    { value: 'full', label: 'Full' }, { value: 'maintenance', label: 'Maintenance' },
  ]},
  { key: 'capacity', label: 'Kapasitas Port', type: 'number' as const, default: 16 },
  { key: 'used_ports', label: 'Port Terpakai', type: 'number' as const },
])
</script>

<template>
  <ResourceListView
    title="ODP"
    subtitle="Optical Distribution Point"
    :columns="columns"
    :fields="fields"
    :list="odpApi.list"
    :create="odpApi.create"
    :update="odpApi.update"
    :destroy="odpApi.destroy"
  />
</template>
