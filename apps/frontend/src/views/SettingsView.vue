<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { settingsApi } from '@/services/api'
import { allNavItems, defaultFavoritePaths, navigation } from '@/data/navigation'
import { useAppStore } from '@/stores/app'
import { ChevronDown, ChevronUp } from 'lucide-vue-next'

const appStore = useAppStore()
const loading = ref(true)
const saving = ref(false)
const success = ref('')
const error = ref('')
const favoritePaths = ref<string[]>(defaultFavoritePaths())

function sectionTitle(to: string): string {
  for (const section of navigation) {
    if (section.items.some((i) => i.to === to)) return section.title
  }
  return ''
}

const selectedFavorites = computed(() =>
  favoritePaths.value
    .map((path) => {
      const item = allNavItems().find((o) => o.to === path)
      return item ? { to: item.to, label: item.label } : null
    })
    .filter((o): o is { to: string; label: string } => Boolean(o)),
)

function isSelected(to: string) {
  return favoritePaths.value.includes(to)
}

function toggleFavorite(to: string) {
  if (isSelected(to)) {
    favoritePaths.value = favoritePaths.value.filter((p) => p !== to)
  } else {
    favoritePaths.value = [...favoritePaths.value, to]
  }
}

function moveFavorite(index: number, dir: -1 | 1) {
  const next = index + dir
  if (next < 0 || next >= favoritePaths.value.length) return
  const copy = [...favoritePaths.value]
  const tmp = copy[index]!
  copy[index] = copy[next]!
  copy[next] = tmp
  favoritePaths.value = copy
}

onMounted(async () => {
  try {
    const { data } = await settingsApi.get()
    favoritePaths.value = Array.isArray(data.sidebar_favorites) && data.sidebar_favorites.length
      ? data.sidebar_favorites
      : defaultFavoritePaths()
  } finally {
    loading.value = false
  }
})

async function save() {
  saving.value = true
  success.value = ''
  error.value = ''
  try {
    const { data } = await settingsApi.update({
      sidebar_favorites: favoritePaths.value,
    })
    const payload = (data.data ?? data) as { sidebar_favorites?: string[] }
    if (Array.isArray(payload.sidebar_favorites)) {
      favoritePaths.value = payload.sidebar_favorites
      appStore.setSidebarFavoritePaths(payload.sidebar_favorites)
    }
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
          <h3 class="text-sm font-semibold text-foreground">Favorit Sidebar</h3>
          <p class="mt-1 text-sm text-muted">
            Pilih menu yang tampil di Favorit untuk semua user. Urutan bisa diubah dengan panah.
          </p>
        </div>

        <div v-if="selectedFavorites.length" class="space-y-2 rounded-xl border border-border bg-muted/20 p-3">
          <p class="text-xs font-medium text-muted">Urutan favorit</p>
          <div
            v-for="(item, index) in selectedFavorites"
            :key="item.to"
            class="flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2"
          >
            <span class="w-5 text-xs font-semibold text-muted">{{ index + 1 }}</span>
            <span class="min-w-0 flex-1 truncate text-sm font-medium text-foreground">{{ item.label }}</span>
            <div class="flex gap-1">
              <button
                type="button"
                class="rounded-lg p-1.5 text-muted hover:bg-muted disabled:opacity-30"
                :disabled="index === 0"
                @click="moveFavorite(index, -1)"
              >
                <ChevronUp class="h-4 w-4" />
              </button>
              <button
                type="button"
                class="rounded-lg p-1.5 text-muted hover:bg-muted disabled:opacity-30"
                :disabled="index === selectedFavorites.length - 1"
                @click="moveFavorite(index, 1)"
              >
                <ChevronDown class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
        <p v-else class="text-sm text-muted">Belum ada menu favorit dipilih.</p>

        <div class="max-h-80 space-y-1 overflow-y-auto rounded-xl border border-border p-2">
          <label
            v-for="opt in allNavItems()"
            :key="opt.to"
            class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-sm hover:bg-muted/40"
          >
            <input
              type="checkbox"
              class="h-4 w-4 rounded border-border text-primary"
              :checked="isSelected(opt.to)"
              @change="toggleFavorite(opt.to)"
            >
            <span class="min-w-0 flex-1 font-medium text-foreground">{{ opt.label }}</span>
            <span class="text-[10px] uppercase tracking-wide text-muted">{{ sectionTitle(opt.to) }}</span>
          </label>
        </div>
      </Card>

      <div class="flex justify-end">
        <Button :disabled="saving" @click="save">{{ saving ? 'Menyimpan...' : 'Simpan' }}</Button>
      </div>
    </div>
  </AppLayout>
</template>
