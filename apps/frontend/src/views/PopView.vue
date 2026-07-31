<script setup lang="ts">
import ResourceListView from '@/components/network/ResourceListView.vue'
import { popApi } from '@/services/api'

const columns = [
  { key: 'name', label: 'Nama' },
  { key: 'code', label: 'Kode' },
  { key: 'area', label: 'Area' },
  { key: 'status', label: 'Status' },
  { key: 'capacity', label: 'Kapasitas' },
]

const fields = [
  { key: 'name', label: 'Nama POP', required: true },
  { key: 'code', label: 'Kode', required: true },
  { key: 'area', label: 'Area' },
  { key: 'status', label: 'Status', type: 'select' as const, options: [
    { value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' },
  ]},
  { key: 'capacity', label: 'Kapasitas', type: 'number' as const, default: 0 },
  { key: 'address', label: 'Alamat', type: 'textarea' as const },
]
</script>

<template>
  <ResourceListView
    title="POP"
    subtitle="Point of Presence management"
    :columns="columns"
    :fields="fields"
    :list="popApi.list"
    :create="popApi.create"
    :update="popApi.update"
    :destroy="popApi.destroy"
  />
</template>
