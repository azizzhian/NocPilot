import {
  LayoutDashboard,
  Activity,
  Zap,
  Trash2,
  Users,
  Package,
  History,
  Router,
  Radio,
  Wifi,
  Building2,
  Cable,
  Network,
  Database,
  FileText,
  Settings,
  Shield,
  ScrollText,
  ListTodo,
  UserCog,
  AlertTriangle,
  Ticket,
  ClipboardList,
  type LucideIcon,
} from 'lucide-vue-next'

export type UserRole = 'administrator' | 'manager' | 'noc' | 'engineer' | 'teknisi' | 'finance'

export interface NavItem {
  label: string
  to: string
  icon: LucideIcon
  badge?: number
  favorite?: boolean
  /** Urutan di section Favorit (angka lebih kecil = lebih atas) */
  favoriteOrder?: number
  /** Spatie permission required to see this menu item */
  permission?: string
  /** @deprecated Prefer `permission`. Kept as fallback for older code. */
  roles?: UserRole[]
}

export interface NavSection {
  title: string
  items: NavItem[]
}

export const navigation: NavSection[] = [
  {
    title: 'OVERVIEW',
    items: [
      { label: 'Dashboard', to: '/', icon: LayoutDashboard, favorite: true, favoriteOrder: 1, permission: 'dashboard.view' },
      { label: 'Monitoring', to: '/monitoring', icon: Activity, permission: 'monitoring.view' },
    ],
  },
  {
    title: 'OPERASIONAL',
    items: [
      { label: 'Komplain', to: '/komplain', icon: AlertTriangle, favorite: true, favoriteOrder: 2, permission: 'complaint.view' },
      { label: 'Aktivasi', to: '/aktivasi', icon: Zap, favorite: true, favoriteOrder: 4, permission: 'activation.view' },
      { label: 'Update NOC', to: '/update-noc', icon: ClipboardList, favorite: true, favoriteOrder: 5, permission: 'activation.view' },
    ],
  },
  {
    title: 'REPORT',
    items: [
      { label: 'Dismantle', to: '/report/dismantle', icon: Trash2, permission: 'dismantle.view' },
      { label: 'Ticket', to: '/report/ticket', icon: Ticket, favorite: true, favoriteOrder: 3, permission: 'ticket.view' },
    ],
  },
  {
    title: 'PELANGGAN',
    items: [
      { label: 'Master Pelanggan', to: '/pelanggan', icon: Users, permission: 'customer.view' },
      { label: 'Paket Internet', to: '/paket', icon: Package, permission: 'package.view' },
      { label: 'Riwayat Pelanggan', to: '/riwayat-pelanggan', icon: History, permission: 'customer.view' },
    ],
  },
  {
    title: 'JARINGAN',
    items: [
      { label: 'Router MikroTik', to: '/router', icon: Router, permission: 'network.view' },
      { label: 'OLT', to: '/olt', icon: Radio, permission: 'network.view' },
      { label: 'ONU', to: '/onu', icon: Wifi, permission: 'network.view' },
      { label: 'POP', to: '/pop', icon: Building2, permission: 'network.view' },
      { label: 'ODC', to: '/odc', icon: Cable, permission: 'network.view' },
      { label: 'ODP', to: '/odp', icon: Network, permission: 'network.view' },
      { label: 'Network Inventory', to: '/inventory', icon: Database, permission: 'network.view' },
    ],
  },
  {
    title: 'LAPORAN',
    items: [
      { label: 'History Report', to: '/report/history', icon: History, permission: 'report.view' },
      { label: 'Template Report', to: '/report/generate', icon: FileText, permission: 'report.generate' },
    ],
  },
  {
    title: 'ADMINISTRASI',
    items: [
      { label: 'Master Data', to: '/master-data', icon: Database, permission: 'master.view' },
      { label: 'Kelola User', to: '/users', icon: UserCog, permission: 'user.view' },
      { label: 'Role & Permission', to: '/roles', icon: Shield, permission: 'role.manage' },
      { label: 'Audit Log', to: '/audit-log', icon: ScrollText, permission: 'audit.view' },
      { label: 'Activity Log', to: '/activity-log', icon: ListTodo, permission: 'audit.view' },
      { label: 'Pengaturan', to: '/settings', icon: Settings, permission: 'settings.manage' },
    ],
  },
]

/** Flat list of all nav items */
export function allNavItems(): NavItem[] {
  return navigation.flatMap((s) => s.items)
}

/** Default favorite paths (urutan global bawaan) */
export function defaultFavoritePaths(): string[] {
  return allNavItems()
    .filter((i) => i.favorite)
    .sort((a, b) => (a.favoriteOrder ?? 999) - (b.favoriteOrder ?? 999))
    .map((i) => i.to)
}

/** Map route path → permission (for router guards) */
export function permissionForPath(path: string): string | undefined {
  const exact = navigation.flatMap((s) => s.items).find((i) => i.to === path)
  if (exact?.permission) return exact.permission
  // nested report history detail
  if (path.startsWith('/report/history/')) return 'report.view'
  if (path === '/alerts') return 'monitoring.view'
  return undefined
}
