<script setup lang="ts">
import { ref, watch } from 'vue'
import { dailyEntryApi } from '@/services/api'

const props = defineProps<{
  modelValue?: string
  label?: string
  placeholder?: string
  required?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
  select: [customer: {
    id: number
    name: string
    customer_code?: string
    phone?: string | null
    address?: string | null
    odc?: { id: number; name: string; code?: string } | null
  }]
}>()

const open = ref(false)
const loading = ref(false)
const results = ref<{
  id: number
  name: string
  customer_code?: string
  phone?: string | null
  address?: string | null
  odc?: { id: number; name: string; code?: string } | null
}[]>([])
const query = ref(props.modelValue ?? '')
let debounceTimer: ReturnType<typeof setTimeout>

watch(() => props.modelValue, (val) => {
  if (val !== query.value) query.value = val ?? ''
})

async function searchCustomers(q: string) {
  const term = q?.trim()
  if (!term || term.length < 2) {
    results.value = []
    return
  }
  loading.value = true
  try {
    const { data } = await dailyEntryApi.searchCustomers(term)
    results.value = data
  } catch {
    results.value = []
  } finally {
    loading.value = false
  }
}

function onInput(e: Event) {
  const val = (e.target as HTMLInputElement).value
  query.value = val
  emit('update:modelValue', val)
  open.value = val.trim().length > 0
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => searchCustomers(val), 300)
}

function selectCustomer(customer: typeof results.value[0]) {
  const display = customer.odc?.name
    ? `${customer.name} (${customer.odc.name})`
    : customer.address
      ? `${customer.name} (${customer.address})`
      : customer.name
  query.value = display
  emit('update:modelValue', display)
  emit('select', customer)
  open.value = false
  results.value = []
}

function onFocus() {
  if (query.value?.trim().length >= 2) {
    open.value = true
    searchCustomers(query.value)
  }
}

function onBlur() {
  setTimeout(() => { open.value = false }, 200)
}
</script>

<template>
  <div class="relative">
    <label v-if="label" class="mb-1.5 block text-sm font-medium">{{ label }}</label>
    <input
      :value="query"
      type="text"
      :required="required"
      :placeholder="placeholder ?? 'Nama'"
      class="form-control h-10 px-4 py-2"
      autocomplete="off"
      @input="onInput"
      @focus="onFocus"
      @blur="onBlur"
    />

    <div
      v-if="open && (loading || results.length > 0)"
      class="absolute z-50 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border border-[#E5E7EB] bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900"
    >
      <p v-if="loading" class="px-4 py-2.5 text-xs text-muted">Mencari...</p>
      <button
        v-for="c in results"
        :key="c.id"
        type="button"
        class="block w-full border-b border-[#F1F5F9] px-4 py-2.5 text-left text-sm transition hover:bg-[#F8FAFC] dark:border-slate-700 dark:hover:bg-slate-800"
        @mousedown.prevent="selectCustomer(c)"
      >
        <span class="font-medium text-[#111827] dark:text-slate-100">{{ c.name }}</span>
        <span v-if="c.customer_code" class="ml-1 text-xs text-muted">({{ c.customer_code }})</span>
        <span class="mt-0.5 block text-xs text-muted">
          <template v-if="c.phone">{{ c.phone }}</template>
          <template v-if="c.phone && (c.odc || c.address)"> · </template>
          <template v-if="c.odc">ODC: {{ c.odc.name }}</template>
          <template v-if="c.odc && c.address"> · </template>
          <template v-if="c.address">{{ c.address }}</template>
        </span>
      </button>
    </div>

    <p v-else-if="open && query?.trim().length >= 2 && !loading && results.length === 0" class="mt-1 text-xs text-muted">
      Tidak ada pelanggan yang cocok. Nama tetap bisa diketik manual.
    </p>
  </div>
</template>
