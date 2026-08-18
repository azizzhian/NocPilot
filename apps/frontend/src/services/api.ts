import api from '@/lib/api'
import type { UserRole } from '@/data/navigation'

export interface ApiUser {
  id: number
  name: string
  username: string
  email: string
  telegram_id: string | null
  telegram_username: string | null
  role: UserRole
  department: string | null
  status: string
  roles: string[]
  permissions: string[]
  last_login_at: string | null
}

export interface LoginResponse {
  message: string
  token: string
  user: ApiUser
}

export interface Customer {
  id: number
  customer_code: string
  name: string
  phone: string | null
  address: string | null
  odc_id: number | null
  odc?: { id: number; name: string; code: string } | null
  status: 'active' | 'inactive' | 'suspended'
  is_active?: boolean
  pppoe?: string | null
  email?: string | null
  package?: string | null
  area?: string | null
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export interface DashboardTopPerformer {
  user_id: number
  name: string
  count: number
}

export interface DashboardSpecialist {
  key: string
  title: string
  emoji: string
  color: string
  name: string
  count: number
  unit: string
}

export interface DashboardStats {
  period: {
    type: 'day' | 'week' | 'month' | 'year' | 'custom'
    from: string
    to: string
    label: string
    days?: number
  }
  summary: {
    activations: number
    activations_clear: number
    complaints: number
    complaints_clear: number
    dismantles: number
    dismantles_clear: number
    cctv: number
    cctv_clear?: number
    tickets?: number
    tickets_clear?: number
    noc_updates: number
  }
  category_kpis?: Array<{
    key: string
    label: string
    value: number
    open?: number
    clear?: number
    split_status?: boolean
    total?: number
    color: string
    icon?: string
    top?: DashboardTopPerformer | null
  }>
  kpis: Array<{
    key: string
    label: string
    value: number
    open?: number
    clear?: number
    split_status?: boolean
    total?: number
    color: string
    icon?: string
    top?: DashboardTopPerformer | null
  }>
  specialists?: DashboardSpecialist[]
  noc_performance: Array<{
    user_id: number
    name: string
    activations: number
    activations_open?: number
    activations_clear: number
    complaints: number
    complaints_open?: number
    complaints_clear: number
    dismantles: number
    dismantles_clear: number
    tickets_open?: number
    tickets_clear: number
    cctv?: number
    cctv_clear?: number
    total: number
    avg_per_day?: number
    contribution_pct?: number
  }>
  odc_stats?: Array<{
    odc_name: string
    complaints_open: number
    complaints_clear: number
    activations_open: number
    activations_clear: number
    tickets_open: number
    tickets_clear: number
    cctv_clear: number
    dismantles_open: number
    dismantles_clear: number
    noc_updates_open: number
    noc_updates_clear: number
    total: number
    avg_per_day?: number
    contribution_pct?: number
  }>
  complaint_client_share?: {
    total: number
    complaints_total?: number
    tickets_total?: number
    source?: 'all' | 'complaint' | 'ticket' | string
    rows: Array<{
      key: string
      name: string
      customer_code?: string | null
      odc_name?: string | null
      is_gamas?: boolean
      complaints_count?: number
      tickets_count?: number
      count: number
      pct: number
    }>
  }
  charts: {
    clear_by_noc: {
      categories: string[]
      series: Array<{ name: string; data: number[]; color?: string }>
    }
    stacked_by_noc?: {
      categories: string[]
      series: Array<{ name: string; data: number[]; color?: string }>
    }
    clear_by_type: {
      categories: string[]
      series: Array<{ name: string; data: number[]; color?: string }>
      colors?: string[]
    }
    contribution?: {
      categories: string[]
      series: Array<{ name: string; data: number[]; color?: string }>
      colors?: string[]
    }
  }
  heatmap?: {
    days: string[]
    rows: Array<{ user_id: number; name: string; values: number[] }>
  }
  recent_activities: Array<{
    id: number
    message: string
    time: string
    severity: string
  }>
  noc_users: Array<{ id: number; name: string }>
  nav_badges: {
    monitoring: number
    input_harian: number
    activations: number
    dismantles: number
  }
}

export const authApi = {
  login: (username: string, password: string) =>
    api.post<LoginResponse>('/auth/login', { username, password }),
  telegramConfig: () =>
    api.get<{ enabled: boolean; bot_username: string | null }>('/auth/telegram-config'),
  loginTelegram: (payload: Record<string, unknown>) =>
    api.post<LoginResponse>('/auth/telegram', payload),
  me: () => api.get<{ user: ApiUser }>('/auth/me'),
  updateProfile: (data: Record<string, unknown>) =>
    api.put<{ message: string; user: ApiUser }>('/auth/profile', data),
  logout: () => api.post('/auth/logout'),
}

export const dashboardApi = {
  stats: (params?: {
    period?: string
    date?: string
    from?: string
    to?: string
    user_id?: number
    odc_name?: string
    complaint_odc_name?: string
    client_share_source?: 'all' | 'complaint' | 'ticket'
  }) => api.get<DashboardStats>('/dashboard/stats', { params }),
  navBadges: () => api.get<{ nav_badges: DashboardStats['nav_badges'] }>('/dashboard/nav-badges'),
}

export const alertApi = {
  index: () => api.get<{
    events: { id: number; title: string; message: string | null; severity: string; created_at: string | null }[]
    issues: { id: string; name: string; condition: string; status: string; triggered: number; severity: string }[]
    counts: { router_offline: number; high_cpu: number; onu_problem: number }
  }>('/alerts'),
}

export const customerApi = {
  list: (params?: { search?: string; status?: string; odc_id?: number; page?: number; per_page?: number }) =>
    api.get<PaginatedResponse<Customer>>('/customers', { params }),
  stats: () => api.get<{ total: number; active: number; inactive: number; suspended: number }>('/customers/stats'),
  get: (id: number) => api.get<{ data: Customer }>(`/customers/${id}`),
  create: (data: Record<string, unknown>) => api.post<{ message: string; data: Customer }>('/customers', data),
  update: (id: number, data: Record<string, unknown>) => api.put<{ message: string; data: Customer }>(`/customers/${id}`, data),
  destroy: (id: number) => api.delete<{ message: string }>(`/customers/${id}`),
  exportCsv: () => downloadCsv('/customers/export', `pelanggan-${Date.now()}.csv`),
  importCsv: (file: File) => {
    const formData = new FormData()
    formData.append('file', file)
    return api.post<{ message: string; success: number; failed: number; errors: string[] }>(
      '/customers/import',
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    )
  },
}

export const userApi = {
  list: (params?: { search?: string; page?: number }) =>
    api.get<PaginatedResponse<ApiUser>>('/users', { params }),
  create: (data: Record<string, unknown>) => api.post('/users', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/users/${id}`, data),
  destroy: (id: number) => api.delete(`/users/${id}`),
}

export interface InventoryTreeNode {
  id: string
  name: string
  type: string
  status: string
  capacity: number
  usage: number
  devices: number
  children?: InventoryTreeNode[]
  meta?: Record<string, string>
}

export interface InternetPackage {
  id: number
  name: string
  speed_mbps: number
  price: number
  status: string
  description: string | null
}

export interface CustomerHistoryItem {
  id: number
  name: string
  customer_code: string
  pppoe: string | null
  status: string
  package: string | null
  events: { type: string; title: string; date: string | null }[]
}

function crudApi<T = Record<string, unknown>>(path: string) {
  return {
    list: (params?: Record<string, unknown>) => api.get<PaginatedResponse<T>>(path, { params }),
    create: (data: Record<string, unknown>) => api.post(path, data),
    update: (id: number, data: Record<string, unknown>) => api.put(`${path}/${id}`, data),
    destroy: (id: number) => api.delete(`${path}/${id}`),
  }
}

export const routerInventoryApi = {
  ...crudApi<RouterDevice>('/routers'),
  testConnection: (
    id: number,
    opts?: {
      username?: string
      password?: string
      ip?: string
      api_port?: number
      monitor_via?: string
      snmp_community?: string
      snmp_port?: number
    },
  ) =>
    api.post<{
      success: boolean
      message: string
      latency_ms?: number
      identity?: string | null
      host?: string
      port?: number
      username?: string
      username_source?: 'stored' | 'input'
      password_source?: 'stored' | 'input'
      password_length?: number
      community_source?: 'stored' | 'input'
    }>(`/routers/${id}/test-connection`, opts ?? {}),
}
export const popApi = crudApi('/pops')
export const odcApi = crudApi('/odcs')
export const odpApi = crudApi('/odps')
export const oltApi = crudApi('/olts')
export const onuApi = crudApi('/onus')
export const packageApi = crudApi<InternetPackage>('/packages')

export const inventoryApi = {
  tree: (search?: string) => api.get<{ data: InventoryTreeNode[] }>('/inventory/tree', { params: { search } }),
  flat: (search?: string) => api.get<{ data: Record<string, unknown>[] }>('/inventory/flat', { params: { search } }),
}

export const masterDataApi = {
  index: () => api.get<{ summary: { key: string; label: string; count: number }[]; links: { label: string; to: string }[] }>('/master-data'),
}

export const settingsApi = {
  get: () => api.get<{
    app_name?: string
    app_tagline?: string
    activity_name?: string
    timezone?: string
    locale?: string
    sidebar_favorites?: string[]
    features?: Record<string, boolean>
  }>('/settings'),
  update: (data: Record<string, unknown>) =>
    api.put<{ message: string; data: Record<string, unknown> }>('/settings', data),
}

export const roleApi = {
  list: () =>
    api.get<{
      roles: { id: number; name: string; label: string; permissions: string[] }[]
      permissions: string[]
      groups: { key: string; label: string; permissions: { name: string; label: string }[] }[]
    }>('/roles'),
  update: (id: number, permissions: string[]) =>
    api.put<{ message: string; role: { id: number; name: string; label: string; permissions: string[] } }>(
      `/roles/${id}`,
      { permissions },
    ),
}

export const customerHistoryApi = {
  list: (params?: { search?: string; page?: number }) =>
    api.get<PaginatedResponse<CustomerHistoryItem>>('/customers/history', { params }),
}

export interface RouterInterfaceItem {
  id: number
  router_id: number
  interface_name: string
  label: string | null
  display_name: string
  is_monitored: boolean
  is_running: boolean
  rx_bps?: number
  tx_bps?: number
  rx_mbps?: number
  tx_mbps?: number
  traffic_polled_at?: string | null
}

export interface RouterDevice {
  id: number
  name: string
  ip: string
  monitor_via?: 'api' | 'snmp'
  api_port?: number
  username?: string | null
  has_api_password?: boolean
  snmp_version?: string
  has_snmp_community?: boolean
  snmp_port?: number
  snmp_timeout?: number
  pop: string | null
  area: string | null
  status: 'online' | 'offline'
  cpu: number
  memory: number
  temperature: number
  uptime: string | null
  clients: number
  pppoe_sessions: number
  board: string | null
  version: string | null
  license: string | null
  download_mbps: number
  upload_mbps: number
  last_synced_at: string | null
  sync_error?: string | null
  metrics_source?: 'api' | 'snmp' | 'simulated' | 'error' | 'pending'
  interfaces?: RouterInterfaceItem[]
}

export interface InterfaceTrafficLive {
  interface_name: string
  label: string
  rx_bps: number
  tx_bps: number
  rx_mbps: number
  tx_mbps: number
  is_running: boolean
}

export interface MonitoringSummary {
  router_online: number
  router_offline: number
  router_total: number
  cpu_average: number
  memory_average: number
  temperature_average: number
  total_clients: number
  total_pppoe: number
  total_download_mbps: number
  total_upload_mbps: number
}

export const monitoringApi = {
  summary: () => api.get<MonitoringSummary>('/monitoring/summary'),
  routers: (params?: { search?: string; pop?: string; status?: string }) =>
    api.get<{ data: RouterDevice[] }>('/monitoring/routers', { params }),
  router: (id: number) => api.get<{ data: RouterDevice }>(`/monitoring/routers/${id}`),
  pops: () => api.get<{ data: string[] }>('/monitoring/pops'),
  syncAll: () => api.post('/monitoring/routers/sync-all'),
  sync: (id: number) => api.post(`/monitoring/routers/${id}/sync`),
  syncInterfaces: (routerId: number) =>
    api.post<{ message: string; count: number; data: RouterInterfaceItem[] }>(
      `/monitoring/routers/${routerId}/sync-interfaces`,
    ),
  updateInterface: (routerId: number, interfaceId: number, data: { is_monitored: boolean; label?: string }) =>
    api.patch<{ message: string; data: RouterInterfaceItem }>(
      `/monitoring/routers/${routerId}/interfaces/${interfaceId}`,
      data,
    ),
  /** Snapshot ringan dari DB (collector). Tanpa SNMP ke perangkat. */
  trafficSnapshot: () =>
    api.get<{
      polled_at: string
      source: string
      routers: Record<string, InterfaceTrafficLive[]>
    }>('/monitoring/traffic-snapshot'),
  live: (routerId: number) =>
    api.get<{ polled_at: string; traffic: InterfaceTrafficLive[]; source?: string }>(
      `/monitoring/routers/${routerId}/live`,
    ),
}

export interface ActivationItem {
  id: number
  reference: string
  customer_id: number | null
  customer_name: string
  phone: string | null
  package: string
  area: string | null
  odp: string | null
  address: string | null
  status: string
  scheduled_at: string | null
  completed_at: string | null
  notes: string | null
  assignee?: { id: number; name: string } | null
  created_at: string | null
}

export interface DismantleItem {
  id: number
  reference: string
  customer_id: number | null
  customer_name: string
  location: string | null
  customer_code: string | null
  phone: string | null
  status: string
  opened_at: string | null
  closed_at: string | null
  notes: string | null
  assignee?: { id: number; name: string } | null
  creator_name?: string | null
  created_at: string | null
}

export const activationApi = {
  list: (params?: { search?: string; status?: string }) =>
    api.get<PaginatedResponse<ActivationItem>>('/activations', { params }),
  stats: () => api.get<Record<string, number>>('/activations/stats'),
  create: (data: Record<string, unknown>) => api.post('/activations', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/activations/${id}`, data),
}

export const dismantleApi = {
  list: (params?: {
    search?: string
    status?: string
    location?: string
    from?: string
    to?: string
    page?: number
  }) => api.get<PaginatedResponse<DismantleItem>>('/dismantles', { params }),
  stats: (params?: {
    search?: string
    location?: string
    from?: string
    to?: string
  }) => api.get<Record<string, number>>('/dismantles/stats', { params }),
  create: (data: Record<string, unknown>) => api.post('/dismantles', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/dismantles/${id}`, data),
  destroy: (id: number) => api.delete(`/dismantles/${id}`),
}

export interface ReportTicketItem {
  id: number
  location: string | null
  odc_name?: string | null
  customer_code: string | null
  customer_name: string
  problem: string | null
  action: string | null
  status: 'On-Progress' | 'Clear' | 'Closed' | string
  opened_at: string | null
  closed_at: string | null
  notes: string | null
  creator_name?: string | null
  clearer_name?: string | null
}

export const reportTicketApi = {
  list: (params?: Record<string, unknown>) =>
    api.get<PaginatedResponse<ReportTicketItem>>('/report-tickets', { params }),
  stats: () => api.get<Record<string, number>>('/report-tickets/stats'),
  create: (data: Record<string, unknown>) => api.post('/report-tickets', data),
  update: (id: number, data: Record<string, unknown>) => api.put(`/report-tickets/${id}`, data),
  destroy: (id: number) => api.delete(`/report-tickets/${id}`),
  exportExcel: (params?: Record<string, unknown>) =>
    downloadFile('/report-tickets/export', `report-ticket-${Date.now()}.xlsx`, params),
}

export const technicianApi = {
  list: () => api.get<{ data: { id: number; name: string; email: string }[] }>('/technicians'),
}

export interface ActivityLogItem {
  id: number
  type: string
  user: string
  action: string
  ip_address: string | null
  browser: string | null
  device: string | null
  created_at: string | null
}

export const activityApi = {
  list: (params?: { search?: string; type?: string; scope?: 'audit' | 'activity'; page?: number; per_page?: number }) =>
    api.get<PaginatedResponse<ActivityLogItem>>('/activity-logs', { params }),
  export: (params?: { scope?: 'audit' | 'activity'; type?: string }) => {
    const q = new URLSearchParams()
    if (params?.scope) q.set('scope', params.scope)
    if (params?.type) q.set('type', params.type)
    const qs = q.toString()
    return downloadCsv(`/activity-logs/export${qs ? `?${qs}` : ''}`, `${params?.scope ?? 'activity'}-log-${Date.now()}.csv`)
  },
}

export const reportApi = {
  analytics: () => api.get<Record<string, unknown>>('/reports/analytics'),
  exportUrl: (type: string) => `${api.defaults.baseURL}/reports/export?type=${type}`,
}

export const realtimeApi = {
  feed: (since?: number) => api.get('/realtime/feed', { params: { since } }),
  markRead: () => api.post('/realtime/mark-read'),
}

export interface AppUpdateChange {
  hash: string
  message: string
  author: string
  date: string
}

export interface AppUpdateItem {
  id: number
  from_commit: string | null
  to_commit: string
  branch: string | null
  changes: AppUpdateChange[]
  deployed_at: string
  is_unread: boolean
}

export const appUpdatesApi = {
  list: () => api.get<{
    updates: AppUpdateItem[]
    unread: number
    last_read_id: number | null
  }>('/app-updates'),
  markRead: () => api.post<{ message: string; unread: number; last_read_id: number | null }>(
    '/app-updates/mark-read',
  ),
}

export async function downloadCsv(path: string, filename: string) {
  return downloadFile(path, filename)
}

export async function downloadFile(path: string, filename: string, params?: Record<string, unknown>) {
  const { data } = await api.get(path, { responseType: 'blob', params })
  const url = URL.createObjectURL(data)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}

export interface DailyEntryItem {
  id: number
  report_date: string
  customer_name?: string
  package_name?: string | null
  customer_code?: string | null
  olt_name?: string | null
  odp_name?: string | null
  port_onu?: string | null
  router?: string | null
  site_name?: string | null
  odc_name?: string | null
  phone_normalized?: string | null
  start_ticket?: string | null
  close_ticket?: string | null
  start_problem?: string | null
  end_problem?: string | null
  problem?: string | null
  action?: string | null
  description?: string
  status: string
  shift?: number | null
  notes?: string | null
  sort_order?: number
  created_by?: number | null
  creator_name?: string | null
  cleared_by?: number | null
  clearer_name?: string | null
  cleared_at?: string | null
  created_at?: string | null
  customer_id?: number | null
  complaint_type?: 'individual' | 'gamas' | string
  gamas_kind?: string | null
  location_label?: string | null
  impact?: string | null
  complaint_count_90d?: number | null
  /** Open item dari tanggal sebelum hari yang sedang dilihat */
  is_carryover?: boolean
}

export interface ComplaintHistoryItem {
  id: number
  report_date: string | null
  problem: string | null
  status: string
  odc_name: string | null
  shift: number | null
  action: string | null
  customer_name: string
  creator_name?: string | null
  cleared_at?: string | null
  created_at?: string | null
}

export interface ComplaintHistorySummary {
  total: number
  days: number
  count_30d: number
  is_repeat: boolean
  open_count: number
  clear_count: number
  clear_rate: number
  avg_clear_hours: number | null
  last_date: string | null
  last_problem: string | null
  score: {
    value: number
    label: string
    level: 'good' | 'watch' | 'risk' | string
    breakdown: { key: string; label: string; points: number }[]
  }
}

export interface ComplaintHistoryResponse {
  total: number
  days: number
  items: ComplaintHistoryItem[]
  summary?: ComplaintHistorySummary
}

export interface DailyEntryOltLookup {
  id: number
  pop_id: number
  pop_name?: string | null
  name: string
  ip?: string | null
}

export interface DailyEntryOdcLookup {
  id: number
  name: string
  code: string
}

export interface DailyEntryOdpLookup {
  id: number
  odc_id: number
  odc_name?: string | null
  name: string
  code: string
}

export interface DailyEntryPackageLookup {
  id: number
  name: string
  speed_mbps?: number | null
}

export interface DailyEntryData {
  date: string
  activations: DailyEntryItem[]
  cctv_setups: DailyEntryItem[]
  dismantles: DailyEntryItem[]
  complaints: DailyEntryItem[]
  noc_updates: DailyEntryItem[]
  lookups: {
    olts: DailyEntryOltLookup[]
    sites: string[]
    odcs: DailyEntryOdcLookup[]
    odps: DailyEntryOdpLookup[]
    routers: string[]
    packages: DailyEntryPackageLookup[]
  }
  status_options: string[]
  summary: { activations: number; complaints: number; dismantles: number }
}

export const dailyEntryApi = {
  index: (date?: string) => api.get<DailyEntryData>('/daily-entry', { params: { date } }),
  events: (date?: string, since?: number) =>
    api.get<{ events: Array<{
      id: number
      event: string
      title: string
      message: string | null
      payload: Record<string, unknown>
      created_at: string | null
    }>; latest_id: number }>('/daily-entry/events', { params: { date, since } }),
  searchCustomers: (q: string) => api.get('/daily-entry/customers/search', { params: { q } }),
  complaintHistory: (params: {
    customer_id?: number
    customer_code?: string
    phone?: string
    name?: string
    days?: number
    exclude_id?: number
  }) => api.get<ComplaintHistoryResponse>('/daily-entry/complaint-history', { params }),
  storeActivation: (data: Record<string, unknown>) => api.post('/daily-entry/activation', data),
  updateActivation: (id: number, data: Record<string, unknown>) => api.put(`/daily-entry/activation/${id}`, data),
  storeCctv: (data: Record<string, unknown>) => api.post('/daily-entry/cctv', data),
  updateCctv: (id: number, data: Record<string, unknown>) => api.put(`/daily-entry/cctv/${id}`, data),
  storeDismantle: (data: Record<string, unknown>) => api.post('/daily-entry/dismantle', data),
  updateDismantle: (id: number, data: Record<string, unknown>) => api.put(`/daily-entry/dismantle/${id}`, data),
  storeComplaint: (data: Record<string, unknown>) => api.post('/daily-entry/complaint', data),
  updateComplaint: (id: number, data: Record<string, unknown>) => api.put(`/daily-entry/complaint/${id}`, data),
  storeNocUpdate: (data: Record<string, unknown>) => api.post('/daily-entry/noc-update', data),
  updateNocUpdate: (id: number, data: Record<string, unknown>) => api.put(`/daily-entry/noc-update/${id}`, data),
  destroy: (type: string, id: number) => api.delete(`/daily-entry/${type}/${id}`),
  updateStatus: (type: string, id: number, status: string) =>
    api.patch(`/daily-entry/${type}/${id}/status`, { status }),
  listComplaints: (params: { from: string; to: string; odc_name?: string; search?: string }) =>
    api.get<{ data: DailyEntryItem[] }>('/daily-entry/list/complaints', { params }),
  listNocUpdates: (params: { from: string; to: string; odc_name?: string }) =>
    api.get<{ data: DailyEntryItem[] }>('/daily-entry/list/noc-updates', { params }),
  listActivations: (params: { from: string; to: string; search?: string }) =>
    api.get<{ data: DailyEntryItem[] }>('/daily-entry/list/activations', { params }),
  listCctvSetups: (params: { from: string; to: string; search?: string }) =>
    api.get<{ data: DailyEntryItem[] }>('/daily-entry/list/cctv', { params }),
  exportComplaints: (params: { from: string; to: string; odc_name?: string; search?: string }) =>
    downloadFile('/daily-entry/export/complaints', `komplain-${params.from}-${params.to}.xlsx`, params),
  exportNocUpdates: (params: { from: string; to: string; odc_name?: string }) =>
    downloadFile('/daily-entry/export/noc-updates', `update-noc-${params.from}-${params.to}.xlsx`, params),
}

export interface ReportTemplateMeta {
  body: string
  is_custom: boolean
  hints: Record<string, string>
}

export interface GenerateReportIndexData {
  date: string
  snapshot: {
    id: number
    report_date: string
    responsible_name: string
    daily_report_text: string
    noc_update_text: string
    monitoring_report_text: string
    created_at: string
  } | null
  noc_users: { id: number; name: string }[]
  default_responsible: string
  activity_name: string
  templates: {
    daily: ReportTemplateMeta
    noc: ReportTemplateMeta
    monitoring: ReportTemplateMeta
  }
}

export interface GenerateReportResult {
  message: string
  daily_report_text: string
  noc_update_text: string
  monitoring_report_text: string
  snapshot: Record<string, unknown>
  router_sync?: { success: number; failed: number }
}

export type ReportSection =
  | 'complaint'
  | 'activation'
  | 'cctv'
  | 'noc'
  | 'dismantle'
  | 'ticket'
  | 'monitoring'

export interface SectionReportResult {
  message: string
  section: ReportSection
  from?: string
  to?: string
  report_date: string
  text: string
}

export interface ReportSnapshotListItem {
  id: number
  report_date: string
  responsible_name: string
  created_at: string
  generator?: { id: number; name: string }
}

export const generateReportApi = {
  index: (date?: string) => api.get<GenerateReportIndexData>('/reports/generate', { params: { date } }),
  generate: (data: { report_date: string; responsible_name: string }) =>
    api.post<GenerateReportResult>('/reports/generate', data, { timeout: 180_000 }),
  generateSection: (data: { section: ReportSection; from: string; to: string; report_date?: string }) =>
    api.post<SectionReportResult>('/reports/generate/section', data, { timeout: 180_000 }),
  history: (page = 1) =>
    api.get<{ data: ReportSnapshotListItem[]; current_page: number; last_page: number }>(
      '/reports/generate/history',
      { params: { page } },
    ),
  show: (id: number) =>
    api.get<{ data: ReportSnapshotListItem & {
      daily_report_text: string
      noc_update_text: string
      monitoring_report_text: string
    } }>(`/reports/generate/history/${id}`),
  updateTemplate: (type: 'daily' | 'noc' | 'monitoring', body: string) =>
    api.put('/reports/generate/templates', { type, body }),
  resetTemplate: (type: 'daily' | 'noc' | 'monitoring') =>
    api.post<{ body: string }>('/reports/generate/templates/reset', { type }),
}
