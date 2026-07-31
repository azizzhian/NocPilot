<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Textarea from '@/components/ui/Textarea.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { generateReportApi } from '@/services/api'
import { copyToClipboard } from '@/lib/copy'
import { cn } from '@/lib/utils'

type Tab = 'daily' | 'noc' | 'monitoring'

const route = useRoute()
const loading = ref(true)
const error = ref('')
const success = ref('')
const tab = ref<Tab>('daily')

const snapshot = ref<{
  report_date: string
  responsible_name: string
  daily_report_text: string
  noc_update_text: string
  monitoring_report_text: string
} | null>(null)

const activeText = computed(() => {
  if (!snapshot.value) return ''
  if (tab.value === 'daily') return snapshot.value.daily_report_text
  if (tab.value === 'noc') return snapshot.value.noc_update_text
  return snapshot.value.monitoring_report_text || ''
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const id = Number(route.params.id)
    const { data } = await generateReportApi.show(id)
    snapshot.value = data.data
  } catch {
    error.value = 'Gagal memuat detail report.'
  } finally {
    loading.value = false
  }
}

async function copyText() {
  if (!activeText.value.trim()) {
    error.value = 'Teks report kosong.'
    return
  }
  try {
    await copyToClipboard(activeText.value)
    success.value = 'Teks berhasil disalin!'
    setTimeout(() => { success.value = '' }, 3000)
  } catch (e) {
    error.value = (e as Error)?.message || 'Gagal menyalin teks.'
  }
}

onMounted(load)
</script>

<template>
  <AppLayout
    :title="snapshot ? `Report ${snapshot.report_date}` : 'Detail Report'"
    :subtitle="snapshot?.responsible_name"
  >
    <div class="mb-4">
      <RouterLink to="/report/history" class="text-sm text-primary hover:underline">← Kembali ke Histori</RouterLink>
    </div>

    <div v-if="error" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
      {{ error }}
    </div>
    <div v-if="success" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
      {{ success }}
    </div>

    <Skeleton v-if="loading" class="h-96 rounded-[18px]" />

    <template v-else-if="snapshot">
      <div class="mb-4 flex flex-wrap gap-2">
        <button
          v-for="t in ([
            { key: 'daily', label: 'Daily' },
            { key: 'noc', label: 'NOC' },
            { key: 'monitoring', label: 'Monitoring' },
          ] as const)"
          :key="t.key"
          type="button"
          :class="cn(
            'rounded-xl px-4 py-2 text-sm font-medium transition',
            tab === t.key
              ? 'bg-primary text-primary-foreground'
              : 'border border-border bg-card text-foreground hover:bg-muted',
          )"
          @click="tab = t.key"
        >
          {{ t.label }}
        </button>
        <Button class="ml-auto bg-emerald-600 hover:bg-emerald-700" @click="copyText">Copy</Button>
      </div>

      <Card class="p-4">
        <Textarea :model-value="activeText" readonly :rows="24" class="font-mono text-sm" />
      </Card>
    </template>
  </AppLayout>
</template>
