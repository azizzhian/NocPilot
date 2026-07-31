<script setup lang="ts">
import { Router, ChevronRight, ChevronDown } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

export interface TreeNode {
  id: string
  name: string
  type: string
  status: string
  capacity: number
  usage: number
  devices: number
  children?: TreeNode[]
}

const props = defineProps<{
  node: TreeNode
  depth: number
  expanded: Set<string>
  typeIcons: Record<string, typeof Router>
  statusColor: Record<string, string>
}>()

const emit = defineEmits<{
  toggle: [id: string]
  select: [node: TreeNode]
}>()

const hasChildren = () => props.node.children && props.node.children.length > 0
const isExpanded = () => props.expanded.has(props.node.id)
</script>

<template>
  <div>
    <button
      class="flex w-full items-center gap-2 rounded-xl px-3 py-2.5 text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
      :style="{ paddingLeft: `${depth * 20 + 12}px` }"
      @click="emit('select', node)"
    >
      <button v-if="hasChildren()" class="shrink-0" @click.stop="emit('toggle', node.id)">
        <ChevronDown v-if="isExpanded()" class="h-4 w-4 text-muted" />
        <ChevronRight v-else class="h-4 w-4 text-muted" />
      </button>
      <span v-else class="w-4" />
      <component :is="typeIcons[node.type] || Router" class="h-4 w-4 shrink-0 text-primary" />
      <span class="flex-1 text-sm font-medium">{{ node.name }}</span>
      <span :class="cn('text-xs capitalize', statusColor[node.status])">{{ node.status }}</span>
      <span class="text-xs text-muted">{{ node.usage }}%</span>
    </button>
    <template v-if="hasChildren() && isExpanded()">
      <TreeNode
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        :depth="depth + 1"
        :expanded="expanded"
        :type-icons="typeIcons"
        :status-color="statusColor"
        @toggle="emit('toggle', $event)"
        @select="emit('select', $event)"
      />
    </template>
  </div>
</template>
