<script setup lang="ts">
import ResourceListView from '@/components/network/ResourceListView.vue'
import { packageApi } from '@/services/api'
import { formatNumber } from '@/lib/utils'

const columns = [
  { key: 'name', label: 'Nama Paket' },
  { key: 'speed_mbps', label: 'Kecepatan', render: (r: Record<string, unknown>) => `${r.speed_mbps} Mbps` },
  { key: 'price', label: 'Harga', render: (r: Record<string, unknown>) => `Rp ${formatNumber(Number(r.price ?? 0))}` },
  { key: 'status', label: 'Status' },
]

const fields = [
  { key: 'name', label: 'Nama Paket', required: true },
  { key: 'speed_mbps', label: 'Kecepatan (Mbps)', type: 'number' as const, required: true },
  { key: 'price', label: 'Harga (Rp)', type: 'number' as const },
  { key: 'status', label: 'Status', type: 'select' as const, options: [
    { value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' },
  ]},
  { key: 'description', label: 'Deskripsi', type: 'textarea' as const },
]
</script>

<template>
  <ResourceListView
    title="Paket Internet"
    subtitle="Master data paket layanan"
    :columns="columns"
    :fields="fields"
    :list="packageApi.list"
    :create="packageApi.create"
    :update="packageApi.update"
    :destroy="packageApi.destroy"
  />
</template>
