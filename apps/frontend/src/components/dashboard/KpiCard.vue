<script setup lang="ts">
import { computed } from 'vue'
import { cn, formatNumber } from '@/lib/utils'
import Card from '@/components/ui/Card.vue'
import {
  Router, Radio, Wifi, Users, Ticket, Zap, Trash2, WifiOff, Camera,
} from 'lucide-vue-next'

const props = defineProps<{
  label: string
  value: number
  total?: number
  color: string
  icon: string
  delay?: number
  topName?: string | null
  topCount?: number | null
}>()

const iconMap: Record<string, typeof Router> = {
  router: Router,
  'router-off': WifiOff,
  olt: Radio,
  'olt-off': Radio,
  onu: Wifi,
  pppoe: Wifi,
  'pppoe-off': WifiOff,
  users: Users,
  ticket: Ticket,
  activation: Zap,
  dismantle: Trash2,
  cctv: Camera,
}

const colorMap: Record<string, { bg: string; text: string; icon: string; ring: string }> = {
  success: { bg: 'bg-success/10', text: 'text-success', icon: 'text-success', ring: 'ring-success/20' },
  danger: { bg: 'bg-danger/10', text: 'text-danger', icon: 'text-danger', ring: 'ring-danger/20' },
  warning: { bg: 'bg-warning/10', text: 'text-warning', icon: 'text-warning', ring: 'ring-warning/20' },
  info: { bg: 'bg-info/10', text: 'text-info', icon: 'text-info', ring: 'ring-info/20' },
  primary: { bg: 'bg-primary/10', text: 'text-primary', icon: 'text-primary', ring: 'ring-primary/20' },
}

const colors = computed(() => colorMap[props.color] ?? colorMap.primary)
const Icon = computed(() => iconMap[props.icon] ?? Router)
const percentage = computed(() =>
  props.total ? Math.round((props.value / props.total) * 100) : null,
)
</script>

<template>
  <Card
    :hover="true"
    padding="sm"
    :class="cn('animate-slide-up')"
    :style="{ animationDelay: `${delay ?? 0}ms` }"
  >
    <div class="flex items-start justify-between">
      <div :class="cn('flex h-10 w-10 items-center justify-center rounded-xl', colors.bg)">
        <component :is="Icon" :class="cn('h-5 w-5', colors.icon)" />
      </div>
      <span v-if="percentage !== null" :class="cn('text-xs font-medium', colors.text)">
        {{ percentage }}%
      </span>
    </div>
    <div class="mt-3">
      <p :class="cn('text-2xl font-bold tracking-tight', colors.text)">
        {{ formatNumber(value) }}
      </p>
      <p class="mt-0.5 text-xs text-muted">{{ label }}</p>
      <p
        v-if="topName"
        class="mt-2 truncate rounded-lg bg-muted/40 px-2 py-1 text-[11px] font-medium text-foreground"
        :title="`${topName} (${topCount ?? 0})`"
      >
        👑 {{ topName }}
        <span class="text-muted">({{ topCount ?? 0 }})</span>
      </p>
      <p v-else class="mt-2 text-[11px] text-muted/70">Belum ada top performer</p>
    </div>
  </Card>
</template>
