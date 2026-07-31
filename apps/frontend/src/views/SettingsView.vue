<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { settingsApi } from '@/services/api'

const loading = ref(true)
const saving = ref(false)
const success = ref('')
const error = ref('')
const settings = ref<Record<string, unknown>>({})
const activityName = ref('')

onMounted(async () => {
  try {
    const { data } = await settingsApi.get()
    settings.value = data
    activityName.value = String(data.activity_name ?? '')
  } finally {
    loading.value = false
  }
})

async function save() {
  saving.value = true
  success.value = ''
  error.value = ''
  try {
    const { data } = await settingsApi.update({ activity_name: activityName.value })
    settings.value = data.data ?? data
    activityName.value = String((data.data ?? data).activity_name ?? activityName.value)
    success.value = 'Pengaturan disimpan.'
  } catch {
    error.value = 'Gagal menyimpan pengaturan.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AppLayout title="Pengaturan" subtitle="Konfigurasi aplikasi NocPilot">
    <div v-if="loading" class="space-y-4">
      <Skeleton class="h-40 w-full" />
      <Skeleton class="h-40 w-full" />
    </div>

    <div v-else class="mx-auto max-w-2xl space-y-6">
      <div v-if="success" class="rounded-xl border border-success/30 bg-success/5 px-4 py-3 text-sm text-success">{{ success }}</div>
      <div v-if="error" class="rounded-xl border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">{{ error }}</div>

      <Card class="space-y-4 p-6">
        <div>
          <h3 class="text-sm font-semibold text-foreground">Umum</h3>
          <p class="mt-1 text-sm text-muted">Nama aktivitas untuk generate laporan harian.</p>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-foreground">Nama aktivitas</label>
          <Input v-model="activityName" placeholder="Report Monitoring & Aktivasi Broadband" />
        </div>
        <Button :disabled="saving" @click="save">{{ saving ? 'Menyimpan...' : 'Simpan' }}</Button>
      </Card>

      <Card class="p-6">
        <h3 class="mb-3 text-sm font-semibold text-foreground">Fitur Aktif</h3>
        <ul class="space-y-2 text-sm text-muted">
          <li v-for="(enabled, key) in (settings.features as Record<string, boolean>)" :key="key">
            <span class="capitalize text-foreground">{{ String(key).replace(/_/g, ' ') }}</span>:
            <span :class="enabled ? 'text-success' : 'text-danger'">{{ enabled ? ' Aktif' : ' Nonaktif' }}</span>
          </li>
        </ul>
      </Card>
    </div>
  </AppLayout>
</template>
