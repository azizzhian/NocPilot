<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import SearchInput from '@/components/ui/SearchInput.vue'
import Button from '@/components/ui/Button.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import TreeNode from '@/components/inventory/TreeNode.vue'
import type { TreeNode as TreeNodeType } from '@/components/inventory/TreeNode.vue'
import { inventoryApi, type InventoryTreeNode } from '@/services/api'
import { cn } from '@/lib/utils'
import {
  ListTree, Map, Table2,
  Router, Building2, Cable, Network, Radio, Wifi, Users,
} from 'lucide-vue-next'

type ViewMode = 'tree' | 'map' | 'table'

const viewMode = ref<ViewMode>('tree')
const search = ref('')
const loading = ref(true)
const inventoryTree = ref<TreeNodeType[]>([])
const flatRows = ref<Record<string, unknown>[]>([])
const expanded = ref(new Set<string>())
const selectedNode = ref<TreeNodeType | null>(null)
const drawerOpen = ref(false)

const typeIcons: Record<string, typeof Router> = {
  core: Router, pop: Building2, odc: Cable, odp: Network,
  olt: Radio, pon: Wifi, onu: Wifi, customer: Users, router: Router,
}

const statusColor: Record<string, string> = {
  online: 'text-success', offline: 'text-danger', warning: 'text-warning',
}

function mapTreeNode(node: InventoryTreeNode): TreeNodeType {
  return {
    id: node.id,
    name: node.name,
    type: node.type,
    status: node.status,
    capacity: node.capacity,
    usage: node.usage,
    devices: node.devices,
    children: node.children?.map(mapTreeNode),
  }
}

function collectExpanded(nodes: InventoryTreeNode[], set = new Set<string>()) {
  nodes.forEach((n) => {
    set.add(n.id)
    if (n.children?.length) collectExpanded(n.children, set)
  })
  return set
}

async function load() {
  loading.value = true
  try {
    const [treeRes, flatRes] = await Promise.all([
      inventoryApi.tree(search.value || undefined),
      inventoryApi.flat(search.value || undefined),
    ])
    inventoryTree.value = treeRes.data.data.map(mapTreeNode)
    flatRows.value = flatRes.data.data
    expanded.value = collectExpanded(treeRes.data.data)
  } finally {
    loading.value = false
  }
}

function toggleExpand(id: string) {
  if (expanded.value.has(id)) expanded.value.delete(id)
  else expanded.value.add(id)
}

function openDetail(node: TreeNodeType) {
  selectedNode.value = node
  drawerOpen.value = true
}

let searchTimeout: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(load, 400)
})

onMounted(load)
</script>

<template>
  <AppLayout title="Network Inventory" subtitle="Hierarki jaringan Core → POP → ODC → ODP → OLT → ONU → Pelanggan">
    <div class="mb-6 flex flex-wrap items-center gap-3">
      <div class="flex rounded-xl border border-border p-1">
        <button
          v-for="mode in [{ key: 'tree' as const, icon: ListTree, label: 'Tree' }, { key: 'map' as const, icon: Map, label: 'Map' }, { key: 'table' as const, icon: Table2, label: 'Table' }]"
          :key="mode.key"
          :class="cn(
            'flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors',
            viewMode === mode.key ? 'bg-primary text-white' : 'text-muted hover:text-foreground',
          )"
          @click="viewMode = mode.key"
        >
          <component :is="mode.icon" class="h-3.5 w-3.5" />
          {{ mode.label }}
        </button>
      </div>
      <div class="flex flex-1 items-center gap-2">
        <SearchInput v-model="search" placeholder="Cari semua perangkat..." class="max-w-sm" />
      </div>
    </div>

    <Skeleton v-if="loading" class="mb-6 h-96 rounded-[18px]" />

    <Card v-else-if="viewMode === 'tree'">
      <TreeNode
        v-for="node in inventoryTree"
        :key="node.id"
        :node="node"
        :depth="0"
        :expanded="expanded"
        :type-icons="typeIcons"
        :status-color="statusColor"
        @toggle="toggleExpand"
        @select="openDetail"
      />
      <p v-if="!inventoryTree.length" class="py-10 text-center text-sm text-muted">Belum ada data inventory.</p>
    </Card>

    <Card v-else-if="viewMode === 'map'" class="flex h-96 items-center justify-center">
      <div class="text-center">
        <Map class="mx-auto h-12 w-12 text-muted" />
        <p class="mt-3 text-sm font-medium">Peta Jaringan</p>
        <p class="text-xs text-muted">Integrasi Google Maps / Leaflet pada sprint berikutnya</p>
      </div>
    </Card>

    <Card v-else class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-border text-left text-xs text-muted">
            <th class="pb-3 pr-4 font-medium">Nama</th>
            <th class="pb-3 pr-4 font-medium">Tipe</th>
            <th class="pb-3 pr-4 font-medium">Status</th>
            <th class="pb-3 pr-4 font-medium">Kapasitas</th>
            <th class="pb-3 pr-4 font-medium">Penggunaan</th>
            <th class="pb-3 font-medium">Perangkat</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in flatRows"
            :key="`${row.type}-${row.id}`"
            class="border-b border-border/50 cursor-pointer hover:bg-muted/30"
          >
            <td class="py-3 pr-4 font-medium text-foreground">{{ row.name }}</td>
            <td class="py-3 pr-4"><Badge variant="secondary">{{ row.type }}</Badge></td>
            <td class="py-3 pr-4">
              <Badge :variant="row.status === 'online' ? 'success' : row.status === 'warning' ? 'warning' : 'danger'">
                {{ row.status }}
              </Badge>
            </td>
            <td class="py-3 pr-4 text-muted">{{ row.capacity }}</td>
            <td class="py-3 pr-4">
              <div class="flex items-center gap-2">
                <div class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                  <div class="h-full rounded-full bg-primary" :style="{ width: `${row.usage}%` }" />
                </div>
                <span class="text-xs">{{ row.usage }}%</span>
              </div>
            </td>
            <td class="py-3 text-muted">{{ row.devices }}</td>
          </tr>
          <tr v-if="!flatRows.length">
            <td colspan="6" class="py-10 text-center text-muted">Belum ada data.</td>
          </tr>
        </tbody>
      </table>
    </Card>

    <Teleport to="body">
      <Transition enter-active-class="transition duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100"
        leave-active-class="transition duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="drawerOpen" class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm" @click="drawerOpen = false" />
      </Transition>
      <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="translate-x-full"
        enter-to-class="translate-x-0" leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-x-0" leave-to-class="translate-x-full">
        <div v-if="drawerOpen && selectedNode"
          class="fixed right-0 top-0 z-50 flex h-full w-full max-w-md flex-col border-l border-border bg-card shadow-2xl">
          <div class="flex items-center justify-between border-b border-border p-6">
            <div>
              <h3 class="text-lg font-semibold">{{ selectedNode.name }}</h3>
              <Badge variant="secondary" class="mt-1 capitalize">{{ selectedNode.type }}</Badge>
            </div>
            <Button variant="ghost" size="icon" @click="drawerOpen = false">✕</Button>
          </div>
          <div class="flex-1 overflow-y-auto p-6 space-y-4">
            <div class="grid grid-cols-2 gap-3">
              <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/50">
                <p class="text-xs text-muted">Status</p>
                <p :class="cn('font-semibold capitalize', statusColor[selectedNode.status])">{{ selectedNode.status }}</p>
              </div>
              <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/50">
                <p class="text-xs text-muted">Kapasitas</p>
                <p class="font-semibold">{{ selectedNode.capacity }}</p>
              </div>
              <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/50">
                <p class="text-xs text-muted">Penggunaan</p>
                <p class="font-semibold">{{ selectedNode.usage }}%</p>
              </div>
              <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/50">
                <p class="text-xs text-muted">Perangkat Terhubung</p>
                <p class="font-semibold">{{ selectedNode.devices }}</p>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </AppLayout>
</template>
