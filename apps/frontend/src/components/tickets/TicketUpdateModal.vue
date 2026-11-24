<script setup lang="ts">
import { ref, watch } from 'vue'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Textarea from '@/components/ui/Textarea.vue'
import { reportTicketApi } from '@/services/api'

const props = withDefaults(
  defineProps<{ open: boolean; ticketId: number | null; mode?: 'progress' | 'close' }>(),
  { mode: 'progress' },
)
const emit = defineEmits<{ close: []; saved: [] }>()

const blank = (mode: 'progress' | 'close') => ({
  type: mode === 'close' ? 'completed' : 'progress',
  action: '',
  check_result: '',
  repair_action: '',
  material: '',
  work_started_at: '',
  work_ended_at: '',
  note: '',
})
const form = ref(blank(props.mode))
const photo = ref<File | null>(null)
const saving = ref(false)
const error = ref('')

watch(
  () => [props.open, props.mode] as const,
  ([open, mode]) => {
    if (!open) return
    form.value = blank(mode)
    photo.value = null
    error.value = ''
  },
  { immediate: true },
)

async function submit() {
  if (!props.ticketId) return
  error.value = ''
  if (props.mode === 'close' && !form.value.action.trim()) {
    error.value = 'Action/perbaikan wajib diisi sebelum ticket ditutup.'
    return
  }
  saving.value = true
  try {
    const payload: Record<string, unknown> = {
      type: form.value.type,
      action: form.value.action || null,
      check_result: form.value.check_result || null,
      repair_action: form.value.repair_action || null,
      material: form.value.material || null,
      work_started_at: form.value.work_started_at || null,
      work_ended_at: form.value.work_ended_at || null,
      note: form.value.note || null,
    }
    let body: FormData | Record<string, unknown> = payload
    if (photo.value) {
      const fd = new FormData()
      Object.entries(payload).forEach(([key, value]) => {
        if (value !== null) fd.append(key, String(value))
      })
      fd.append('photo', photo.value)
      body = fd
    }
    await reportTicketApi.storeUpdate(props.ticketId, body)
    emit('saved')
    emit('close')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    const first = err.response?.data?.errors
      ? Object.values(err.response.data.errors).flat()[0]
      : undefined
    error.value = first ?? err.response?.data?.message ?? 'Gagal menyimpan update pekerjaan.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Modal
    :open="open"
    :title="mode === 'close' ? 'Close Tiket' : 'Update Pekerjaan Tiket'"
    :subtitle="mode === 'close'
      ? 'Tiket akan ditutup; Action wajib diisi dan komplain terkait masuk Menunggu Konfirmasi.'
      : 'Catatan pekerjaan tersimpan pada riwayat tiket, terpisah dari riwayat komplain.'"
    size="lg"
    @close="emit('close')"
  >
    <div class="grid gap-4 sm:grid-cols-2">
      <div v-if="mode !== 'close'">
        <label class="mb-1.5 block text-sm font-medium">Jenis Update</label>
        <Select v-model="form.type">
          <option value="progress">Progress</option>
          <option value="started">Mulai bekerja</option>
        </Select>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium">Material yang digunakan</label>
        <Input v-model="form.material" placeholder="Contoh: Konektor RJ45, kabel dropcore" />
      </div>
      <div v-if="mode === 'close'" class="sm:col-span-2 rounded-xl border border-success/30 bg-success/5 p-3">
        <label class="mb-1.5 block text-sm font-medium">Action / Hasil Penyelesaian <span class="text-danger">*</span></label>
        <Textarea v-model="form.action" :rows="2" placeholder="Contoh: ONU diganti, konektor disambung ulang" />
      </div>
      <div class="sm:col-span-2">
        <label class="mb-1.5 block text-sm font-medium">Hasil Pengecekan</label>
        <Textarea v-model="form.check_result" :rows="3" placeholder="Contoh: Kabel dropcore putus di titik ODP" />
      </div>
      <div class="sm:col-span-2">
        <label class="mb-1.5 block text-sm font-medium">Tindakan Perbaikan</label>
        <Textarea v-model="form.repair_action" :rows="3" placeholder="Contoh: Sambung ulang konektor & ganti splitter" />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium">Waktu Mulai Pekerjaan</label>
        <Input v-model="form.work_started_at" type="datetime-local" />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium">Waktu Selesai Pekerjaan</label>
        <Input v-model="form.work_ended_at" type="datetime-local" />
      </div>
      <div class="sm:col-span-2">
        <label class="mb-1.5 block text-sm font-medium">Foto Dokumentasi (opsional)</label>
        <input
          type="file"
          accept="image/*"
          class="form-control w-full text-xs"
          @change="photo = (($event.target as HTMLInputElement).files ?? [])[0] ?? null"
        />
      </div>
      <div class="sm:col-span-2">
        <label class="mb-1.5 block text-sm font-medium">Catatan Tambahan</label>
        <Textarea v-model="form.note" :rows="2" />
      </div>
    </div>
    <p v-if="error" class="mt-3 text-sm text-danger">{{ error }}</p>
    <template #footer>
      <Button variant="outline" @click="emit('close')">Batal</Button>
      <Button :disabled="saving" @click="submit">
        {{ saving ? 'Menyimpan...' : (mode === 'close' ? 'Simpan & Close' : 'Simpan Update') }}
      </Button>
    </template>
  </Modal>
</template>
