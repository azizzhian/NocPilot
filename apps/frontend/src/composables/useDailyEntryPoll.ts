import { ref, watch, onUnmounted, type Ref } from 'vue'
import { dailyEntryApi } from '@/services/api'
import type { DailyEntryItem } from '@/services/api'

export interface DailyEntryRealtimePayload {
  action: 'created' | 'updated' | 'deleted'
  report_date: string
  complaint_id: number
  complaint?: DailyEntryItem | null
}

export interface DailyEntryRealtimeEvent {
  id: number
  event: string
  title: string
  message: string | null
  payload: DailyEntryRealtimePayload
  created_at: string | null
}

const POLL_MS = 3000

export function useDailyEntryPoll(
  date: Ref<string>,
  onEvent: (event: DailyEntryRealtimeEvent) => void,
) {
  const connected = ref(false)
  const lastEventId = ref(0)

  let timer: ReturnType<typeof setInterval> | null = null
  let active = false
  let polling = false

  function clearTimer() {
    if (timer) {
      clearInterval(timer)
      timer = null
    }
  }

  async function poll() {
    if (!active || polling) return
    polling = true

    try {
      const res = await dailyEntryApi.events(
        date.value,
        lastEventId.value > 0 ? lastEventId.value : undefined,
      )

      connected.value = true

      if (lastEventId.value === 0 && res.data.latest_id > 0) {
        lastEventId.value = res.data.latest_id
      }

      for (const event of res.data.events) {
        if (event.id > lastEventId.value) {
          lastEventId.value = event.id
        }
        onEvent(event as unknown as DailyEntryRealtimeEvent)
      }

      if (res.data.latest_id > lastEventId.value) {
        lastEventId.value = res.data.latest_id
      }
    } catch {
      connected.value = false
    } finally {
      polling = false
    }
  }

  function start() {
    if (active) return
    active = true
    void poll()
    timer = setInterval(() => void poll(), POLL_MS)
  }

  function stop() {
    active = false
    clearTimer()
    connected.value = false
  }

  watch(date, () => {
    lastEventId.value = 0
    if (active) void poll()
  })

  onUnmounted(stop)

  return { connected, start, stop }
}
