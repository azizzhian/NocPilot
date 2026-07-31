<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { generateReportApi, type ReportSnapshotListItem } from '@/services/api'

const loading = ref(true)
const error = ref('')
const search = ref('')
const snapshots = ref<ReportSnapshotListItem[]>([])
const currentPage = ref(1)
const lastPage = ref(1)

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return snapshots.value
  return snapshots.value.filter((s) =>
    s.report_date.includes(q) ||
    s.responsible_name.toLowerCase().includes(q),
  )
})

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const { data } = await generateReportApi.history(page)
    snapshots.value = data.data
    currentPage.value = data.current_page
    lastPage.value = data.last_page
  } catch {
    error.value = 'Gagal memuat histori report.'
  } finally {
    loading.value = false
  }
}

function formatDateTime(value: string) {
  return new Date(value).toLocaleString('id-ID')
}

onMounted(() => load())
</script>

<template>
  <AppLayout title="Histori Report" subtitle="Arsip laporan harian yang telah digenerate">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
      <Input
        v-model="search"
        type="search"
        placeholder="Cari tanggal atau penanggung jawab..."
        class="form-control max-w-sm"
      />
      <RouterLink
        to="/report/generate"
        class="inline-flex h-10 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm font-medium text-[#111827] hover:bg-[#F8FAFC] dark:border-border dark:bg-card dark:text-foreground dark:hover:bg-slate-800"
      >
        Generate Report
      </RouterLink>
    </div>

    <div v-if="error" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
      {{ error }}
    </div>

    <div v-if="loading" class="space-y-4">
      <Skeleton v-for="i in 4" :key="i" class="h-28 rounded-[18px]" />
    </div>

    <div v-else class="relative space-y-0">
      <div class="absolute left-[23px] top-4 bottom-4 hidden w-px bg-border md:block" />

      <div v-for="s in filtered" :key="s.id" class="relative mb-6 flex gap-6">
        <div class="hidden flex-col items-center md:flex">
          <div class="z-10 flex h-3 w-3 rounded-full bg-primary ring-4 ring-background" />
        </div>
        <Card class="flex-1 p-6 transition hover:shadow-md">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <div class="flex items-center gap-2">
                <h3 class="text-lg font-semibold text-foreground">{{ s.report_date }}</h3>
                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                  Tersimpan
                </span>
              </div>
              <p class="mt-1 text-sm text-muted-foreground">
                Penanggung jawab: <strong class="text-foreground">{{ s.responsible_name }}</strong>
              </p>
              <p class="mt-1 text-xs text-muted-foreground">
                Dibuat {{ formatDateTime(s.created_at) }}
                <span v-if="s.generator?.name"> · oleh {{ s.generator.name }}</span>
              </p>
            </div>
            <RouterLink
              :to="`/report/history/${s.id}`"
              class="inline-flex h-10 items-center justify-center rounded-xl bg-primary px-4 text-sm font-medium text-white hover:bg-primary/90"
            >
              Preview
            </RouterLink>
          </div>
        </Card>
      </div>

      <Card v-if="!filtered.length" class="py-16 text-center text-muted-foreground">
        Belum ada histori report.
      </Card>

      <div v-if="lastPage > 1" class="mt-6 flex justify-center gap-2">
        <Button variant="outline" size="sm" :disabled="currentPage <= 1" @click="load(currentPage - 1)">
          Sebelumnya
        </Button>
        <span class="flex items-center px-3 text-sm text-muted-foreground">
          Halaman {{ currentPage }} / {{ lastPage }}
        </span>
        <Button variant="outline" size="sm" :disabled="currentPage >= lastPage" @click="load(currentPage + 1)">
          Selanjutnya
        </Button>
      </div>
    </div>
  </AppLayout>
</template>
