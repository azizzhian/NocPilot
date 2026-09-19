<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import ResourceListView from '@/components/network/ResourceListView.vue'
import { locationApi, odcApi } from '@/services/api'

const odcOptions = ref<{ value: number | ''; label: string }[]>([])

onMounted(async () => {
  const { data } = await odcApi.list({ per_page: 200 })
  odcOptions.value = [
    { value: '', label: '— Belum ditentukan —' },
    ...(data.data as { id: number; name: string }[]).map((o) => ({
      value: o.id,
      label: o.name,
    })),
  ]
})

const columns = [
  { key: 'name', label: 'Lokasi' },
  { key: 'odc', label: 'ODC', render: (r: Record<string, unknown>) => (r.odc as { name?: string })?.name ?? '—' },
  { key: 'status', label: 'Status' },
]

const fields = computed(() => [
  { key: 'name', label: 'Nama Lokasi', required: true },
  { key: 'odc_id', label: 'ODC', type: 'select' as const, options: odcOptions.value },
  { key: 'status', label: 'Status', type: 'select' as const, options: [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
  ]},
  { key: 'notes', label: 'Catatan', type: 'textarea' as const },
])
</script>

<template>
  <ResourceListView
    title="Lokasi"
    subtitle="Master lokasi tiket & dismantle — tetapkan ODC untuk tiap lokasi"
    :columns="columns"
    :fields="fields"
    :list="locationApi.list"
    :create="locationApi.create"
    :update="locationApi.update"
    :destroy="locationApi.destroy"
  />
</template>
