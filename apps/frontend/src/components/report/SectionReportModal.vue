<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import DateRangePicker from '@/components/ui/DateRangePicker.vue'
import { generateReportApi, type ReportSection } from '@/services/api'
import { copyToClipboard } from '@/lib/copy'
import { todayInput } from '@/lib/date-input'
import { FileText, Copy } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    open: boolean
    section: ReportSection
    title?: string
    /** Prefill tanggal tunggal (YYYY-MM-DD) */
    date?: string
    /** Prefill rentang */
    from?: string
    to?: string
  }>(),
  {
    title: '',
    date: '',
    from: '',
    to: '',
  },
)

const emit = defineEmits<{ 'update:open': [value: boolean] }>()

const fromDate = ref(todayInput())
const toDate = ref(todayInput())
const text = ref('')
const generating = ref(false)
const copying = ref(false)
const error = ref('')
const success = ref('')

const sectionLabels: Record<ReportSection, string> = {
  complaint: 'Komplain',
  activation: 'Aktivasi',
  cctv: 'CCTV',
  noc: 'Update NOC',
  dismantle: 'Dismantle',
  ticket: 'Ticket',
  monitoring: 'Monitoring',
}

const modalTitle = computed(
  () => props.title || `Generate Report ${sectionLabels[props.section] ?? props.section}`,
)

watch(
  () => props.open,
  (open) => {
    if (!open) return
    const start = props.from || props.date || todayInput()
    const end = props.to || props.from || props.date || start
    fromDate.value = start
    toDate.value = end
    text.value = ''
    error.value = ''
    success.value = ''
  },
)

function close() {
  emit('update:open', false)
}

function flash(msg: string) {
  success.value = msg
  setTimeout(() => {
    success.value = ''
  }, 2500)
}

async function generate() {
  if (!fromDate.value || !toDate.value) {
    error.value = 'Pilih periode terlebih dahulu.'
    return
  }
  generating.value = true
  error.value = ''
  success.value = ''
  try {
    const { data } = await generateReportApi.generateSection({
      section: props.section,
      from: fromDate.value,
      to: toDate.value,
    })
    text.value = data.text ?? ''
    flash(data.message || 'Berhasil di-generate.')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? 'Gagal generate report.'
  } finally {
    generating.value = false
  }
}

async function copy() {
  if (!text.value.trim()) {
    error.value = 'Belum ada teks. Klik Generate dulu.'
    return
  }
  copying.value = true
  error.value = ''
  try {
    await copyToClipboard(text.value)
    flash('Teks berhasil disalin.')
  } catch {
    error.value = 'Gagal menyalin teks.'
  } finally {
    copying.value = false
  }
}
</script>

<template>
  <Modal :open="open" :title="modalTitle" size="lg" @close="close">
    <div class="space-y-4">
      <div v-if="error" class="rounded-xl border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">
        {{ error }}
      </div>
      <div v-if="success" class="rounded-xl border border-success/30 bg-success/5 px-4 py-3 text-sm text-success">
        {{ success }}
      </div>

      <div class="flex flex-wrap items-end gap-3">
        <DateRangePicker
          v-model:from="fromDate"
          v-model:to="toDate"
          class="min-w-[16rem] flex-1"
        />
        <Button :disabled="generating" @click="generate">
          <FileText class="h-4 w-4" />
          {{ generating ? 'Generating...' : 'Generate' }}
        </Button>
        <Button variant="outline" :disabled="copying || !text.trim()" @click="copy">
          <Copy class="h-4 w-4" />
          {{ copying ? 'Menyalin...' : 'Salin' }}
        </Button>
      </div>

      <div>
        <label class="mb-1.5 block text-sm font-medium">Hasil</label>
        <pre
          class="form-control max-h-[24rem] min-h-[16rem] overflow-auto whitespace-pre-wrap px-4 py-3 font-mono text-xs"
        >{{ text || 'Hasil generate akan muncul di sini...' }}</pre>
      </div>
    </div>
    <template #footer>
      <Button variant="outline" @click="close">Tutup</Button>
    </template>
  </Modal>
</template>
