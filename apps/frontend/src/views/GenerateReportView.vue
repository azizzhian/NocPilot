<script setup lang="ts">
import { ref, computed, reactive, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Select from '@/components/ui/Select.vue'
import Textarea from '@/components/ui/Textarea.vue'
import { generateReportApi, type GenerateReportIndexData } from '@/services/api'
import { copyToClipboard } from '@/lib/copy'
import { todayInput } from '@/lib/date-input'
import { cn } from '@/lib/utils'

type PreviewTab = 'daily' | 'noc' | 'monitoring'
type ViewMode = 'preview' | 'template'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const generating = ref(false)
const savingTemplate = ref(false)
const error = ref('')
const success = ref('')

const reportDate = ref(todayInput())
const responsibleName = ref('')
const nocUsers = ref<{ id: number; name: string }[]>([])
const activityName = ref('')

const dailyText = ref('')
const nocText = ref('')
const monitoringText = ref('')

const activePreview = ref<PreviewTab>('daily')
const viewMode = ref<ViewMode>('preview')

const templateDrafts = reactive({ daily: '', noc: '', monitoring: '' })
const templateCustom = reactive({ daily: false, noc: false, monitoring: false })
const templateHints = reactive<Record<PreviewTab, Record<string, string>>>({
  daily: {},
  noc: {},
  monitoring: {},
})

const previewTitle = computed(() => {
  if (activePreview.value === 'daily') return 'Daily Report'
  if (activePreview.value === 'noc') return 'Update NOC'
  return 'Monitoring'
})

const activeText = computed(() => {
  if (activePreview.value === 'daily') return dailyText.value
  if (activePreview.value === 'noc') return nocText.value
  return monitoringText.value
})

const activeTemplateBody = computed({
  get: () => templateDrafts[activePreview.value],
  set: (value: string) => {
    templateDrafts[activePreview.value] = value
  },
})

const activeTemplateHints = computed(() => Object.entries(templateHints[activePreview.value] ?? {}))
const isTemplateCustom = computed(() => templateCustom[activePreview.value])

function applyIndexData(data: GenerateReportIndexData) {
  reportDate.value = data.date
  responsibleName.value = data.default_responsible
  nocUsers.value = data.noc_users
  activityName.value = data.activity_name

  if (data.snapshot) {
    dailyText.value = data.snapshot.daily_report_text ?? ''
    nocText.value = data.snapshot.noc_update_text ?? ''
    monitoringText.value = data.snapshot.monitoring_report_text ?? ''
    responsibleName.value = data.snapshot.responsible_name || data.default_responsible
  }

  ;(['daily', 'noc', 'monitoring'] as PreviewTab[]).forEach((type) => {
    const tpl = data.templates[type]
    if (tpl) {
      templateDrafts[type] = tpl.body
      templateCustom[type] = tpl.is_custom
      templateHints[type] = tpl.hints ?? {}
    }
  })
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const date = (route.query.date as string) || reportDate.value
    const { data } = await generateReportApi.index(date)
    applyIndexData(data)
  } catch {
    error.value = 'Gagal memuat halaman generate report.'
  } finally {
    loading.value = false
  }
}

function changeDate(e: Event) {
  const value = (e.target as HTMLInputElement).value
  reportDate.value = value
  router.replace({ query: { date: value } })
  load()
}

function flashSuccess(msg: string) {
  success.value = msg
  setTimeout(() => { success.value = '' }, 3000)
}

function extractErrorMessage(e: unknown, fallback: string) {
  const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
  const data = err.response?.data
  if (data?.message) return data.message
  if (data?.errors) return Object.values(data.errors).flat().join(' ')
  return fallback
}

async function generate() {
  if (!responsibleName.value.trim()) {
    error.value = 'Pilih nama penanggung jawab terlebih dahulu.'
    return
  }

  generating.value = true
  error.value = ''
  try {
    const { data } = await generateReportApi.generate({
      report_date: reportDate.value,
      responsible_name: responsibleName.value,
    })
    dailyText.value = data.daily_report_text
    nocText.value = data.noc_update_text
    monitoringText.value = data.monitoring_report_text
    viewMode.value = 'preview'
    flashSuccess(data.message || 'Report berhasil di-generate.')
  } catch (e) {
    error.value = extractErrorMessage(e, 'Gagal generate report.')
  } finally {
    generating.value = false
  }
}

async function saveTemplate() {
  savingTemplate.value = true
  error.value = ''
  try {
    await generateReportApi.updateTemplate(activePreview.value, activeTemplateBody.value)
    templateCustom[activePreview.value] = true
    flashSuccess('Template berhasil disimpan.')
  } catch (e) {
    error.value = extractErrorMessage(e, 'Gagal menyimpan template.')
  } finally {
    savingTemplate.value = false
  }
}

async function resetTemplate() {
  if (!confirm('Kembalikan template ini ke default bawaan?')) return

  savingTemplate.value = true
  error.value = ''
  try {
    const { data } = await generateReportApi.resetTemplate(activePreview.value)
    templateDrafts[activePreview.value] = data.body
    templateCustom[activePreview.value] = false
    flashSuccess('Template dikembalikan ke default.')
  } catch (e) {
    error.value = extractErrorMessage(e, 'Gagal reset template.')
  } finally {
    savingTemplate.value = false
  }
}

async function copyText(text: string) {
  if (!text.trim()) {
    error.value = 'Belum ada teks report. Klik Generate Report dulu.'
    return
  }
  try {
    await copyToClipboard(text)
    flashSuccess('Teks berhasil disalin!')
  } catch (e) {
    error.value = (e as Error)?.message || 'Gagal menyalin teks.'
  }
}

watch(
  () => route.query.date,
  (date) => {
    if (typeof date === 'string' && date !== reportDate.value) load()
  },
)

onMounted(load)
</script>

<template>
  <AppLayout title="Template Report" subtitle="Kelola template & generate full Daily + NOC + Monitoring (opsional)">
    <div v-if="error" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
      {{ error }}
    </div>
    <div v-if="success" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
      {{ success }}
    </div>

    <Card class="mb-6 grid gap-6 p-6 md:grid-cols-3">
      <div>
        <label class="mb-1 block text-sm font-medium text-foreground">Tanggal Report</label>
        <input type="date" :value="reportDate" class="form-control h-10 w-full rounded-xl px-4 py-2" @change="changeDate" />
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-foreground">Nama Penanggung Jawab</label>
        <Select
          v-model="responsibleName"
          class="w-full"
          :options="nocUsers.map((u) => ({ value: u.name, label: u.name }))"
        />
      </div>
      <div class="flex items-end">
        <Button class="w-full justify-center" :disabled="generating || loading" @click="generate">
          {{ generating ? 'Sync router & generate...' : 'Generate Report' }}
        </Button>
      </div>
    </Card>

    <div class="mb-4 flex flex-wrap items-center gap-2">
      <button
        v-for="tab in ([
          { key: 'daily', label: 'Daily Report' },
          { key: 'noc', label: 'Update NOC' },
          { key: 'monitoring', label: 'Monitoring' },
        ] as const)"
        :key="tab.key"
        type="button"
        :class="cn(
          'rounded-xl px-4 py-2 text-sm font-medium transition',
          activePreview === tab.key
            ? 'bg-primary text-primary-foreground shadow-sm'
            : 'border border-border bg-card text-foreground hover:bg-muted',
        )"
        @click="activePreview = tab.key"
      >
        {{ tab.label }}
      </button>

      <span class="mx-1 hidden h-6 w-px bg-border sm:inline" />

      <button
        type="button"
        :class="cn(
          'rounded-xl px-4 py-2 text-sm font-medium transition',
          viewMode === 'preview'
            ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900'
            : 'border border-border bg-card text-foreground hover:bg-muted',
        )"
        @click="viewMode = 'preview'"
      >
        Preview Hasil
      </button>
      <button
        type="button"
        :class="cn(
          'rounded-xl px-4 py-2 text-sm font-medium transition',
          viewMode === 'template'
            ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900'
            : 'border border-border bg-card text-foreground hover:bg-muted',
        )"
        @click="viewMode = 'template'"
      >
        Edit Template
        <span v-if="isTemplateCustom" class="ml-1 text-[10px] text-amber-400">●</span>
      </button>
    </div>

    <Card v-if="viewMode === 'preview'" class="p-6">
      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="font-semibold text-foreground">{{ previewTitle }}</h2>
          <p v-if="activityName" class="mt-0.5 text-xs text-muted-foreground">{{ activityName }}</p>
        </div>
        <Button variant="outline" class="border-emerald-600 text-emerald-700 hover:bg-emerald-50 dark:text-emerald-400" @click="copyText(activeText)">
          Salin ke Clipboard
        </Button>
      </div>
      <p v-if="activePreview === 'monitoring'" class="mb-3 rounded-xl bg-blue-50 px-3 py-2 text-xs text-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
        Saat Generate Report, router disinkron otomatis dulu, lalu traffic ditampilkan per interface yang dicentang di Monitoring.
      </p>
      <Textarea
        :model-value="activeText"
        readonly
        :rows="24"
        class="font-mono text-sm"
        placeholder="Klik Generate Report untuk membuat teks..."
      />
    </Card>

    <div v-else class="grid gap-6 xl:grid-cols-12">
      <Card class="p-6 xl:col-span-8">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="font-semibold text-foreground">Template {{ previewTitle }}</h2>
            <p class="mt-0.5 text-xs text-muted-foreground">
              {{ isTemplateCustom ? 'Menggunakan template kustom.' : 'Menggunakan template default bawaan.' }}
            </p>
          </div>
          <div class="flex flex-wrap gap-2">
            <Button variant="outline" :disabled="savingTemplate" @click="resetTemplate">Reset Default</Button>
            <Button :disabled="savingTemplate" @click="saveTemplate">
              {{ savingTemplate ? 'Menyimpan...' : 'Simpan Template' }}
            </Button>
          </div>
        </div>

        <p class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
          Sesuaikan label, urutan, atau format teks. Gunakan placeholder seperti
          <code v-pre class="font-mono">{{responsible_name}}</code>
          dan blok loop
          <code v-pre class="font-mono">{{#activations}}</code> …
          <code v-pre class="font-mono">{{/activations}}</code>.
          Setelah disimpan, klik <strong>Generate Report</strong> untuk melihat hasil baru.
        </p>

        <Textarea v-model="activeTemplateBody" :rows="28" class="font-mono text-sm" />
      </Card>

      <Card class="p-5 xl:col-span-4">
        <h3 class="mb-3 text-sm font-semibold text-foreground">Placeholder Tersedia</h3>
        <ul class="max-h-[32rem] space-y-2 overflow-y-auto text-xs">
          <li
            v-for="([token, desc], idx) in activeTemplateHints"
            :key="idx"
            class="rounded-lg border border-border p-2.5"
          >
            <code class="block break-all font-mono text-primary">{{ token }}</code>
            <span class="mt-1 block text-muted-foreground">{{ desc }}</span>
          </li>
        </ul>
        <p v-if="activePreview === 'monitoring'" class="mt-4 text-xs text-muted-foreground">
          Template monitoring memakai blok <code class="font-mono">@@core@@</code>, <code class="font-mono">@@multi@@</code>, dll.
          untuk format berbeda per tipe router.
        </p>
      </Card>
    </div>
  </AppLayout>
</template>
