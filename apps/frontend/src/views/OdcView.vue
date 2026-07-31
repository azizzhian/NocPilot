<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import ResourceListView from '@/components/network/ResourceListView.vue'
import { odcApi, popApi } from '@/services/api'

const popOptions = ref<{ value: number; label: string }[]>([])

onMounted(async () => {
  const { data } = await popApi.list({ per_page: 100 })
  popOptions.value = (data.data as { id: number; name: string }[]).map((p) => ({ value: p.id, label: p.name }))
})

const columns = [
  { key: 'name', label: 'Nama' },
  { key: 'code', label: 'Kode' },
  { key: 'pop', label: 'POP', render: (r: Record<string, unknown>) => (r.pop as { name?: string })?.name ?? '—' },
  { key: 'status', label: 'Status' },
  { key: 'capacity', label: 'Kapasitas' },
]

const fields = computed(() => [
  { key: 'pop_id', label: 'POP', type: 'select' as const, required: true, options: popOptions.value },
  { key: 'name', label: 'Nama ODC', required: true },
  { key: 'code', label: 'Kode', required: true },
  { key: 'location', label: 'Lokasi' },
  { key: 'status', label: 'Status', type: 'select' as const, options: [
    { value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }, { value: 'maintenance', label: 'Maintenance' },
  ]},
  { key: 'capacity', label: 'Kapasitas', type: 'number' as const, default: 0 },
])
</script>

<template>
  <ResourceListView
    title="ODC"
    subtitle="Optical Distribution Cabinet"
    :columns="columns"
    :fields="fields"
    :list="odcApi.list"
    :create="odcApi.create"
    :update="odcApi.update"
    :destroy="odcApi.destroy"
  />
</template>
