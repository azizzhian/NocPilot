<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import Select from '@/components/ui/Select.vue'
import TicketUpdateModal from '@/components/tickets/TicketUpdateModal.vue'
import { reportTicketApi, technicianApi, type ReportTicketItem } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { formatTimeId } from '@/lib/date-input'
import { isCloseStatus, ticketStatusLabel, ticketStatusVariant } from '@/lib/ticket-status'
import { ArrowRight, ClipboardList, Clock, MapPin, User } from 'lucide-vue-next'

const props = defineProps<{ open: boolean; ticketId: number | null }>()
const emit = defineEmits<{
  close: []
  changed: []
  'view-complaint': [id: number]
}>()

const auth = useAuthStore()
const ticket = ref<ReportTicketItem | null>(null)
const technicians = ref<{ id: number; name: string }[]>([])
const selectedTechnician = ref('')
const loading = ref(false)
const busy = ref(false)
const error = ref('')
const updateModalOpen = ref(false)
const updateMode = ref<'progress' | 'close'>('progress')

const canManage = computed(() => auth.can('ticket.manage'))
const fromComplaint = computed(() => ticket.value?.source === 'complaint' || Boolean(ticket.value?.complaint_id))

async function load() {
  if (!props.ticketId) return
  loading.value = true
  error.value = ''
  try {
    const res = await reportTicketApi.show(props.ticketId)
    ticket.value = res.data.data
    selectedTechnician.value = ticket.value?.assigned_to ? String(ticket.value.assigned_to) : ''
  } catch {
    error.value = 'Gagal memuat detail ticket.'
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.open, props.ticketId] as const,
  async ([open]) => {
    if (!open || !props.ticketId) return
    const res = await technicianApi.list().catch(() => null)
    technicians.value = res?.data.data ?? []
    await load()
  },
  { immediate: true },
)

async function act(fn: () => Promise<unknown>, success: string) {
  busy.value = true
  error.value = ''
  try {
    await fn()
    await load()
    emit('changed')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    const first = err.response?.data?.errors
      ? Object.values(err.response.data.errors).flat()[0]
      : undefined
    error.value = first ?? err.response?.data?.message ?? success
  } finally {
    busy.value = false
  }
}

function assign() {
  if (!selectedTechnician.value || !ticket.value) return
  act(() => reportTicketApi.assign(ticket.value!.id, Number(selectedTechnician.value)), 'Gagal menugaskan teknisi.')
}

function start() {
  if (!ticket.value) return
  act(() => reportTicketApi.start(ticket.value!.id), 'Gagal memulai pekerjaan.')
}

function openUpdate(mode: 'progress' | 'close') {
  updateMode.value = mode
  updateModalOpen.value = true
}
</script>

<template>
  <Modal
    :open="open"
    title="Detail Ticket"
    :subtitle="ticket ? `${ticket.customer_name} · #${ticket.id}` : undefined"
    size="lg"
    @close="emit('close')"
  >
    <div v-if="loading" class="py-10 text-center text-sm text-muted">Memuat detail...</div>
    <div v-else-if="!ticket" class="py-10 text-center text-sm text-muted">Ticket tidak ditemukan.</div>
    <div v-else class="space-y-4">
      <div v-if="error" class="rounded-xl border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">
        {{ error }}
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <Badge :variant="fromComplaint ? 'info' : 'secondary'">
          {{ fromComplaint ? 'Dari Komplain' : 'Tiket Langsung' }}
        </Badge>
        <Badge :variant="ticketStatusVariant(ticket.status)">{{ ticketStatusLabel(ticket.status) }}</Badge>
        <span v-if="ticket.priority" class="text-xs text-muted">Prioritas: {{ ticket.priority }}</span>
        <span v-if="ticket.job_type" class="text-xs text-muted">· {{ ticket.job_type }}</span>
      </div>

      <div v-if="fromComplaint" class="rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm">
        <p class="font-semibold text-foreground">SUMBER TIKET: KOMPLAIN</p>
        <div class="mt-2 grid gap-1.5 text-xs text-muted sm:grid-cols-2">
          <p>No. Komplain: <span class="font-medium text-foreground">{{ ticket.complaint_number || '—' }}</span></p>
          <p>Tanggal Komplain: <span class="font-medium text-foreground">{{ ticket.opened_at || '—' }}</span></p>
          <p>Pelanggan: <span class="font-medium text-foreground">{{ ticket.customer_name }}</span></p>
          <p>ODC/Site: <span class="font-medium text-foreground">{{ ticket.odc_name || '—' }}</span></p>
          <p>NOC Pembuat: <span class="font-medium text-foreground">
            {{ (ticket.source_snapshot as Record<string, unknown> | null)?.creator_name || ticket.creator_name || '—' }}
          </span></p>
          <p>Waktu Eskalasi: <span class="font-medium text-foreground">{{ formatTimeId(ticket.escalated_at) || '—' }}</span></p>
        </div>
        <p class="mt-2 break-words text-xs text-foreground">Problem awal: {{ ticket.problem || '—' }}</p>
        <p class="mt-1 break-words text-xs text-foreground">Alasan eskalasi: {{ ticket.escalation_reason || '—' }}</p>
        <Button
          v-if="ticket.complaint_id"
          variant="outline"
          size="sm"
          class="mt-3"
          @click="emit('view-complaint', ticket.complaint_id!)"
        >
          Lihat Komplain Asal <ArrowRight class="h-3.5 w-3.5" />
        </Button>
      </div>

      <div class="grid gap-2 rounded-xl border border-border p-4 text-xs text-muted sm:grid-cols-2">
        <p class="flex items-center gap-1.5"><MapPin class="h-3.5 w-3.5" /> Lokasi: {{ ticket.location || '—' }}</p>
        <p class="flex items-center gap-1.5"><User class="h-3.5 w-3.5" /> Teknisi: {{ ticket.assignee_name || 'Belum ditugaskan' }}</p>
        <p class="flex items-center gap-1.5"><Clock class="h-3.5 w-3.5" /> Mulai kerja: {{ formatTimeId(ticket.work_started_at) || '—' }}</p>
        <p class="flex items-center gap-1.5"><Clock class="h-3.5 w-3.5" /> Selesai kerja: {{ formatTimeId(ticket.work_completed_at) || '—' }}</p>
        <p v-if="ticket.visit_schedule" class="sm:col-span-2">Jadwal kunjungan: {{ ticket.visit_schedule.replace('T', ' ').slice(0, 16) }}</p>
        <p v-if="ticket.action" class="sm:col-span-2 break-words">Action: {{ ticket.action }}</p>
        <p v-if="ticket.notes" class="sm:col-span-2 break-words">Catatan: {{ ticket.notes }}</p>
      </div>

      <div v-if="canManage" class="flex flex-wrap items-end gap-2 rounded-xl border border-border p-3">
        <div class="min-w-[12rem] flex-1">
          <label class="mb-1 block text-[11px] text-muted">Assignment teknisi</label>
          <Select v-model="selectedTechnician">
            <option value="">— Pilih teknisi —</option>
            <option v-for="t in technicians" :key="t.id" :value="t.id">{{ t.name }}</option>
          </Select>
        </div>
        <Button size="sm" :disabled="busy || !selectedTechnician" @click="assign">Tugaskan</Button>
        <Button size="sm" variant="outline" :disabled="busy" @click="start">Mulai Kerja</Button>
        <Button size="sm" variant="outline" :disabled="busy" @click="openUpdate('progress')">
          <ClipboardList class="h-3.5 w-3.5" /> Update Pekerjaan
        </Button>
        <Button
          v-if="!isCloseStatus(ticket.status)"
          size="sm"
          variant="outline"
          :disabled="busy"
          @click="openUpdate('close')"
        >
          Close Tiket
        </Button>
      </div>

      <div>
        <p class="mb-2 text-sm font-semibold">Riwayat Pekerjaan Tiket</p>
        <div v-if="!ticket.updates?.length" class="rounded-xl border border-dashed border-border px-4 py-6 text-center text-xs text-muted">
          Belum ada riwayat pekerjaan.
        </div>
        <div v-else class="space-y-3">
          <div
            v-for="update in ticket.updates"
            :key="update.id"
            class="relative border-l-2 border-primary/30 pl-4 text-xs"
          >
            <span class="absolute -left-[5px] top-1.5 h-2.5 w-2.5 rounded-full bg-primary" />
            <p class="font-medium text-foreground">
              {{ update.type }} · {{ update.author_name || '—' }} ·
              {{ update.created_at?.slice(0, 16).replace('T', ' ') }}
            </p>
            <p v-if="update.check_result" class="mt-0.5 break-words text-muted">Hasil: {{ update.check_result }}</p>
            <p v-if="update.repair_action" class="mt-0.5 break-words text-muted">Tindakan: {{ update.repair_action }}</p>
            <p v-if="update.material" class="mt-0.5 text-muted">Material: {{ update.material }}</p>
            <p v-if="update.note" class="mt-0.5 break-words text-muted">Catatan: {{ update.note }}</p>
            <a
              v-if="update.photo"
              :href="`/storage/${update.photo}`"
              target="_blank"
              rel="noopener"
              class="mt-1 inline-block text-primary underline"
            >
              Lihat foto
            </a>
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <Button variant="outline" @click="emit('close')">Tutup</Button>
    </template>
  </Modal>

  <TicketUpdateModal
    :open="updateModalOpen"
    :ticket-id="ticketId"
    :mode="updateMode"
    @close="updateModalOpen = false"
    @saved="() => { load(); emit('changed') }"
  />
</template>
