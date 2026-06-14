<script setup>
import { ref, watch, computed } from 'vue'
import { Head, router, usePage, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Plus, Search, ArrowDownCircle, User, Building2, Calendar, Hash, Tag, X, Scale, Banknote, Package, ChevronDown, Layers } from '@lucide/vue'
import { debounce } from 'lodash-es'

defineOptions({ layout: AppLayout })

const props = defineProps({
    logs: Object,
    items: Array,
    branches: Array,
    availableBranches: Array,
    filters: Object
})

const page = usePage()
const search = ref(props.filters?.search || '')
const branchFilter = ref(props.filters?.branch || '')
const typeFilter = ref(props.filters?.type || '')
const ui = computed(() => page.props.translations?.ui || {})

const updateFilters = debounce(() => {
    router.get(route('item.stock-in.index'), { 
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

const isModalOpen = ref(false)
const isSubmitting = ref(false)
const errors = ref({})

const form = ref({
    type: 'Items', // Pick type first
    itemid: '',
    branchid: '',
    qty: 1,
    unit: 'Pcs',
    price: 0
})

const filteredItemsForModal = computed(() => {
    return props.items.filter(item => item.type === form.value.type)
})

const availableUnits = computed(() => {
    if (!form.value.itemid) return ['Pcs']
    const selectedItem = props.items.find(i => i.itemid === form.value.itemid)
    if (!selectedItem) return ['Pcs']
    const baseUnit = selectedItem.unit?.toLowerCase()
    if (baseUnit === 'kg') return ['Kg', 'gr']
    if (baseUnit === 'l') return ['L', 'ml']
    if (baseUnit === 'pcs') return ['Pcs', 'Pack']
    return [selectedItem.unit || 'Pcs']
})

watch(() => form.value.type, (newVal) => {
    // Reset item selection when type changes
    form.value.itemid = filteredItemsForModal.value[0]?.itemid || ''
})

watch(() => form.value.itemid, (newVal) => {
    if (newVal) {
        form.value.unit = availableUnits.value[0]
    }
})

const openModal = () => {
    errors.value = {}
    form.value = {
        type: 'Items',
        itemid: '',
        branchid: props.branches[0]?.branchid || '',
        qty: 1,
        unit: 'Pcs',
        price: 0
    }
    // Set initial item after nextTick or just directly
    form.value.itemid = filteredItemsForModal.value[0]?.itemid || ''
    isModalOpen.value = true
}

const closeModal = () => {
    isModalOpen.value = false
    errors.value = {}
}

const submit = () => {
    isSubmitting.value = true
    errors.value = {}

    router.post(route('item.stock-in.store'), form.value, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: (err) => {
            errors.value = err
        },
        onFinish: () => {
            isSubmitting.value = false
        }
    })
}

const formatDate = (dateString) => {
    if (!dateString) return '-'
    const date = new Date(dateString)
    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    }).format(date)
}

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(value)
}

const formatQty = (qty) => {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    }).format(qty)
}
</script>

<template>
    <Head :title="ui.stock_in || 'Stock In'" />

    <div class="max-w-7xl mx-auto space-y-6 lg:space-y-8">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between gap-4 items-start sm:items-center px-2 lg:px-0">
            <div>
                <h1 class="text-2xl lg:text-3xl font-black uppercase tracking-tight text-primary">{{ ui.stock_in || 'Stock In' }}</h1>
                <p class="text-muted-foreground text-[10px] lg:text-sm font-medium uppercase tracking-widest mt-1 opacity-60">{{ ui.manage_stock_in || 'View and manage incoming stock.' }}</p>
            </div>
            <button @click="openModal" class="px-6 py-3 bg-primary text-primary-foreground font-black uppercase tracking-widest text-xs rounded-2xl hover:bg-primary/90 transition-all flex items-center gap-2 shadow-lg shadow-primary/20 active:scale-95 h-12">
                <Plus class="w-4 h-4 stroke-[3]" />
                {{ ui.input_stock || 'Input Stock' }}
            </button>
        </div>

        <!-- Filters -->
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
            <div v-if="availableBranches && availableBranches.length > 0" class="relative w-full sm:w-56">
                <Building2 class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <select 
                    v-model="branchFilter"
                    class="w-full pl-11 h-12 bg-card border-transparent rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-sm outline-none focus:ring-4 focus:ring-primary/10 transition-all appearance-none cursor-pointer"
                >
                    <option value="">{{ ui.all_branches || 'All Branches' }}</option>
                    <option v-for="branch in availableBranches" :key="branch.branchid" :value="branch.branchid">
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
                            <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/60">{{ ui.date || 'Date' }}</th>
                            <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/60">{{ ui.item_name || 'Item' }}</th>
                            <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/60">{{ ui.type || 'Type' }}</th>
                            <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/60">{{ ui.branch || 'Branch' }}</th>
                            <th class="px-8 py-5 text-center text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/60">{{ ui.qty || 'Qty' }}</th>
                            <th class="px-8 py-5 text-right text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/60">{{ ui.price || 'Price' }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/50">
                        <tr v-for="log in logs.data" :key="log.inventory_log_id" class="hover:bg-primary/5 transition-colors group">
                            <td class="px-8 py-5">
                                <div class="flex flex-col">
                                    <span class="font-black text-primary text-xs uppercase tracking-tight">{{ formatDate(log.created_at).split(',')[0] }}</span>
                                    <span class="text-[10px] text-muted-foreground font-bold">{{ formatDate(log.created_at).split(',')[1] }}</span>
                                </div>
                            </td>
                            <td class="px-8 py-5">
                                <span class="font-black text-primary uppercase tracking-tight">{{ log.item?.name || 'Deleted Item' }}</span>
                            </td>
                            <td class="px-8 py-5">
                                <span class="text-[9px] font-black bg-primary/10 text-primary px-2 py-0.5 rounded-md uppercase tracking-widest border border-primary/20">{{ log.item?.type || '-' }}</span>
                            </td>
                            <td class="px-8 py-5">
                                <span class="text-muted-foreground font-bold text-xs uppercase tracking-wider">{{ log.branch?.branchname || '-' }}</span>
                            </td>
                            <td class="px-8 py-5 text-center">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-green-500/10 text-green-600 border border-green-500/10">
                                    +{{ formatQty(log.qty) }} {{ log.unit }}
                                </span>
                            </td>
                            <td class="px-8 py-5 text-right">
                                <span class="text-primary font-black text-sm">{{ formatCurrency(log.price) }}</span>
                            </td>
                        </tr>
                        <tr v-if="logs.data.length === 0">
                            <td colspan="6" class="px-8 py-20 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center gap-4">
                                    <div class="w-16 h-16 rounded-full bg-secondary/30 flex items-center justify-center">
                                        <ArrowDownCircle class="w-8 h-8 opacity-20" />
                                    </div>
                                    <span class="font-black uppercase tracking-widest text-xs opacity-40">{{ ui.no_logs_found || 'No records found.' }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile View -->
        <div class="md:hidden space-y-4 px-2">
            <div v-for="log in logs.data" :key="log.inventory_log_id" class="bg-card rounded-3xl shadow-sm border border-secondary p-5 flex flex-col gap-4">
                <div class="flex justify-between items-start">
                    <div class="flex flex-col gap-1">
                        <h3 class="font-black text-primary uppercase tracking-tight">{{ log.item?.name || 'Deleted Item' }}</h3>
                        <div class="flex items-center gap-2 text-muted-foreground">
                            <Calendar class="w-3 h-3" />
                            <span class="text-[10px] font-bold uppercase tracking-wider">{{ formatDate(log.created_at) }}</span>
                        </div>
                    </div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-[0.2em] bg-green-500/10 text-green-600 border border-green-500/10">
                        +{{ formatQty(log.qty) }} {{ log.unit }}
                    </span>
                </div>
                
                <div class="grid grid-cols-2 gap-4 pt-4 border-t border-secondary/50">
                    <div class="flex flex-col gap-1">
                        <span class="text-[8px] font-black uppercase tracking-widest text-muted-foreground/60">Branch</span>
                        <span class="text-xs font-bold text-primary uppercase">{{ log.branch?.branchname || '-' }}</span>
                    </div>
                    <div class="flex flex-col gap-1 text-right">
                        <span class="text-[8px] font-black uppercase tracking-widest text-muted-foreground/60">Price</span>
                        <span class="text-xs font-black text-primary">{{ formatCurrency(log.price) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="logs.links && logs.links.length > 3" class="flex flex-wrap justify-center gap-2 mt-8">
            <template v-for="(link, key) in logs.links" :key="key">
                <div v-if="link.url === null" class="px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-muted-foreground/30 bg-card border border-secondary rounded-xl" v-html="link.label" />
                <Link v-else :href="link.url" :class="[
                    'px-4 py-2.5 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all border',
                    link.active 
                        ? 'bg-primary text-primary-foreground border-primary shadow-lg shadow-primary/20' 
                        : 'bg-card border-secondary text-muted-foreground hover:bg-secondary hover:text-primary'
                ]" v-html="link.label" />
            </template>
        </div>

        <!-- Input Stock Modal -->
        <transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div v-if="isModalOpen" class="fixed inset-0 bg-transparent backdrop-blur-md z-50 flex items-center justify-center p-4">
                <div class="bg-card border border-secondary rounded-[2.5rem] shadow-2xl w-full max-w-lg overflow-hidden" @click.stop>
                    <div class="px-8 py-6 border-b border-secondary flex items-center justify-between bg-secondary/10">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                                <Plus class="w-5 h-5 stroke-[3]" />
                            </div>
                            <h2 class="text-lg font-black uppercase tracking-tight text-primary">Input New Stock</h2>
                        </div>
                        <button @click="closeModal" class="p-3 text-muted-foreground hover:bg-secondary hover:text-primary rounded-2xl transition-all">
                            <X class="w-5 h-5 stroke-[3]" />
                        </button>
                    </div>
                    
                    <form @submit.prevent="submit" class="p-8 space-y-6">
                        <!-- Select Type First -->
                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center gap-2">
                                <Layers class="w-3.5 h-3.5" />
                                Select Item Type
                            </label>
                            <div class="relative">
                                <select 
                                    v-model="form.type" 
                                    class="w-full px-6 py-4 bg-secondary/30 border-secondary rounded-[1.25rem] text-sm focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all font-black uppercase tracking-widest cursor-pointer appearance-none shadow-sm pr-12"
                                    required
                                >
                                    <option value="Items">Items</option>
                                    <option value="Products">Products</option>
                                    <option value="Ingredients">Ingredients</option>
                                    <option value="Merchandise">Merchandise</option>
                                </select>
                                <ChevronDown class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center gap-2">
                                <Package class="w-3.5 h-3.5" />
                                Select Item
                            </label>
                            <div class="relative">
                                <select 
                                    v-model="form.itemid" 
                                    class="w-full px-6 py-4 bg-secondary/30 border-secondary rounded-[1.25rem] text-sm focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all font-black uppercase tracking-widest cursor-pointer appearance-none shadow-sm pr-12"
                                    required
                                    :disabled="filteredItemsForModal.length === 0"
                                >
                                    <option v-if="filteredItemsForModal.length === 0" value="">No items found for this type</option>
                                    <option v-for="item in filteredItemsForModal" :key="item.itemid" :value="item.itemid">
                                        {{ item.name }} ({{ item.unit }})
                                    </option>
                                </select>
                                <ChevronDown class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
                            </div>
                            <span v-if="errors.itemid" class="text-[10px] text-destructive font-black uppercase tracking-wider">{{ errors.itemid }}</span>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center gap-2">
                                <Building2 class="w-3.5 h-3.5" />
                                Destination Branch
                            </label>
                            <div class="relative">
                                <select 
                                    v-model="form.branchid" 
                                    class="w-full px-6 py-4 bg-secondary/30 border-secondary rounded-[1.25rem] text-sm focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all font-black uppercase tracking-widest cursor-pointer appearance-none shadow-sm pr-12"
                                    required
                                >
                                    <option v-for="branch in branches" :key="branch.branchid" :value="branch.branchid">
                                        {{ branch.branchname }}
                                    </option>
                                </select>
                                <ChevronDown class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
                            </div>
                            <span v-if="errors.branchid" class="text-[10px] text-destructive font-black uppercase tracking-wider">{{ errors.branchid }}</span>
                        </div>

                        <div class="grid grid-cols-2 gap-6 pt-2">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center gap-2">
                                    <Hash class="w-3.5 h-3.5" />
                                    Quantity
                                </label>
                                <input 
                                    v-model="form.qty" 
                                    type="number" 
                                    step="0.01"
                                    min="0.01"
                                    required
                                    class="w-full px-6 py-4 bg-secondary/30 border-secondary rounded-[1.25rem] text-sm focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all font-black"
                                >
                                <span v-if="errors.qty" class="text-[10px] text-destructive font-black uppercase tracking-wider">{{ errors.qty }}</span>
                            </div>

                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center gap-2">
                                    <Scale class="w-3.5 h-3.5" />
                                    Unit
                                </label>
                                <div class="relative">
                                    <select 
                                        v-model="form.unit" 
                                        class="w-full px-6 py-4 bg-secondary/30 border-secondary rounded-[1.25rem] text-sm focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all font-black uppercase tracking-widest cursor-pointer appearance-none shadow-sm pr-12"
                                        required
                                    >
                                        <option v-for="u in availableUnits" :key="u" :value="u">
                                            {{ u }}
                                        </option>
                                    </select>
                                    <ChevronDown class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
                                </div>
                                <span v-if="errors.unit" class="text-[10px] text-destructive font-black uppercase tracking-wider">{{ errors.unit }}</span>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center gap-2">
                                <Banknote class="w-3.5 h-3.5" />
                                Purchase Price (Total)
                            </label>
                            <input 
                                v-model="form.price" 
                                type="number" 
                                required
                                min="0"
                                class="w-full px-6 py-4 bg-secondary/30 border-secondary rounded-[1.25rem] text-sm focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all font-black"
                            >
                            <span v-if="errors.price" class="text-[10px] text-destructive font-black uppercase tracking-wider">{{ errors.price }}</span>
                        </div>

                        <div class="pt-6 flex justify-end gap-4">
                            <button type="button" @click="closeModal" class="px-6 py-3 text-[10px] font-black uppercase tracking-widest text-muted-foreground hover:bg-secondary rounded-2xl transition-all active:scale-95">
                                Cancel
                            </button>
                            <button type="submit" :disabled="isSubmitting || filteredItemsForModal.length === 0" class="px-8 py-3 text-[10px] font-black uppercase tracking-widest bg-primary text-primary-foreground rounded-2xl hover:bg-primary/90 transition-all disabled:opacity-50 shadow-xl shadow-primary/20 active:scale-95">
                                {{ isSubmitting ? 'Recording...' : 'Submit Stock' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>
    </div>
</template>
