<script setup lang="ts">
import { computed } from 'vue'
import { cn, formatNumber } from '@/lib/utils'
import Card from '@/components/ui/Card.vue'
import {
  Router, Radio, Wifi, Users, Ticket, Zap, Trash2, WifiOff,
} from 'lucide-vue-next'

const props = defineProps<{
  label: string
  value: number
  total?: number
  color: string
  icon: string
  delay?: number
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
}

const colorMap: Record<string, { bg: string; text: string; icon: string }> = {
  success: { bg: 'bg-success/10', text: 'text-success', icon: 'text-success' },
  danger: { bg: 'bg-danger/10', text: 'text-danger', icon: 'text-danger' },
  warning: { bg: 'bg-warning/10', text: 'text-warning', icon: 'text-warning' },
  info: { bg: 'bg-info/10', text: 'text-info', icon: 'text-info' },
  primary: { bg: 'bg-primary/10', text: 'text-primary', icon: 'text-primary' },
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
    </div>
  </Card>
</template>
