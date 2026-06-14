<script setup>
import { ref, watch, computed } from 'vue'
import { Head, router, usePage, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Layers, Search, AlertTriangle, Building2, Package, Tag, Scale, ChevronDown, X } from '@lucide/vue'
import { debounce } from 'lodash-es'

defineOptions({ layout: AppLayout })

const props = defineProps({
    stocks: Object,
    branches: Array,
    filters: Object
})

const page = usePage()
const search = ref(props.filters?.search || '')
const branchFilter = ref(props.filters?.branch || '')
const typeFilter = ref(props.filters?.type || '')
const ui = computed(() => page.props.translations?.ui || {})

const updateFilters = debounce(() => {
    router.get(route('item.stocks.index'), { 
        search: search.value,
        branch: branchFilter.value,
        type: typeFilter.value
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    })
}, 300)

watch([search, branchFilter, typeFilter], updateFilters)

const clearFilters = () => {
    search.value = ''
    branchFilter.value = ''
    typeFilter.value = ''
}

const getStockStatusClass = (qty) => {
    if (qty <= 0) return 'bg-red-500/10 text-red-600 border border-red-500/10'
    if (qty < 10) return 'bg-yellow-500/10 text-yellow-600 border border-yellow-500/10'
    return 'bg-green-500/10 text-green-600 border border-green-500/10'
}

const getStockStatusText = (qty) => {
    if (qty <= 0) return 'Out of Stock'
    if (qty < 10) return 'Low Stock'
    return 'In Stock'
}

const formatQty = (qty) => {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    }).format(qty)
}
</script>

<template>
    <Head :title="ui.inventory_stock || 'Inventory Stock'" />

    <div class="max-w-7xl mx-auto space-y-6 lg:space-y-8">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between gap-4 items-start sm:items-center px-2 lg:px-0">
            <div>
                <h1 class="text-2xl lg:text-3xl font-black uppercase tracking-tight text-primary">{{ ui.inventory_stock || 'Inventory Stock' }}</h1>
                <p class="text-muted-foreground text-[10px] lg:text-sm font-medium uppercase tracking-widest mt-1 opacity-60">{{ ui.manage_inventory_stock || 'Monitor your current inventory levels across branches.' }}</p>
            </div>
        </div>

        <!-- Filters (Styled like Menu Category Filter) -->
        <div class="flex flex-col sm:flex-row gap-4 px-2 lg:px-0">
            <!-- Search -->
            <div class="relative flex-1">
                <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <input 
                    v-model="search"
                    type="text" 
                    :placeholder="ui.search_item || 'Search by item name...'" 
                    class="w-full pl-11 h-12 bg-card border-transparent rounded-2xl text-xs font-bold shadow-sm outline-none focus:ring-4 focus:ring-primary/10 transition-all placeholder:font-medium"
                >
            </div>

            <!-- Type Filter -->
            <div class="relative w-full sm:w-48">
                <Layers class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <select 
                    v-model="typeFilter"
                    class="w-full pl-11 h-12 bg-card border-transparent rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-sm outline-none focus:ring-4 focus:ring-primary/10 transition-all appearance-none cursor-pointer"
                >
                    <option value="">{{ ui.all_types || 'All Types' }}</option>
                    <option value="Items">Items</option>
                    <option value="Products">Products</option>
                    <option value="Ingredients">Ingredients</option>
                    <option value="Merchandise">Merchandise</option>
                </select>
                <ChevronDown class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
            </div>

            <!-- Branch Filter (Superadmin only) -->
            <div v-if="branches && branches.length > 0" class="relative w-full sm:w-56">
                <Building2 class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <select 
                    v-model="branchFilter"
                    class="w-full pl-11 h-12 bg-card border-transparent rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-sm outline-none focus:ring-4 focus:ring-primary/10 transition-all appearance-none cursor-pointer"
                >
                    <option value="">{{ ui.all_branches || 'All Branches' }}</option>
                    <option v-for="branch in branches" :key="branch.branchid" :value="branch.branchid">
                        {{ branch.branchname }}
                    </option>
                </select>
                <ChevronDown class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
            </div>

            <!-- Reset Button -->
            <button 
                v-if="search || branchFilter || typeFilter" 
                @click="clearFilters"
                class="h-12 px-6 bg-destructive/10 text-destructive rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-destructive hover:text-white transition-all flex items-center justify-center gap-2 active:scale-95"
            >
                <X class="w-4 h-4 stroke-[3]" />
                {{ ui.reset || 'Reset' }}
            </button>
        </div>

        <!-- Desktop View -->
        <div class="hidden md:block bg-card rounded-3xl shadow-sm border border-secondary overflow-hidden mx-2 lg:mx-0">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-secondary/20 border-b border-secondary">
                            <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/60">{{ ui.item_name || 'Item Name' }}</th>
                            <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/60">{{ ui.branch || 'Branch' }}</th>
                            <th class="px-8 py-5 text-center text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/60">{{ ui.stock_level || 'Stock Level' }}</th>
                            <th class="px-8 py-5 text-right text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/60">{{ ui.status || 'Status' }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/50">
                        <tr v-for="stock in stocks.data" :key="stock.itemid + '-' + stock.branchid" class="hover:bg-primary/5 transition-colors group">
                            <td class="px-8 py-5">
                                <div class="flex flex-col">
                                    <span class="font-black text-primary uppercase tracking-tight">{{ stock.item?.name || 'Deleted Item' }}</span>
                                    <span class="text-[9px] font-black bg-primary/10 text-primary px-2 py-0.5 rounded-md uppercase tracking-widest border border-primary/20 w-fit mt-1">{{ stock.item?.type || '-' }}</span>
                                </div>
                            </td>
                            <td class="px-8 py-5">
                                <span class="text-muted-foreground font-bold text-xs uppercase tracking-wider">{{ stock.branch?.branchname || '-' }}</span>
                            </td>
                            <td class="px-8 py-5 text-center">
                                <span class="text-primary font-black text-lg font-mono">{{ formatQty(stock.total_qty) }}</span>
                                <span class="text-[10px] font-bold text-muted-foreground ml-1 uppercase">{{ stock.item?.unit }}</span>
                            </td>
                            <td class="px-8 py-5 text-right">
                                <span :class="['inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider', getStockStatusClass(stock.total_qty)]">
                                    {{ getStockStatusText(stock.total_qty) }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="stocks.data.length === 0">
                            <td colspan="4" class="px-8 py-20 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center gap-4">
                                    <div class="w-16 h-16 rounded-full bg-secondary/30 flex items-center justify-center">
                                        <Layers class="w-8 h-8 opacity-20" />
                                    </div>
                                    <span class="font-black uppercase tracking-widest text-xs opacity-40">{{ ui.no_stocks_found || 'No inventory stock records found.' }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile View -->
        <div class="md:hidden space-y-4 px-2">
            <div v-for="stock in stocks.data" :key="stock.itemid + '-' + stock.branchid" class="bg-card rounded-3xl shadow-sm border border-secondary p-5 flex flex-col gap-4">
                <div class="flex justify-between items-start">
                    <div class="flex flex-col gap-1">
                        <h3 class="font-black text-primary uppercase tracking-tight">{{ stock.item?.name || 'Deleted Item' }}</h3>
                        <div class="flex items-center gap-2 text-muted-foreground">
                            <Building2 class="w-3 h-3" />
                            <span class="text-[10px] font-bold uppercase tracking-wider">{{ stock.branch?.branchname || '-' }}</span>
                        </div>
                        <span class="text-[8px] font-black bg-primary/10 text-primary px-2 py-0.5 rounded-md uppercase tracking-widest border border-primary/20 w-fit">{{ stock.item?.type || '-' }}</span>
                    </div>
                    <span :class="['inline-flex items-center px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-[0.2em]', getStockStatusClass(stock.total_qty)]">
                        {{ getStockStatusText(stock.total_qty) }}
                    </span>
                </div>
                
                <div class="flex items-center justify-between pt-4 border-t border-secondary/50">
                    <span class="text-[10px] font-black uppercase tracking-widest text-muted-foreground/60">Stock Available</span>
                    <div class="flex items-baseline gap-1">
                        <span class="text-lg font-black text-primary">{{ formatQty(stock.total_qty) }}</span>
                        <span class="text-[10px] font-bold text-muted-foreground uppercase">{{ stock.item?.unit }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="stocks.links && stocks.links.length > 3" class="flex flex-wrap justify-center gap-2 mt-8">
            <template v-for="(link, key) in stocks.links" :key="key">
                <div v-if="link.url === null" class="px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-muted-foreground/30 bg-card border border-secondary rounded-xl" v-html="link.label" />
                <Link v-else :href="link.url" :class="[
                    'px-4 py-2.5 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all border',
                    link.active 
                        ? 'bg-primary text-primary-foreground border-primary shadow-lg shadow-primary/20' 
                        : 'bg-card border-secondary text-muted-foreground hover:bg-secondary hover:text-primary'
                ]" v-html="link.label" />
            </template>
        </div>
    </div>
</template>
