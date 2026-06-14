<script setup>
import { Link, usePage, router } from '@inertiajs/vue3'
import { 
    LayoutDashboard, 
    Utensils, 
    Database,
    ChevronDown,
    Tags,
    Settings,
    LogOut,
    Plus,
    ShieldCheck,
    History,
    HardDriveDownload,
    Users as UsersIcon,
    MapPin,
    Terminal,
    UserCircle,
    BarChart3,
    DollarSign,
    Package,
    Layers,
    ClipboardList,
    Wallet,
    BookOpen,
    Menu as MenuIcon,
    X as XIcon,
    CreditCard,
    ChefHat,
    ListTodo,
    Trash2,
    Gift,
    Box
} from '@lucide/vue'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { cn } from '@/lib/utils'
import LanguageToggle from '@/Components/shared/LanguageToggle.vue'
import RemoteScannerHandler from '@/Components/feature-specific/RemoteScannerHandler.vue'

const page = usePage()
const masterOpen = ref(false)
const inventoryOpen = ref(true) // Open by default
const financeOpen = ref(false)
const systemOpen = ref(false)
const sidebarOpen = ref(true)
const isMobile = ref(false)

// UI Translations
const ui = computed(() => page.props.translations?.ui || {})

// Check window size to determine mobile state and set initial sidebar state
const checkWindowSize = () => {
    isMobile.value = window.innerWidth < 1024
    if (isMobile.value) {
        sidebarOpen.value = false
    } else {
        sidebarOpen.value = true
    }
}

onMounted(() => {
    checkWindowSize()
    window.addEventListener('resize', checkWindowSize)
})

onUnmounted(() => {
    window.removeEventListener('resize', checkWindowSize)
})

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value
}

const userPermissions = computed(() => page.props.auth?.user?.permissions || [])
const isSuperadmin = computed(() => page.props.auth?.user?.is_superadmin || false)

const hasPermission = (permission) => {
    if (!permission) return true
    if (isSuperadmin.value) return true
    return userPermissions.value.includes(permission)
}

const navigation = computed(() => [
    { name: ui.value.dashboard || 'Dashboard', href: route('dashboard'), icon: LayoutDashboard, current: page.url === '/', permission: 'dashboard.view' },
    { name: ui.value.payments || 'Payments',  href: route('payments.index'), icon: CreditCard, current: page.url.startsWith('/payments'), permission: 'payments.view' },
    { name: ui.value.orders || 'Orders', href: route('orders.index'), icon: ListTodo, current: page.url.startsWith('/orders'), permission: 'orders.view' },
    { name: ui.value.kitchen || 'Kitchen',  href: route('kitchen.index'), icon: ChefHat, current: page.url.startsWith('/kitchen'), permission: 'kitchen.view' },
    { name: ui.value.menu_data || 'Menu Data', href: route('menu.index'), icon: Utensils, current: page.url.startsWith('/menu'), permission: 'menu.view' },
    { name: ui.value.packages || 'Packages', href: route('packages.index'), icon: Tags, current: page.url.startsWith('/packages'), permission: 'master.promotions.view' },
    { name: ui.value.promotions || 'Promotions', href: route('promotions.index'), icon: Gift, current: page.url.startsWith('/promotions'), permission: 'master.promotions.view' },
].filter(item => hasPermission(item.permission)))

const financeItems = computed(() => [
    { name: ui.value.operationals || 'Operationals', href: route('finance.operationals.index'), icon: Wallet, permission: 'finance.operationals.view' },
    { name: ui.value.reports || 'Reports', href: route('finance.reports'), icon: BarChart3, permission: 'finance.reports.view' },
    { name: ui.value.salaries || 'Salaries', href: route('finance.salaries.index'), icon: DollarSign, permission: 'finance.salaries.view' },
].filter(item => hasPermission(item.permission)).sort((a, b) => a.name.localeCompare(b.name)))

const masterItems = computed(() => [
    { name: ui.value.addons || 'Add-ons', href: route('master.addons.index'), icon: Plus, permission: 'master.addons.view' },
    { name: ui.value.branches || 'Branches', href: route('master.branches.index'), icon: MapPin, permission: 'master.branches.view' },
    { name: ui.value.categories || 'Categories', href: route('master.categories.index'), icon: Tags, permission: 'master.categories.view' },
    { name: ui.value.tables || 'Tables', href: route('master.tables.index'), icon: Database, permission: 'master.tables.view' },
    { name: ui.value.users || 'Users', href: route('master.users.index'), icon: UsersIcon, permission: 'master.users.view' },
].filter(item => hasPermission(item.permission)).sort((a, b) => a.name.localeCompare(b.name)))

const inventoryItems = computed(() => [
    { name: ui.value.inventory_item || 'Inventory Item', href: route('item.data-items.index'), icon: Package, permission: 'item.items.view' },
    { name: ui.value.inventory_stock || 'Inventory Stock', href: route('item.stocks.index'), icon: Layers, permission: 'item.item-stocks.view' },
    { name: ui.value.stock_in || 'Stock In', href: route('item.stock-in.index'), icon: Plus, permission: 'item.item-stocks.view' },
    { name: ui.value.stock_out || 'Stock Out', href: route('item.stock-out.index'), icon: Trash2, permission: 'item.item-stocks.view' },
    { name: ui.value.inventory_damage || 'Inventory Damage', href: route('item.opname.index'), icon: ClipboardList, permission: 'item.item-logs.view' },
].filter(item => hasPermission(item.permission)).sort((a, b) => a.name.localeCompare(b.name)))

const systemItems = computed(() => [
    { name: ui.value.activity_log || 'Activity Log', href: route('system.activity-log'), icon: History, permission: 'system.activity-log.view' },
    { name: ui.value.backup_database || 'Backup Database', href: route('system.backup.database'), icon: HardDriveDownload, permission: 'system.backup.manage' },
    { name: ui.value.permissions || 'Permissions', href: route('system.permissions'), icon: ShieldCheck, permission: 'system.permissions.manage' },
    { name: ui.value.recycle_bin || 'Recycle Bin', href: route('system.recycle-bin.index'), icon: Trash2, permission: 'system.settings.manage' },
    { name: ui.value.system_settings || 'Settings', href: route('system.settings'), icon: Settings, permission: 'system.settings.manage' },
].filter(item => hasPermission(item.permission)).sort((a, b) => a.name.localeCompare(b.name)))

const canShowFinance = computed(() => financeItems.value.length > 0)
const canShowInventory = computed(() => inventoryItems.value.length > 0)
const canShowMaster = computed(() => masterItems.value.length > 0)
const canShowSystem = computed(() => systemItems.value.length > 0)
</script>

<template>
    <div class="flex h-screen bg-background overflow-hidden relative">
        <RemoteScannerHandler />
        <!-- Mobile Sidebar Overlay -->
        <transition
            enter-active-class="transition-opacity duration-300 ease-in-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-300 ease-in-out"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isMobile && sidebarOpen"
                class="fixed inset-0 bg-background/80 backdrop-blur-sm z-30 lg:hidden"
                @click="sidebarOpen = false"
            ></div>
        </transition>

        <!-- Sidebar -->
        <aside
            :class="cn(
                'absolute lg:relative bg-card border-secondary z-40 h-full transition-all duration-300 ease-in-out overflow-hidden shrink-0 flex flex-col',
                sidebarOpen ? 'w-64 translate-x-0 border-r opacity-100' : 'w-0 -translate-x-full lg:translate-x-0 border-r-0 opacity-0'
            )"
        >
            <div
                :class="cn(
                    'w-64 flex flex-col h-full shrink-0 transition-transform duration-300 ease-in-out',
                    sidebarOpen ? 'translate-x-0' : '-translate-x-12'
                )"
            >
                <div class="p-8 text-center border-b border-secondary/50 bg-secondary/5 relative">
                <!-- Close button for mobile -->
                <button
                    @click="sidebarOpen = false"
                    class="absolute top-4 right-4 p-2 rounded-lg bg-secondary text-muted-foreground hover:text-primary lg:hidden"
                >
                    <XIcon class="w-4 h-4" />
                </button>
                <Link :href="route('dashboard')" class="flex flex-col items-center justify-center gap-4">       
                    <div class="w-20 h-20 bg-primary rounded-2xl flex items-center justify-center text-primary-foreground font-black shadow-lg shadow-primary/20 text-3xl overflow-hidden group hover:scale-105 transition-transform duration-300">
                        <img v-if="$page.props.system?.logo" :src="$page.props.system.logo" class="w-full h-full object-cover" />
                        <span v-else>{{ $page.props.system?.name?.charAt(0) || 'P' }}</span>
                    </div>
                    <div class="space-y-0.5">
                        <h2 class="text-lg font-black tracking-tighter text-primary uppercase leading-none">{{ $page.props.system?.name || 'POS System' }}</h2>
                        <p class="text-[9px] font-black text-muted-foreground/60 uppercase tracking-[0.2em]">{{ ui.management_system || 'Management System' }}</p>
                    </div>
                </Link>
            </div>

            <nav class="flex-1 px-4 space-y-1 overflow-y-auto pt-6 no-scrollbar">
                <template v-for="item in navigation" :key="item.name">
                    <Link
                        :href="item.href"
                        :class="cn(
                            'flex items-center gap-3 px-3 py-3 text-sm font-bold rounded-xl transition-all duration-200 group',
                            item.current
                                ? 'bg-primary text-primary-foreground shadow-lg shadow-primary/20'
                                : 'text-muted-foreground hover:bg-secondary hover:text-primary'
                        )"
                    >
                        <component :is="item.icon" class="w-5 h-5 shrink-0" />
                        {{ item.name }}
                    </Link>
                </template>

                <div v-if="canShowFinance || canShowInventory || canShowMaster || canShowSystem" class="pt-4 pb-2 px-3">
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/50">{{ ui.management || 'Management' }}</p>
                </div>

                <!-- Collapsible Finance -->
                <div v-if="canShowFinance" class="space-y-1">
                    <button
                        @click="financeOpen = !financeOpen"
                        class="w-full flex items-center justify-between px-3 py-3 text-sm font-bold text-muted-foreground rounded-xl hover:bg-secondary hover:text-primary transition-all group"
                    >
                        <div class="flex items-center gap-3">
                            <BarChart3 class="w-5 h-5 shrink-0" />
                            {{ ui.finance || 'Finance' }}
                        </div>
                        <ChevronDown
                            :class="cn('w-4 h-4 transition-transform duration-200', financeOpen && 'rotate-180')"
                        />
                    </button>

                    <div v-if="financeOpen" class="pl-11 space-y-1 mt-1">
                        <template v-for="sub in financeItems" :key="sub.name">
                            <Link
                                :href="sub.href"
                                class="flex items-center gap-3 px-3 py-2 text-xs font-bold text-muted-foreground rounded-lg hover:text-primary transition-colors"
                            >
                                {{ sub.name }}
                            </Link>
                        </template>
                    </div>
                </div>

                <!-- Collapsible Inventory -->
                <div v-if="canShowInventory" class="space-y-1">
                    <button
                        @click="inventoryOpen = !inventoryOpen"
                        class="w-full flex items-center justify-between px-3 py-3 text-sm font-bold text-muted-foreground rounded-xl hover:bg-secondary hover:text-primary transition-all group"
                    >
                        <div class="flex items-center gap-3">
                            <Layers class="w-5 h-5 shrink-0" />
                            {{ ui.inventory || 'Inventory' }}
                        </div>
                        <ChevronDown
                            :class="cn('w-4 h-4 transition-transform duration-200', inventoryOpen && 'rotate-180')" 
                        />
                    </button>

                    <div v-if="inventoryOpen" class="pl-11 space-y-1 mt-1">
                        <template v-for="sub in inventoryItems" :key="sub.name">
                            <Link
                                :href="sub.href"
                                class="flex items-center gap-3 px-3 py-2 text-xs font-bold text-muted-foreground rounded-lg hover:text-primary transition-colors"
                            >
                                {{ sub.name }}
                            </Link>
                        </template>
                    </div>
                </div>

                <!-- Collapsible Master Data -->
                <div v-if="canShowMaster" class="space-y-1">
                    <button
                        @click="masterOpen = !masterOpen"
                        class="w-full flex items-center justify-between px-3 py-3 text-sm font-bold text-muted-foreground rounded-xl hover:bg-secondary hover:text-primary transition-all group"
                    >
                        <div class="flex items-center gap-3">
                            <Database class="w-5 h-5 shrink-0" />
                            {{ ui.master_data || 'Master Data' }}
                        </div>
                        <ChevronDown
                            :class="cn('w-4 h-4 transition-transform duration-200', masterOpen && 'rotate-180')"
                        />
                    </button>

                    <div v-if="masterOpen" class="pl-11 space-y-1 mt-1">
                        <template v-for="sub in masterItems" :key="sub.name">
                            <Link
                                :href="sub.href"
                                class="flex items-center gap-3 px-3 py-2 text-xs font-bold text-muted-foreground rounded-lg hover:text-primary transition-colors"
                            >
                                {{ sub.name }}
                            </Link>
                        </template>
                    </div>
                </div>

                <!-- Collapsible System -->
                <div v-if="canShowSystem" class="space-y-1">
                    <button
                        @click="systemOpen = !systemOpen"
                        class="w-full flex items-center justify-between px-3 py-3 text-sm font-bold text-muted-foreground rounded-xl hover:bg-secondary hover:text-primary transition-all group"
                    >
                        <div class="flex items-center gap-3">
                            <Terminal class="w-5 h-5 shrink-0" />
                            {{ ui.system || 'System' }}
                        </div>
                        <ChevronDown
                            :class="cn('w-4 h-4 transition-transform duration-200', systemOpen && 'rotate-180')"
                        />
                    </button>

                    <div v-if="systemOpen" class="pl-11 space-y-1 mt-1">
                        <template v-for="sub in systemItems" :key="sub.name">
                            <Link
                                :href="sub.href"
                                class="flex items-center gap-3 px-3 py-2 text-xs font-bold text-muted-foreground rounded-lg hover:text-primary transition-colors"
                            >
                                {{ sub.name }}
                            </Link>
                        </template>
                    </div>
                </div>
            </nav>

            <!-- User Detail Section (Clickable) -->
            <div class="p-4 border-t border-secondary mt-auto bg-secondary/10 shrink-0">
                <Link
                    :href="route('profile')"
                    class="flex items-center gap-3 px-3 py-4 mb-3 rounded-2xl bg-card border border-secondary shadow-sm hover:shadow-md hover:border-primary/20 transition-all group"
                >
                    <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-primary-foreground transition-colors shrink-0">
                        <UserCircle class="w-6 h-6" />
                    </div>
                    <div class="flex flex-col min-w-0">
                        <span class="text-xs font-black text-primary truncate">{{ $page.props.auth?.user?.name || 'Staff' }}</span>
                        <span class="text-[10px] text-muted-foreground uppercase font-bold tracking-tighter">{{ ui.my_profile || 'My Profile' }}</span>
                    </div>
                </Link>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="w-full flex items-center gap-3 px-4 py-3 text-xs font-black uppercase tracking-widest text-destructive hover:bg-destructive/5 rounded-xl transition-all"
                >
                    <LogOut class="w-4 h-4" />
                    {{ ui.logout || 'Logout' }}
                </Link>
            </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-1 flex flex-col min-w-0 bg-[#fffcf3]/30 overflow-y-auto transition-all duration-300"> 
            <!-- Topbar with Toggle -->
            <header class="bg-background/80 backdrop-blur-sm border-b border-secondary/50 sticky top-0 z-20 px-6 py-4 flex items-center gap-4">
                <button
                    @click="toggleSidebar"
                    class="p-2 rounded-xl bg-secondary text-primary hover:bg-primary/10 transition-colors shadow-sm"
                >
                    <MenuIcon class="w-5 h-5" />
                </button>
                <div class="min-w-0 flex-1">
                    <h1 class="text-sm font-black uppercase tracking-widest text-foreground truncate">
                        {{ navigation.find(n => n.current)?.name || financeItems.find(n => $page.url.includes(n.href))?.name || inventoryItems.find(n => $page.url.includes(n.href))?.name || masterItems.find(n => $page.url.includes(n.href))?.name || systemItems.find(n => $page.url.includes(n.href))?.name || ui.dashboard || 'Dashboard' }}
                    </h1>
                </div>

                <LanguageToggle />
            </header>

            <div class="p-4 lg:p-8 max-w-7xl w-full flex-1 mx-auto">
                <slot />
            </div>

            <!-- Global Footer -->
            <footer class="w-full py-6 px-8 border-t border-secondary/50 flex flex-col items-center justify-center gap-4 bg-white/20 backdrop-blur-sm mt-auto">
                <p class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground text-center">   
                    &copy; {{ new Date().getFullYear() }} {{ $page.props.system?.name || ui.pos_system || 'POS System' }} {{ ui.all_rights_reserved || 'All Rights Reserved' }}
                </p>
            </footer>
        </main>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>