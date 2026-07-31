<script setup lang="ts">
import ResourceListView from '@/components/network/ResourceListView.vue'
import { routerInventoryApi } from '@/services/api'

async function testConnection(id: number, opts?: {
  username?: string
  password?: string
  ip?: string
  api_port?: number
  monitor_via?: string
  snmp_community?: string
  snmp_port?: number
}) {
  const res = await routerInventoryApi.testConnection(id, opts)
  return res.data
}

const columns = [
  { key: 'name', label: 'Nama' },
  { key: 'ip', label: 'IP' },
  {
    key: 'monitor_via',
    label: 'Mode',
    render: (row: Record<string, unknown>) =>
      row.monitor_via === 'snmp' ? 'SNMP' : 'API',
  },
  { key: 'api_port', label: 'Port API' },
  { key: 'pop', label: 'POP' },
  { key: 'status', label: 'Status' },
  { key: 'cpu', label: 'CPU %' },
  { key: 'pppoe_sessions', label: 'PPPoE' },
]

const fields = [
  { key: 'name', label: 'Nama Router', required: true },
  { key: 'ip', label: 'IP Address', required: true },
  {
    key: 'monitor_via',
    label: 'Mode Monitoring',
    type: 'select' as const,
    default: 'api',
    options: [
      { value: 'api', label: 'MikroTik API' },
      { value: 'snmp', label: 'SNMP v2c' },
    ],
  },
  {
    key: 'api_port',
    label: 'Port Service API',
    type: 'number' as const,
    default: 8728,
    placeholder: '8728 (API), 8729 (API-SSL)',
    hiddenWhen: (f: Record<string, unknown>) => f.monitor_via === 'snmp',
  },
  { key: 'pop', label: 'POP' },
  { key: 'area', label: 'Area' },
  {
    key: 'username',
    label: 'API Username',
    hiddenWhen: (f: Record<string, unknown>) => f.monitor_via === 'snmp',
  },
  {
    key: 'password',
    label: 'API Password',
    type: 'password' as const,
    placeholder: 'Wajib untuk mode API. Kosongkan saat edit jika tidak diubah.',
    hiddenWhen: (f: Record<string, unknown>) => f.monitor_via === 'snmp',
  },
  {
    key: 'snmp_community',
    label: 'SNMP Community',
    type: 'text' as const,
    placeholder: 'Wajib untuk mode SNMP. Kosongkan saat edit jika tidak diubah.',
    hiddenWhen: (f: Record<string, unknown>) => f.monitor_via !== 'snmp',
  },
  {
    key: 'snmp_port',
    label: 'Port SNMP',
    type: 'number' as const,
    default: 161,
    hiddenWhen: (f: Record<string, unknown>) => f.monitor_via !== 'snmp',
  },
  {
    key: 'snmp_timeout',
    label: 'Timeout SNMP (detik)',
    type: 'number' as const,
    default: 3,
    hiddenWhen: (f: Record<string, unknown>) => f.monitor_via !== 'snmp',
  },
]
</script>

<template>
  <ResourceListView
    title="Router MikroTik"
    subtitle="Monitoring via MikroTik API atau SNMP"
    :columns="columns"
    :fields="fields"
    :list="routerInventoryApi.list"
    :create="routerInventoryApi.create"
    :update="routerInventoryApi.update"
    :destroy="routerInventoryApi.destroy"
    :test-connection="testConnection"
  />
</template>
