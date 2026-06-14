<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, usePage, Link } from '@inertiajs/vue3'
import Card from '@/Components/ui/Card.vue'
import { 
    TrendingUp, 
    Users, 
    ShoppingBag, 
    ArrowUpRight,
    DollarSign,
    BarChart,
    LineChart,
    AreaChart
} from '@lucide/vue'
import { ref, computed, onMounted, onUnmounted } from 'vue'

defineOptions({ layout: AppLayout })

const page = usePage()
const props = defineProps({
    income: {
        type: Number,
        default: 0
    },
    chartData: {
        type: Object,
        default: () => ({ labels: [], series: [] })
    },
    stats: {
        type: Object,
        default: () => ({ totalOrders: 0, todayOrders: 0 })
    },
    errors: Object,
    auth: Object,
    ziggy: Object,
    system: Object,
    locale: String,
    translations: Object,
})

const ui = computed(() => page.props.translations?.ui || {})

const chartType = ref('area') // 'area', 'line', or 'bar'

const formatPrice = (price) => {
    return new Intl.NumberFormat(page.props.locale === 'id' ? 'id-ID' : 'en-US', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(price || 0)
}

const dashboardStats = computed(() => [
    { name: ui.value.total_revenue || 'Total Revenue', value: formatPrice(props.income), icon: TrendingUp, trend: 'Total' },
    { name: ui.value.total_orders || 'Total Orders', value: props.stats?.totalOrders || 0, icon: ShoppingBag, trend: 'Lifetime' },
    { name: ui.value.today_orders || 'Today\'s Orders', value: props.stats?.todayOrders || 0, icon: Users, trend: 'Daily' },
])

const isMobileView = ref(false)
const checkViewSize = () => {
    isMobileView.value = window.innerWidth < 768
}

onMounted(() => {
    checkViewSize()
    window.addEventListener('resize', checkViewSize)
})

onUnmounted(() => {
    window.removeEventListener('resize', checkViewSize)
})

const chartOptions = computed(() => ({
    chart: {
        id: 'revenue-chart',
        toolbar: { show: false },
        zoom: { enabled: false },
        fontFamily: 'inherit',
    },
    colors: ['#0ea5e9'],
    dataLabels: { enabled: false },
    stroke: { 
        curve: 'smooth', 
        width: chartType.value === 'bar' ? 0 : 3 
    },
    fill: {
        type: chartType.value === 'area' ? 'gradient' : 'solid',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.45,
            opacityTo: 0.05,
            stops: [20, 100]
        }
    },
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: '50%',
        }
    },
    xaxis: {
        categories: props.chartData?.labels || [],
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: {
            style: { colors: '#64748b', fontWeight: 600, fontSize: '9px' },
            rotate: isMobileView.value ? -45 : 0,
        }
    },
    yaxis: {
        labels: {
            formatter: (val) => formatPrice(val),
            style: { colors: '#64748b', fontWeight: 600, fontSize: '9px' }
        }
    },
    grid: {
        borderColor: '#f1f5f9',
        strokeDashArray: 4,
    },
    tooltip: {
        theme: 'dark',
        x: { show: true },
        y: { formatter: (val) => formatPrice(val) }
    }
}))

const series = computed(() => [{
    name: ui.value.revenue || 'Revenue',
    data: props.chartData?.series || []
}])
</script>

<template>
    <Head :title="ui.dashboard || 'Dashboard'" />
    
    <div class="max-w-7xl mx-auto space-y-6 lg:space-y-8 pb-20 px-2 lg:px-0">
        <div class="flex items-end justify-between">
            <div>
                <h1 class="text-2xl lg:text-3xl font-black tracking-tight text-primary uppercase">{{ ui.dashboard || 'Dashboard' }}</h1>
                <p class="text-muted-foreground text-[10px] lg:text-sm font-medium">{{ ui.welcome_back || 'Welcome back' }}, {{ $page.props.auth.user?.name }}</p>
            </div>
            <div class="hidden sm:block">
                <span class="text-[10px] font-bold uppercase tracking-widest bg-primary/10 text-primary px-3 py-1 rounded-full border border-primary/20">
                    {{ new Date().toLocaleDateString($page.props.locale === 'id' ? 'id-ID' : 'en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 lg:gap-6">
            <template v-for="stat in dashboardStats" :key="stat.name">
                <Card class="bg-card/50 border-none shadow-sm hover:shadow-md transition-shadow cursor-default group overflow-hidden relative p-5 lg:p-6">
                    <div class="flex items-start justify-between relative z-10">
                        <div class="space-y-1.5 lg:space-y-2">
                            <p class="text-[9px] lg:text-[10px] font-bold uppercase tracking-widest text-muted-foreground">{{ stat.name }}</p>
                            <h3 class="text-xl lg:text-2xl font-black text-primary tracking-tighter">{{ stat.value }}</h3>
                            <div class="flex items-center gap-1 text-[9px] lg:text-[10px] font-bold text-green-600">
                                <ArrowUpRight class="w-3 h-3" />
                                <span>{{ stat.trend }}</span>
                            </div>
                        </div>
                        <div class="p-2.5 lg:p-3 rounded-2xl bg-primary/5 text-primary group-hover:bg-primary group-hover:text-primary-foreground transition-colors duration-300">
                            <component :is="stat.icon" class="w-5 h-5 lg:w-6 lg:h-6" />
                        </div>
                    </div>
                </Card>
            </template>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
            <!-- Chart Panel (66%) -->
            <Card class="lg:col-span-2 bg-card/50 border-none shadow-sm p-4 lg:p-6 overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 lg:gap-4 mb-6 lg:mb-8">
                    <div class="flex items-center gap-3">
                        <div class="p-2 rounded-xl bg-primary text-primary-foreground">
                            <DollarSign class="w-4 h-4" />
                        </div>
                        <div>
                            <h2 class="text-xs lg:text-sm font-black uppercase tracking-widest text-primary">{{ ui.revenue_analysis || 'Revenue Analysis' }}</h2>
                            <p class="text-[9px] lg:text-[10px] font-bold text-muted-foreground uppercase">{{ ui.last_7_days || 'Last 7 Days' }}</p>
                        </div>
                    </div>

                    <!-- Chart Type Switcher -->
                    <div class="flex bg-secondary/50 p-1 rounded-xl border border-secondary/50 self-start sm:self-auto">
                        <button 
                            @click="chartType = 'area'"
                            :class="[
                                'p-2 rounded-lg transition-all',
                                chartType === 'area' ? 'bg-background text-primary shadow-sm' : 'text-muted-foreground hover:text-primary'
                            ]"
                            title="Area Chart"
                        >
                            <AreaChart class="w-3.5 h-3.5 lg:w-4 lg:h-4" />
                        </button>
                        <button 
                            @click="chartType = 'line'"
                            :class="[
                                'p-2 rounded-lg transition-all',
                                chartType === 'line' ? 'bg-background text-primary shadow-sm' : 'text-muted-foreground hover:text-primary'
                            ]"
                            title="Line Chart"
                        >
                            <LineChart class="w-3.5 h-3.5 lg:w-4 lg:h-4" />
                        </button>
                        <button 
                            @click="chartType = 'bar'"
                            :class="[
                                'p-2 rounded-lg transition-all',
                                chartType === 'bar' ? 'bg-background text-primary shadow-sm' : 'text-muted-foreground hover:text-primary'
                            ]"
                            title="Bar Chart"
                        >
                            <BarChart class="w-3.5 h-3.5 lg:w-4 lg:h-4" />
                        </button>
                    </div>
                </div>
                
                <div class="h-64 lg:h-80 -ml-2 lg:-ml-4">
                    <apexchart
                        v-if="series[0].data.length > 0"
                        :key="chartType"
                        :type="chartType"
                        height="100%"
                        :options="chartOptions"
                        :series="series"
                    />
                </div>
            </Card>

            <!-- Activity Panel (33%) -->
            <Card class="bg-card/50 border-none shadow-sm p-4 lg:p-6 flex flex-col">
                <div class="flex items-center gap-3 mb-4 lg:mb-6">
                    <div class="p-2 rounded-xl bg-secondary text-primary">
                        <TrendingUp class="w-4 h-4" />
                    </div>
                    <h2 class="text-xs lg:text-sm font-black uppercase tracking-widest text-primary">{{ ui.quick_overview || 'Quick Overview' }}</h2>
                </div>
                
                <div class="flex-1 space-y-4 lg:space-y-6">
                    <div class="p-4 rounded-2xl bg-background border border-secondary/50">
                        <p class="text-[9px] lg:text-[10px] font-black text-muted-foreground uppercase mb-1">{{ ui.daily_average || 'Daily Average' }}</p>
                        <p class="text-lg lg:text-xl font-black text-primary">{{ formatPrice(income / 30) }}</p>
                    </div>
                    
                    <div class="p-4 rounded-2xl bg-background border border-secondary/50">
                        <p class="text-[9px] lg:text-[10px] font-black text-muted-foreground uppercase mb-1">{{ ui.monthly_target || 'Monthly Target' }}</p>
                        <div class="flex items-end justify-between mb-2">
                            <p class="text-lg lg:text-xl font-black text-primary">65%</p>
                            <p class="text-[9px] lg:text-[10px] font-bold text-muted-foreground uppercase">IDR 50M</p>
                        </div>
                        <div class="w-full h-2 bg-secondary rounded-full overflow-hidden">
                            <div class="w-[65%] h-full bg-primary"></div>
                        </div>
                    </div>
                </div>
                
                <Link :href="route('finance.reports')" class="w-full mt-4 lg:mt-auto py-3 lg:py-4 rounded-2xl bg-secondary text-primary font-black uppercase tracking-widest text-[9px] lg:text-[10px] hover:bg-primary hover:text-primary-foreground transition-all text-center">
                    {{ ui.view_detailed_reports || 'View Detailed Reports' }}
                </Link>
            </Card>
        </div>
    </div>
</template>