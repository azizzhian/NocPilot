import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { permissionForPath } from '@/data/navigation'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue'), meta: { guest: true } },
    { path: '/profile', name: 'profile', component: () => import('@/views/ProfileView.vue') },
    { path: '/', name: 'dashboard', component: () => import('@/views/DashboardView.vue'), meta: { permission: 'dashboard.view' } },
    { path: '/monitoring', name: 'monitoring', component: () => import('@/views/MonitoringView.vue'), meta: { permission: 'monitoring.view' } },
    { path: '/input-harian', redirect: '/aktivasi' },
    {
      path: '/komplain',
      name: 'komplain',
      component: () => import('@/views/InputHarianView.vue'),
      meta: {
        dailyTab: 'complaint',
        title: 'Komplain',
        subtitle: 'Input dan pantau komplain harian NOC',
        permission: 'complaint.view',
      },
    },
    {
      path: '/aktivasi',
      name: 'aktivasi',
      component: () => import('@/views/InputHarianView.vue'),
      meta: {
        dailyTabs: ['activation', 'cctv', 'noc'],
        title: 'Aktivasi',
        subtitle: 'Aktivasi, setup CCTV, dan update NOC harian',
        permission: 'activation.view',
      },
    },
    { path: '/dismantle', redirect: '/report/dismantle' },
    { path: '/report/dismantle', name: 'report-dismantle', component: () => import('@/views/DismantleView.vue'), meta: { permission: 'dismantle.view' } },
    { path: '/report/ticket', name: 'report-ticket', component: () => import('@/views/ReportTicketView.vue'), meta: { permission: 'ticket.view' } },
    { path: '/pelanggan', name: 'pelanggan', component: () => import('@/views/PelangganView.vue'), meta: { permission: 'customer.view' } },
    { path: '/paket', name: 'paket', component: () => import('@/views/PaketView.vue'), meta: { permission: 'package.view' } },
    { path: '/riwayat-pelanggan', name: 'riwayat-pelanggan', component: () => import('@/views/RiwayatPelangganView.vue'), meta: { permission: 'customer.view' } },
    { path: '/router', name: 'router', component: () => import('@/views/RouterView.vue'), meta: { permission: 'network.view' } },
    { path: '/olt', name: 'olt', component: () => import('@/views/OltView.vue'), meta: { permission: 'network.view' } },
    { path: '/onu', name: 'onu', component: () => import('@/views/OnuView.vue'), meta: { permission: 'network.view' } },
    { path: '/pop', name: 'pop', component: () => import('@/views/PopView.vue'), meta: { permission: 'network.view' } },
    { path: '/odc', name: 'odc', component: () => import('@/views/OdcView.vue'), meta: { permission: 'network.view' } },
    { path: '/odp', name: 'odp', component: () => import('@/views/OdpView.vue'), meta: { permission: 'network.view' } },
    { path: '/inventory', name: 'inventory', component: () => import('@/views/InventoryView.vue'), meta: { permission: 'network.view' } },
    { path: '/report', redirect: '/report/generate' },
    { path: '/report/generate', name: 'report-generate', component: () => import('@/views/GenerateReportView.vue'), meta: { permission: 'report.generate' } },
    { path: '/report/history', name: 'report-history', component: () => import('@/views/ReportHistoryView.vue'), meta: { permission: 'report.view' } },
    { path: '/report/history/:id', name: 'report-show', component: () => import('@/views/ReportShowView.vue'), meta: { permission: 'report.view' } },
    { path: '/analytics', redirect: '/' },
    { path: '/master-data', name: 'master-data', component: () => import('@/views/MasterDataView.vue'), meta: { permission: 'master.view' } },
    { path: '/users', name: 'users', component: () => import('@/views/UsersView.vue'), meta: { permission: 'user.view' } },
    { path: '/roles', name: 'roles', component: () => import('@/views/RolesView.vue'), meta: { permission: 'role.manage' } },
    { path: '/audit-log', name: 'audit-log', component: () => import('@/views/ActivityLogView.vue'), meta: { permission: 'audit.view', logScope: 'audit' } },
    { path: '/activity-log', name: 'activity-log', component: () => import('@/views/ActivityLogView.vue'), meta: { permission: 'audit.view', logScope: 'activity' } },
    { path: '/settings', name: 'settings', component: () => import('@/views/SettingsView.vue'), meta: { permission: 'settings.manage' } },
    { path: '/alerts', name: 'alerts', component: () => import('@/views/AlertCenterView.vue'), meta: { permission: 'monitoring.view' } },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (auth.token && !auth.user) {
    await auth.fetchUser()
  }

  if (to.meta.guest) {
    if (auth.isAuthenticated) return '/'
    return true
  }

  if (!auth.isAuthenticated) return '/login'

  const permission =
    (typeof to.meta.permission === 'string' ? to.meta.permission : undefined)
    ?? permissionForPath(to.path)

  if (permission && !auth.can(permission)) {
    // Redirect to first allowed overview page
    if (auth.can('dashboard.view')) return '/'
    if (auth.can('monitoring.view')) return '/monitoring'
    if (auth.can('complaint.view')) return '/komplain'
    if (auth.can('activation.view')) return '/aktivasi'
    return '/login'
  }

  return true
})

export default router
