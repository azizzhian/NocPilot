<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Textarea from '@/components/ui/Textarea.vue'
import {
  activityApi,
  odcApi,
  locationApi,
  reportTicketApi,
  technicianApi,
  type ActivityLogItem,
  type DailyEntryItem,
} from '@/services/api'
import { todayInput } from '@/lib/date-input'
import { isCloseStatus, ticketStatusChoices } from '@/lib/ticket-status'

const props = withDefaults(
  defineProps<{
    open: boolean
    editingId?: number | null
    complaint?: DailyEntryItem | null
  }>(),
  { editingId: null, complaint: null },
)

const emit = defineEmits<{
  close: []
  saved: [payload: { id: number; created: boolean; fromComplaint: boolean }]
}>()

const isEscalation = computed(() => Boolean(props.complaint) && !props.editingId)

const blankForm = () => ({
  location: '',
  odc_name: '',
  customer_code: '',
  customer_name: '',
  customer_phone: '',
  job_type: '',
  priority: '',
  visit_schedule: '',
  problem: '',
  action: '',
  status: 'On-Progress',
  opened_at: todayInput(),
  closed_at: '',
  notes: '',
  escalation_reason: '',
  assigned_to: '' as string | number,
})

const form = ref(blankForm())
const ticketType = ref<'customer' | 'general'>('customer')
const existingComplaintId = ref<number | null>(null)
const technicians = ref<{ id: number; name: string }[]>([])
const odcs = ref<{ id: number; name: string }[]>([])
const locations = ref<{ name: string; odc_name: string | null }[]>([])
const complaintLogs = ref<ActivityLogItem[]>([])
const saving = ref(false)
const error = ref('')

const statusChoices = computed(() => ticketStatusChoices(form.value.status))
const closing = computed(() => isCloseStatus(form.value.status))

const ticketTypes = ['customer', 'general'] as const

const escalationSuggestions = [
  'Gangguan tidak dapat diselesaikan melalui remote troubleshooting.',
  'Memerlukan pengecekan kabel dropcore.',
  'Diduga terdapat kerusakan konektor atau splitter.',
  'Modem/ONU perlu diperiksa langsung.',
  'Membutuhkan pengecekan jalur FO.',
  'Memerlukan pengecekan perangkat pelanggan.',
  'Gangguan membutuhkan kunjungan teknisi.',
]

async function loadOptions() {
  try {
    const [techRes, odcRes, locRes] = await Promise.all([
      technicianApi.list(),
      odcApi.list({ per_page: 200 }),
      locationApi.list({ per_page: 200, status: 'active' }),
    ])
    technicians.value = techRes.data.data ?? []
    odcs.value = (odcRes.data.data as { id: number; name?: string }[])
      .map((o) => ({ id: o.id, name: String(o.name ?? '') }))
      .filter((o) => o.name)
    locations.value = (locRes.data.data as { name?: string; odc?: { name?: string } | null }[])
      .map((l) => ({ name: String(l.name ?? ''), odc_name: l.odc?.name ? String(l.odc.name) : null }))
      .filter((l) => l.name)
  } catch {
    technicians.value = []
    odcs.value = []
    locations.value = []
  }
}

async function loadComplaintLogs(complaint: DailyEntryItem) {
  try {
    const res = await activityApi.list({
      type: 'complaint',
      subject_type: 'App\\Models\\DailyComplaint',
      subject_id: complaint.id,
      per_page: 5,
    })
    complaintLogs.value = res.data.data ?? []
  } catch {
    complaintLogs.value = []
  }
}

watch(
  () => props.open,
  async (open) => {
    if (!open) return
    error.value = ''
    await loadOptions()

    if (props.editingId) {
      try {
        const res = await reportTicketApi.show(props.editingId)
        const t = res.data.data
        existingComplaintId.value = t.complaint_id ?? null
        ticketType.value = t.customer_code || t.customer_name !== 'Gangguan jalur umum' ? 'customer' : 'general'
        form.value = {
          location: t.location ?? '',
          odc_name: t.odc_name ?? '',
          customer_code: t.customer_code ?? '',
          customer_name: t.customer_name ?? '',
          customer_phone: t.customer_phone ?? '',
          job_type: t.job_type ?? '',
          priority: t.priority ?? '',
          visit_schedule: t.visit_schedule ? t.visit_schedule.slice(0, 16) : '',
          problem: t.problem ?? '',
          action: t.action ?? '',
          status: t.status,
          opened_at: t.opened_at ?? '',
          closed_at: t.closed_at ?? '',
          notes: t.notes ?? '',
          escalation_reason: t.escalation_reason ?? '',
          assigned_to: t.assigned_to ?? '',
        }
      } catch {
        error.value = 'Gagal memuat ticket.'
      }
      return
    }

    existingComplaintId.value = null
    const c = props.complaint
    form.value = {
      ...blankForm(),
      location: c?.location_label || c?.odc_name || '',
      odc_name: c?.odc_name || '',
      customer_code: c?.customer_code || '',
      customer_name: c?.customer_name || '',
      customer_phone: c?.phone_normalized || '',
      problem: c?.problem || '',
      action: c?.action || '',
      opened_at: c?.report_date || todayInput(),
      notes: c ? `Komplain ${c.complaint_number ?? `#${c.id}`} — ${c.problem ?? ''}` : '',
    }
    ticketType.value = 'customer'
    complaintLogs.value = []
    if (c) await loadComplaintLogs(c)
  },
  { immediate: true },
)

function setTicketType(type: 'customer' | 'general') {
  if (isEscalation.value) return
  ticketType.value = type
  if (type === 'general') form.value.customer_code = ''
}

function onLocationChange(value: string | number) {
  form.value.location = String(value ?? '')
  const match = locations.value.find((l) => l.name === form.value.location)
  if (match?.odc_name) form.value.odc_name = match.odc_name
}

async function submit() {
  error.value = ''
  if (isEscalation.value && !form.value.escalation_reason.trim()) {
    error.value = 'Alasan eskalasi wajib diisi.'
    return
  }
  if (isEscalation.value && !form.value.action.trim()) {
    error.value = 'Action/perbaikan wajib diisi sebelum komplain di-Clear.'
    return
  }
  if (closing.value && !form.value.action.trim()) {
    error.value = 'Action/perbaikan wajib diisi sebelum ticket ditutup.'
    return
  }
  saving.value = true
  try {
    const payload: Record<string, unknown> = {
      ...form.value,
      complaint_id: props.complaint?.id ?? existingComplaintId.value ?? null,
      customer_code: form.value.customer_code || null,
      customer_name: form.value.customer_name,
      customer_phone: form.value.customer_phone || null,
      assigned_to: form.value.assigned_to === '' ? null : Number(form.value.assigned_to),
      visit_schedule: form.value.visit_schedule || null,
      opened_at: form.value.opened_at || null,
      closed_at: form.value.closed_at || null,
      escalation_reason: form.value.escalation_reason || null,
    }
    const res = props.editingId
      ? await reportTicketApi.update(props.editingId, payload)
      : await reportTicketApi.create(payload)
    const data = res.data as { data?: { id: number } }
    emit('saved', {
      id: data.data?.id ?? props.editingId ?? 0,
      created: !props.editingId,
      fromComplaint: isEscalation.value,
    })
    emit('close')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    const first = err.response?.data?.errors
      ? Object.values(err.response.data.errors).flat()[0]
      : undefined
    error.value = first ?? err.response?.data?.message ?? 'Gagal menyimpan ticket.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Modal
    :open="open"
    :title="editingId ? 'Edit Ticket' : isEscalation ? 'Buat Tiket dari Komplain' : 'Tambah Ticket'"
    :subtitle="isEscalation ? 'Form tiket yang sama — data pelanggan & gangguan terisi otomatis dari komplain.' : undefined"
    size="lg"
    @close="emit('close')"
  >
    <div v-if="isEscalation && complaint" class="mb-4 rounded-xl border border-primary/20 bg-primary/5 p-3 text-xs">
      <p class="font-semibold text-foreground">SUMBER KOMPLAIN · {{ complaint.complaint_number }}</p>
      <p class="mt-1 text-muted">
        {{ complaint.customer_name }} · {{ complaint.phone_normalized || '—' }} ·
        ODC/Site: {{ complaint.odc_name || '—' }} · Input: {{ complaint.creator_name || '—' }} ·
        {{ complaint.report_date }}
      </p>
      <p class="mt-1 break-words text-foreground">Problem: {{ complaint.problem || '—' }}</p>
      <div v-if="complaintLogs.length" class="mt-2 space-y-0.5 border-t border-primary/20 pt-2">
        <p class="font-semibold">Riwayat Update Komplain</p>
        <p v-for="log in complaintLogs" :key="log.id" class="break-words text-muted">
          • {{ log.created_at?.slice(0, 16).replace('T', ' ') }} — {{ log.user }}: {{ log.action }}
        </p>
      </div>
      <p v-else class="mt-2 text-muted">Belum ada riwayat update komplain.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
      <div v-if="!isEscalation" class="sm:col-span-2">
        <label class="mb-1.5 block text-sm font-medium">Tipe Ticket</label>
        <div class="flex gap-1 rounded-xl border border-border p-1">
          <button
            v-for="type in ticketTypes"
            :key="type"
            type="button"
            class="flex-1 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
            :class="ticketType === type ? 'bg-primary text-white' : 'text-muted hover:bg-muted'"
            @click="setTicketType(type)"
          >
            {{ type === 'customer' ? 'Pelanggan' : 'General' }}
          </button>
        </div>
      </div>

      <div>
        <label class="mb-1.5 block text-sm font-medium">Lokasi</label>
        <Select :model-value="form.location" @update:model-value="onLocationChange">
          <option value="">— Pilih lokasi —</option>
          <option v-for="l in locations" :key="l.name" :value="l.name">
            {{ l.name }}{{ l.odc_name ? ` → ${l.odc_name}` : '' }}
          </option>
        </Select>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium">ODC / Site</label>
        <Select v-model="form.odc_name">
          <option value="">— Pilih ODC —</option>
          <option v-for="o in odcs" :key="o.id" :value="o.name">{{ o.name }}</option>
        </Select>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium">ID Pelanggan</label>
        <Input v-model="form.customer_code" />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium">Nama Pelanggan / Judul</label>
        <Input v-model="form.customer_name" required />
      </div>
      <div v-if="isEscalation">
        <label class="mb-1.5 block text-sm font-medium">No. Telepon</label>
        <Input v-model="form.customer_phone" />
      </div>
      <div class="sm:col-span-2">
        <label class="mb-1.5 block text-sm font-medium">Problem</label>
        <Input v-model="form.problem" />
      </div>
      <div class="sm:col-span-2">
        <label class="mb-1.5 block text-sm font-medium">
          Action <span v-if="isEscalation" class="text-danger">*</span>
        </label>
        <Textarea v-model="form.action" :rows="2" />
        <p v-if="isEscalation" class="mt-1 text-[11px] text-muted">
          Wajib diisi — komplain akan langsung di-Clear saat tiket dibuat.
        </p>
      </div>

      <div v-if="isEscalation" class="sm:col-span-2 rounded-xl border border-warning/30 bg-warning/5 p-3">
        <label class="mb-1.5 block text-sm font-medium">Alasan Eskalasi <span class="text-danger">*</span></label>
        <Textarea v-model="form.escalation_reason" :rows="2" placeholder="Contoh: Gangguan tidak dapat diselesaikan melalui remote troubleshooting." />
        <div class="mt-1.5 flex flex-wrap gap-1">
          <button
            v-for="suggestion in escalationSuggestions"
            :key="suggestion"
            type="button"
            class="rounded-full border border-border px-2 py-0.5 text-[10px] text-muted hover:border-primary hover:text-primary"
            @click="form.escalation_reason = suggestion"
          >
            {{ suggestion }}
          </button>
        </div>
      </div>

      <div>
        <label class="mb-1.5 block text-sm font-medium">Jenis Pekerjaan Lapangan</label>
        <Input v-model="form.job_type" placeholder="Contoh: Pengecekan kabel dropcore" />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium">Prioritas</label>
        <Select v-model="form.priority">
          <option value="">— Pilih prioritas —</option>
          <option value="low">Rendah</option>
          <option value="medium">Sedang</option>
          <option value="high">Tinggi</option>
          <option value="critical">Kritis</option>
        </Select>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium">Teknisi / Tim Lapangan</label>
        <Select v-model="form.assigned_to">
          <option value="">— Belum ditugaskan —</option>
          <option v-for="t in technicians" :key="t.id" :value="t.id">{{ t.name }}</option>
        </Select>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium">Jadwal Kunjungan</label>
        <Input v-model="form.visit_schedule" type="datetime-local" />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium">Status</label>
        <Select v-model="form.status">
          <option v-for="s in statusChoices" :key="s.value" :value="s.value">{{ s.label }}</option>
        </Select>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium">Tgl Open</label>
        <Input v-model="form.opened_at" type="date" />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium">Tgl Close</label>
        <Input v-model="form.closed_at" type="date" />
      </div>
      <div v-if="closing" class="sm:col-span-2 rounded-xl border border-success/30 bg-success/5 p-3">
        <label class="mb-1.5 block text-sm font-medium">
          Action / Hasil Penyelesaian <span class="text-danger">*</span>
        </label>
        <Textarea v-model="form.action" :rows="2" placeholder="Contoh: ONU diganti, konektor disambung ulang" />
        <p class="mt-1 text-[11px] text-muted">Wajib diisi sebelum ticket dapat ditutup.</p>
      </div>
      <div class="sm:col-span-2">
        <label class="mb-1.5 block text-sm font-medium">Catatan untuk Teknisi</label>
        <Textarea v-model="form.notes" :rows="2" />
      </div>
    </div>

    <p v-if="error" class="mt-3 text-sm text-danger">{{ error }}</p>
    <template #footer>
      <Button variant="outline" @click="emit('close')">Batal</Button>
      <Button :disabled="saving" @click="submit">{{ saving ? 'Menyimpan...' : 'Simpan' }}</Button>
    </template>
  </Modal>
</template>
