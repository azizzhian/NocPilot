<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { roleApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { Shield, Save } from 'lucide-vue-next'

type RoleRow = { id: number; name: string; label: string; permissions: string[] }
type PermGroup = { key: string; label: string; permissions: { name: string; label: string }[] }

const auth = useAuthStore()
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')
const roles = ref<RoleRow[]>([])
const groups = ref<PermGroup[]>([])
const selectedRoleId = ref<number | null>(null)
const draft = ref<Set<string>>(new Set())

const selectedRole = computed(() => roles.value.find((r) => r.id === selectedRoleId.value) ?? null)
const dirty = computed(() => {
  if (!selectedRole.value) return false
  const current = new Set(selectedRole.value.permissions)
  if (current.size !== draft.value.size) return true
  for (const p of draft.value) {
    if (!current.has(p)) return true
  }
  return false
})

function selectRole(role: RoleRow) {
  selectedRoleId.value = role.id
  draft.value = new Set(role.permissions)
  error.value = ''
  success.value = ''
}

function toggle(perm: string) {
  const next = new Set(draft.value)
  if (next.has(perm)) next.delete(perm)
  else next.add(perm)
  draft.value = next
}

function toggleGroup(group: PermGroup, checked: boolean) {
  const next = new Set(draft.value)
  for (const p of group.permissions) {
    if (checked) next.add(p.name)
    else next.delete(p.name)
  }
  draft.value = next
}

function groupChecked(group: PermGroup): boolean {
  return group.permissions.every((p) => draft.value.has(p.name))
}

function groupIndeterminate(group: PermGroup): boolean {
  const n = group.permissions.filter((p) => draft.value.has(p.name)).length
  return n > 0 && n < group.permissions.length
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await roleApi.list()
    roles.value = data.roles
    groups.value = data.groups ?? []
    const keep = selectedRoleId.value
    const pick = roles.value.find((r) => r.id === keep) ?? roles.value.find((r) => r.name === 'noc') ?? roles.value[0]
    if (pick) selectRole(pick)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? 'Gagal memuat role.'
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!selectedRole.value) return
  saving.value = true
  error.value = ''
  success.value = ''
  try {
    const { data } = await roleApi.update(selectedRole.value.id, [...draft.value].sort())
    const idx = roles.value.findIndex((r) => r.id === data.role.id)
    if (idx !== -1) roles.value[idx] = { ...roles.value[idx], permissions: [...data.role.permissions] }
    draft.value = new Set(data.role.permissions)
    success.value = data.message
    // refresh own permissions if editing own role
    if (auth.user?.roles?.includes(data.role.name) || auth.user?.role === data.role.name) {
      await auth.fetchUser()
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    error.value =
      err.response?.data?.errors?.permissions?.[0]
      ?? err.response?.data?.message
      ?? 'Gagal menyimpan permission.'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <AppLayout title="Role & Permission" subtitle="Centang akses menu dan widget dashboard per role">
    <div v-if="error" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
      {{ error }}
    </div>
    <div v-if="success" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
      {{ success }}
    </div>

    <Skeleton v-if="loading" class="h-96 rounded-[18px]" />

    <div v-else class="grid gap-6 lg:grid-cols-3">
      <Card class="lg:col-span-1 p-5">
        <div class="mb-4 flex items-center gap-2">
          <Shield class="h-4 w-4 text-primary" />
          <h3 class="text-sm font-semibold text-foreground">Roles</h3>
        </div>
        <div class="space-y-2">
          <button
            v-for="role in roles"
            :key="role.id"
            type="button"
            class="w-full rounded-xl border p-3 text-left transition"
            :class="selectedRoleId === role.id
              ? 'border-primary bg-primary/5'
              : 'border-border hover:bg-muted/30'"
            @click="selectRole(role)"
          >
            <p class="font-medium capitalize text-foreground">{{ role.label }}</p>
            <p class="mt-1 text-xs text-muted">{{ role.permissions.length }} permissions</p>
          </button>
        </div>
        <p class="mt-4 text-xs text-muted">
          Role di-assign ke user di menu Kelola User. Administrator selalu punya akses penuh di UI.
        </p>
      </Card>

      <Card class="lg:col-span-2 p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 class="text-sm font-semibold text-foreground">
              Permission — <span class="capitalize">{{ selectedRole?.label ?? '—' }}</span>
            </h3>
            <p class="mt-0.5 text-xs text-muted">Centang modul & widget yang boleh diakses role ini</p>
          </div>
          <Button :disabled="!dirty || saving || !selectedRole" @click="save">
            <Save class="h-4 w-4" />
            {{ saving ? 'Menyimpan...' : 'Simpan' }}
          </Button>
        </div>

        <div v-if="selectedRole" class="space-y-6">
          <div v-for="group in groups" :key="group.key">
            <label class="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
              <input
                type="checkbox"
                class="h-4 w-4 rounded border-border"
                :checked="groupChecked(group)"
                :indeterminate.prop="groupIndeterminate(group)"
                @change="toggleGroup(group, ($event.target as HTMLInputElement).checked)"
              >
              {{ group.label }}
            </label>
            <div class="grid gap-2 sm:grid-cols-2">
              <label
                v-for="perm in group.permissions"
                :key="perm.name"
                class="flex cursor-pointer items-start gap-2 rounded-lg border border-border/70 px-3 py-2 hover:bg-muted/20"
              >
                <input
                  type="checkbox"
                  class="mt-0.5 h-4 w-4 rounded border-border"
                  :checked="draft.has(perm.name)"
                  @change="toggle(perm.name)"
                >
                <span>
                  <span class="block text-sm text-foreground">{{ perm.label }}</span>
                  <span class="block text-[10px] text-muted">{{ perm.name }}</span>
                </span>
              </label>
            </div>
          </div>
        </div>
        <p v-else class="py-12 text-center text-sm text-muted">Pilih role di kiri.</p>

        <div class="mt-6 overflow-x-auto border-t border-border pt-4">
          <p class="mb-2 text-xs font-medium text-muted">Ringkasan matrix</p>
          <table class="w-full text-xs">
            <thead>
              <tr class="border-b border-border text-muted">
                <th class="pb-2 pr-3 text-left font-medium">Permission</th>
                <th v-for="role in roles" :key="role.id" class="pb-2 px-2 text-center font-medium capitalize">{{ role.name }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="name in groups.flatMap((g) => g.permissions.map((p) => p.name))" :key="name" class="border-b border-border/50">
                <td class="py-2 pr-3 font-medium text-foreground">{{ name }}</td>
                <td v-for="role in roles" :key="role.id" class="py-2 px-2 text-center">
                  <Badge v-if="role.permissions.includes(name)" variant="success">✓</Badge>
                  <span v-else class="text-muted">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  </AppLayout>
</template>
