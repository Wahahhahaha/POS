<script setup>
import { ref, watch, computed } from 'vue'
import { Head, router, usePage, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmationModal from '@/Components/ui/ConfirmationModal.vue'
import { ClipboardList, Search, Plus, X, Tag, Package, Building2, Hash, Scale, Camera, ChevronDown, Trash2, Check, Scissors, Layers } from '@lucide/vue'
import { debounce } from 'lodash-es'
import { Cropper } from 'vue-advanced-cropper'
import 'vue-advanced-cropper/dist/style.css'

defineOptions({ layout: AppLayout })

const props = defineProps({
    opnames: Object,
    items: Array,
    branches: Array,
    availableBranches: Array,
    itemStocks: Array,
    filters: Object
})

const page = usePage()
const user = computed(() => page.props.auth.user)
const isAdmin = computed(() => user.value && [1, 2].includes(parseInt(user.value.roleid)))

const search = ref(props.filters?.search || '')
const branchFilter = ref(props.filters?.branch || '')
const typeFilter = ref(props.filters?.type || '')
const ui = computed(() => page.props.translations?.ui || {})

const updateFilters = debounce(() => {
    router.get(route('item.opname.index'), { 
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

// Confirmation Modal State
const showConfirmModal = ref(false)
const confirmData = ref({
    title: '',
    message: '',
    type: 'info',
    confirmText: '',
    action: null
})

// Cropper State
const isCropModalOpen = ref(false)
const rawImage = ref(null)
const cropperRef = ref(null)
const evidencePreview = ref(null)

const form = ref({
    type: 'Items',
    itemid: '',
    branchid: '',
    qty: 1,
    unit: 'Pcs',
    reason: '',
    evidence: null
})

// Dynamic Item Logic
const filteredItems = computed(() => {
    if (!form.value.branchid || !form.value.type) return []
    const availableItemIds = props.itemStocks
        .filter(s => parseInt(s.branchid) === parseInt(form.value.branchid) && parseFloat(s.total_qty) > 0)
        .map(s => s.itemid)
    return props.items.filter(item => item.type === form.value.type && availableItemIds.includes(item.itemid))
})

// Dynamic Unit Logic
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

watch(() => form.value.type, () => {
    form.value.itemid = filteredItems.value[0]?.itemid || ''
})

watch(() => form.value.branchid, (newVal) => {
    if (newVal) {
        const isStillAvailable = filteredItems.value.some(i => i.itemid === form.value.itemid)
        if (!isStillAvailable) {
            form.value.itemid = filteredItems.value[0]?.itemid || ''
        }
    }
})

watch(() => form.value.itemid, (newVal) => {
    if (newVal) {
        form.value.unit = availableUnits.value[0]
    }
})

const openModal = () => {
    errors.value = {}
    evidencePreview.value = null
    form.value = {
        type: 'Items',
        branchid: props.branches[0]?.branchid || '',
        itemid: '',
        qty: 1,
        unit: 'Pcs',
        reason: '',
        evidence: null
    }
    setTimeout(() => {
        form.value.itemid = filteredItems.value[0]?.itemid || ''
    }, 0)
    isModalOpen.value = true
}

const closeModal = () => {
    isModalOpen.value = false
    errors.value = {}
}

const handleFileSelect = (e) => {
    const file = e.target.files[0]
    if (file) {
        const reader = new FileReader()
        reader.onload = (event) => {
            rawImage.value = event.target.result
            isCropModalOpen.value = true
        }
        reader.readAsDataURL(file)
        e.target.value = ''
    }
}

const applyCrop = () => {
    const { canvas } = cropperRef.value.getResult()
    if (canvas) {
        canvas.toBlob((blob) => {
            const file = new File([blob], 'evidence.jpg', { type: 'image/jpeg' })
            form.value.evidence = file
            evidencePreview.value = URL.createObjectURL(blob)
            isCropModalOpen.value = false
        }, 'image/jpeg')
    }
}

const submit = () => {
    isSubmitting.value = true
    errors.value = {}

    router.post(route('item.opname.store'), form.value, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: (err) => { errors.value = err },
        onFinish: () => { isSubmitting.value = false }
    })
}

const promptApprove = (id) => {
    confirmData.value = {
        title: 'Approve Damage Report',
        message: 'Are you sure you want to approve this damage report? This will officially deduct the stock from the warehouse.',
        type: 'info',
        confirmText: 'Approve Now',
        action: () => {
            router.post(route('item.opname.approve', id), {}, { 
                preserveScroll: true,
                onSuccess: () => { showConfirmModal.value = false }
            })
        }
    }
    showConfirmModal.value = true
}

const promptReject = (id) => {
    confirmData.value = {
        title: 'Reject Damage Report',
        message: 'Are you sure you want to reject this damage report? The pending stock deduction will be cancelled and the record will be marked as rejected.',
        type: 'danger',
        confirmText: 'Reject Report',
        action: () => {
            router.post(route('item.opname.reject', id), {}, { 
                preserveScroll: true,
                onSuccess: () => { showConfirmModal.value = false }
            })
        }
    }
    showConfirmModal.value = true
}

const formatDate = (dateString) => {
    if (!dateString) return '-'
    const date = new Date(dateString)
    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    }).format(date)
}

const formatQty = (qty) => {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    }).format(qty)
}

const getStatusBadgeClass = (status) => {
    switch (parseInt(status)) {
        case 1: return 'bg-green-500/10 text-green-600 border border-green-500/10'
        case 2: return 'bg-red-500/10 text-red-600 border border-red-500/10'
        default: return 'bg-yellow-500/10 text-yellow-600 border border-yellow-500/10 animate-pulse'
    }
}

const getStatusText = (status) => {
    switch (parseInt(status)) {
        case 1: return 'Approved'
        case 2: return 'Rejected'
        default: return 'Pending'
    }
}
</script>

<template>
    <Head :title="ui.inventory_damage || 'Inventory Damage'" />

    <div class="max-w-7xl mx-auto space-y-6 lg:space-y-8">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between gap-4 items-start sm:items-center px-2 lg:px-0">
            <div>
                <h1 class="text-2xl lg:text-3xl font-black uppercase tracking-tight text-primary">{{ ui.inventory_damage || 'Inventory Damage' }}</h1>
                <p class="text-muted-foreground text-[10px] lg:text-sm font-medium uppercase tracking-widest mt-1 opacity-60">{{ ui.manage_inventory_damage || 'Manage inventory damage records.' }}</p>
            </div>
            <button @click="openModal" class="px-6 py-3 bg-primary text-primary-foreground font-black uppercase tracking-widest text-xs rounded-2xl hover:bg-primary/90 transition-all flex items-center gap-2 shadow-lg shadow-primary/20 active:scale-95 h-12">
                <Trash2 class="w-4 h-4 stroke-[3]" />
                {{ ui.report_damage || 'Report Damage' }}
            </button>
        </div>

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-4 px-2 lg:px-0">
            <div class="relative flex-1">
                <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <input v-model="search" type="text" :placeholder="ui.search_reason || 'Search by reason or item...'" class="w-full pl-11 h-12 bg-card border-transparent rounded-2xl text-xs font-bold shadow-sm outline-none focus:ring-4 focus:ring-primary/10 transition-all placeholder:font-medium">
            </div>
            <div class="relative w-full sm:w-48">
                <Layers class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <select v-model="typeFilter" class="w-full pl-11 h-12 bg-card border-transparent rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-sm outline-none focus:ring-4 focus:ring-primary/10 transition-all appearance-none cursor-pointer">
                    <option value="">{{ ui.all_types || 'All Types' }}</option>
                    <option value="Items">Items</option>
                    <option value="Products">Products</option>
                    <option value="Ingredients">Ingredients</option>
                    <option value="Merchandise">Merchandise</option>
                </select>
                <ChevronDown class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
            </div>
            <div v-if="availableBranches && availableBranches.length > 0" class="relative w-full sm:w-56">
                <Building2 class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <select v-model="branchFilter" class="w-full pl-11 h-12 bg-card border-transparent rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-sm outline-none focus:ring-4 focus:ring-primary/10 transition-all appearance-none cursor-pointer">
                    <option value="">{{ ui.all_branches || 'All Branches' }}</option>
                    <option v-for="branch in availableBranches" :key="branch.branchid" :value="branch.branchid">{{ branch.branchname }}</option>
                </select>
                <ChevronDown class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
            </div>
            <button v-if="search || branchFilter || typeFilter" @click="clearFilters" class="h-12 px-6 bg-destructive/10 text-destructive rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-destructive hover:text-white transition-all flex items-center justify-center gap-2 active:scale-95">
                <X class="w-4 h-4 stroke-[3]" /> {{ ui.reset || 'Reset' }}
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
                            <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/60">{{ ui.branch || 'Branch' }}</th>
                            <th class="px-8 py-5 text-center text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/60">{{ ui.qty || 'Qty' }}</th>
                            <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/60">{{ ui.reason || 'Reason' }}</th>
                            <th class="px-8 py-5 text-center text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/60">{{ ui.status || 'Status' }}</th>
                            <th v-if="isAdmin" class="px-8 py-5 text-right text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/60">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/50">
                        <tr v-for="opname in opnames.data" :key="opname.opnameid" class="hover:bg-primary/5 transition-colors group">
                            <td class="px-8 py-5">
                                <span class="font-black text-primary text-xs uppercase tracking-tight">{{ formatDate(opname.created_at) }}</span>
                            </td>
                            <td class="px-8 py-5">
                                <div class="flex flex-col">
                                    <span class="font-black text-primary uppercase tracking-tight">{{ opname.log?.item?.name || 'Deleted Item' }}</span>
                                    <span class="text-[8px] font-black text-primary/40 uppercase tracking-widest mt-0.5">{{ opname.log?.item?.type || '-' }}</span>
                                </div>
                            </td>
                            <td class="px-8 py-5">
                                <span class="text-muted-foreground font-bold text-xs uppercase tracking-wider">{{ opname.log?.branch?.branchname || '-' }}</span>
                            </td>
                            <td class="px-8 py-5 text-center">
                                <span class="text-primary font-black text-sm font-mono">{{ formatQty(opname.qty) }}</span>
                                <span class="text-[10px] font-bold text-muted-foreground ml-1 uppercase">{{ opname.unit }}</span>
                            </td>
                            <td class="px-8 py-5">
                                <div class="flex items-center gap-3">
                                    <div v-if="opname.evidence" class="w-8 h-8 rounded-lg overflow-hidden bg-secondary border border-secondary shadow-sm shrink-0">
                                        <a :href="opname.evidence.startsWith('http') ? opname.evidence : '/storage/' + opname.evidence" target="_blank">
                                            <img :src="opname.evidence.startsWith('http') ? opname.evidence : '/storage/' + opname.evidence" class="w-full h-full object-cover" />
                                        </a>
                                    </div>
                                    <span class="text-xs font-bold text-muted-foreground/80 block max-w-[200px] truncate" :title="opname.reason">{{ opname.reason }}</span>
                                </div>
                            </td>
                            <td class="px-8 py-5 text-center">
                                <span :class="['inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider', getStatusBadgeClass(opname.status)]">
                                    {{ getStatusText(opname.status) }}
                                </span>
                            </td>
                            <td v-if="isAdmin" class="px-8 py-5 text-right">
                                <div v-if="parseInt(opname.status) === 0" class="flex items-center justify-end gap-2">
                                    <button @click="promptApprove(opname.opnameid)" class="p-2 bg-green-500 text-white rounded-xl hover:bg-green-600 transition-all shadow-lg shadow-green-500/20 active:scale-90" title="Approve">
                                        <Check class="w-4 h-4 stroke-[4]" />
                                    </button>
                                    <button @click="promptReject(opname.opnameid)" class="p-2 bg-destructive text-white rounded-xl hover:bg-destructive/90 transition-all shadow-lg shadow-destructive/20 active:scale-90" title="Reject">
                                        <X class="w-4 h-4 stroke-[4]" />
                                    </button>
                                </div>
                                <span v-else class="text-[10px] font-black text-muted-foreground/30 uppercase tracking-widest">Locked</span>
                            </td>
                        </tr>
                        <tr v-if="opnames.data.length === 0">
                            <td colspan="7" class="px-8 py-20 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center gap-4">
                                    <div class="w-16 h-16 rounded-full bg-secondary/30 flex items-center justify-center"><ClipboardList class="w-8 h-8 opacity-20" /></div>
                                    <span class="font-black uppercase tracking-widest text-xs opacity-40">{{ ui.no_records_found || 'No records found.' }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile View -->
        <div class="md:hidden space-y-4 px-2">
            <div v-for="opname in opnames.data" :key="opname.opnameid" class="bg-card rounded-3xl shadow-sm border border-secondary p-5 flex flex-col gap-4">
                <div class="flex justify-between items-start">
                    <div class="flex flex-col gap-1">
                        <span class="text-xs font-black text-primary uppercase tracking-tight">{{ opname.log?.item?.name || 'Deleted Item' }}</span>
                        <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest">{{ formatDate(opname.created_at) }}</span>
                    </div>
                    <span :class="['inline-flex items-center px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-[0.2em]', getStatusBadgeClass(opname.status)]">{{ getStatusText(opname.status) }}</span>
                </div>
                <div class="flex justify-between items-center pt-4 border-t border-secondary/50">
                    <div class="flex items-baseline gap-1">
                        <span class="text-lg font-black text-primary">{{ formatQty(opname.qty) }}</span>
                        <span class="text-[10px] font-bold text-muted-foreground uppercase">{{ opname.unit }}</span>
                    </div>
                    <div v-if="opname.evidence" class="w-10 h-10 rounded-xl overflow-hidden bg-secondary border border-secondary shadow-sm">
                        <a :href="opname.evidence.startsWith('http') ? opname.evidence : '/storage/' + opname.evidence" target="_blank">
                            <img :src="opname.evidence.startsWith('http') ? opname.evidence : '/storage/' + opname.evidence" class="w-full h-full object-cover" />
                        </a>
                    </div>
                </div>
                <div class="bg-secondary/20 p-3 rounded-2xl"><p class="text-[10px] font-bold text-muted-foreground leading-relaxed uppercase tracking-wider italic opacity-60">"{{ opname.reason }}"</p></div>
                <!-- Mobile Actions -->
                <div v-if="isAdmin && parseInt(opname.status) === 0" class="flex gap-2 pt-2">
                    <button @click="promptApprove(opname.opnameid)" class="flex-1 py-3 bg-green-500 text-white rounded-xl font-black uppercase text-[10px] tracking-widest flex items-center justify-center gap-2 shadow-lg shadow-green-500/20 active:scale-95"><Check class="w-3.5 h-3.5 stroke-[4]" /> Approve</button>
                    <button @click="promptReject(opname.opnameid)" class="flex-1 py-3 bg-destructive text-white rounded-xl font-black uppercase text-[10px] tracking-widest flex items-center justify-center gap-2 shadow-lg shadow-destructive/20 active:scale-95"><X class="w-3.5 h-3.5 stroke-[4]" /> Reject</button>
                </div>
            </div>
        </div>

        <!-- Report Damage Modal -->
        <transition enter-active-class="transition-all duration-300 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition-all duration-200 ease-in" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
            <div v-if="isModalOpen" class="fixed inset-0 bg-transparent backdrop-blur-md z-50 flex items-start justify-center p-4 overflow-y-auto">
                <div class="bg-card border border-secondary rounded-[2.5rem] shadow-2xl w-full max-w-lg my-8" @click.stop>
                    <div class="px-8 py-6 border-b border-secondary flex items-center justify-between bg-secondary/10 sticky top-0 z-10 backdrop-blur-md rounded-t-[2.5rem]">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-destructive/10 flex items-center justify-center text-destructive"><Trash2 class="w-5 h-5 stroke-[3]" /></div>
                            <h2 class="text-lg font-black uppercase tracking-tight text-primary">Report Damage</h2>
                        </div>
                        <button @click="closeModal" class="p-3 text-muted-foreground hover:bg-secondary hover:text-primary rounded-2xl transition-all"><X class="w-5 h-5 stroke-[3]" /></button>
                    </div>
                    <form @submit.prevent="submit" class="p-8 space-y-6">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center gap-2"><Building2 class="w-3.5 h-3.5" /> Branch</label>
                            <div class="relative">
                                <select v-model="form.branchid" class="w-full px-6 py-4 bg-secondary/30 border-secondary rounded-[1.25rem] text-sm focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all font-black uppercase tracking-widest cursor-pointer appearance-none shadow-sm pr-12" required>
                                    <option v-for="branch in branches" :key="branch.branchid" :value="branch.branchid">{{ branch.branchname }}</option>
                                </select>
                                <ChevronDown class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
                            </div>
                            <span v-if="errors.branchid" class="text-[10px] text-destructive font-black uppercase tracking-wider">{{ errors.branchid }}</span>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center gap-2"><Layers class="w-3.5 h-3.5" /> Select Item Type</label>
                            <div class="relative">
                                <select v-model="form.type" class="w-full px-6 py-4 bg-secondary/30 border-secondary rounded-[1.25rem] text-sm focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all font-black uppercase tracking-widest cursor-pointer appearance-none shadow-sm pr-12" required>
                                    <option value="Items">Items</option><option value="Products">Products</option><option value="Ingredients">Ingredients</option><option value="Merchandise">Merchandise</option>
                                </select>
                                <ChevronDown class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center gap-2"><Package class="w-3.5 h-3.5" /> Select Item (With Stock)</label>
                            <div class="relative">
                                <select v-model="form.itemid" class="w-full px-6 py-4 bg-secondary/30 border-secondary rounded-[1.25rem] text-sm focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all font-black uppercase tracking-widest cursor-pointer appearance-none shadow-sm pr-12" required :disabled="filteredItems.length === 0">
                                    <option v-if="filteredItems.length === 0" value="">No Items with Stock Found</option>
                                    <option v-for="item in filteredItems" :key="item.itemid" :value="item.itemid">{{ item.name }} ({{ item.unit }})</option>
                                </select>
                                <ChevronDown class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
                            </div>
                            <span v-if="errors.itemid" class="text-[10px] text-destructive font-black uppercase tracking-wider">{{ errors.itemid }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-6 pt-2">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center gap-2"><Hash class="w-3.5 h-3.5" /> Quantity</label>
                                <input v-model="form.qty" type="number" step="0.01" min="0.01" required class="w-full px-6 py-4 bg-secondary/30 border-secondary rounded-[1.25rem] text-sm focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all font-black">
                                <span v-if="errors.qty" class="text-[10px] text-destructive font-black uppercase tracking-wider">{{ errors.qty }}</span>
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center gap-2"><Scale class="w-3.5 h-3.5" /> Unit</label>
                                <div class="relative">
                                    <select v-model="form.unit" class="w-full px-6 py-4 bg-secondary/30 border-secondary rounded-[1.25rem] text-sm focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all font-black uppercase tracking-widest cursor-pointer appearance-none shadow-sm pr-12" required>
                                        <option v-for="u in availableUnits" :key="u" :value="u">{{ u }}</option>
                                    </select>
                                    <ChevronDown class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
                                </div>
                                <span v-if="errors.unit" class="text-[10px] text-destructive font-black uppercase tracking-wider">{{ errors.unit }}</span>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center gap-2"><Tag class="w-3.5 h-3.5" /> Reason / Notes</label>
                            <textarea v-model="form.reason" rows="2" placeholder="e.g. Expired, Spilled, Broken packaging..." class="w-full px-6 py-4 bg-secondary/30 border-secondary rounded-[1.25rem] text-sm focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all font-bold uppercase placeholder:normal-case resize-none" required></textarea>
                            <span v-if="errors.reason" class="text-[10px] text-destructive font-black uppercase tracking-wider">{{ errors.reason }}</span>
                        </div>
                        <div class="space-y-4">
                            <label class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center justify-center gap-2"><Camera class="w-3.5 h-3.5" /> Evidence Photo</label>
                            <div class="flex flex-col items-center justify-center">
                                <div v-if="evidencePreview" class="w-56 h-56 rounded-[2.5rem] overflow-hidden bg-secondary border-4 border-primary/20 shadow-2xl relative group">
                                    <img :src="evidencePreview" class="w-full h-full object-cover" />
                                    <label class="absolute inset-0 bg-primary/40 opacity-0 group-hover:opacity-100 transition-all flex flex-col items-center justify-center cursor-pointer text-white backdrop-blur-sm"><Camera class="w-10 h-10 mb-2 drop-shadow-lg" /><span class="text-[10px] font-black uppercase tracking-[0.2em] drop-shadow-md">Change Photo</span><input type="file" class="hidden" @change="handleFileSelect" accept="image/*" /></label>
                                </div>
                                <label v-else class="w-full flex flex-col items-center justify-center px-6 py-12 bg-secondary/30 border-4 border-dashed border-secondary rounded-[2.5rem] cursor-pointer hover:bg-primary/5 hover:border-primary/20 transition-all group">
                                    <div class="w-20 h-20 rounded-3xl bg-primary/10 flex items-center justify-center text-primary mb-4 group-hover:scale-110 transition-transform shadow-inner"><Camera class="w-10 h-10 stroke-[2.5]" /></div>
                                    <div class="text-center"><span class="text-[11px] font-black uppercase tracking-[0.2em] text-primary">Upload Evidence</span><p class="text-[9px] font-bold text-muted-foreground uppercase tracking-widest mt-1 opacity-60">Tap to select photo</p></div>
                                    <input type="file" class="hidden" @change="handleFileSelect" accept="image/*" />
                                </label>
                            </div>
                            <span v-if="errors.evidence" class="text-[10px] text-destructive font-black uppercase tracking-wider text-center block">{{ errors.evidence }}</span>
                        </div>
                        <div class="pt-6 flex flex-col sm:flex-row justify-end gap-4">
                            <button type="button" @click="closeModal" class="px-8 py-4 text-[10px] font-black uppercase tracking-widest text-muted-foreground hover:bg-secondary rounded-2xl transition-all active:scale-95 order-2 sm:order-1">Cancel</button>
                            <button type="submit" :disabled="isSubmitting || filteredItems.length === 0" class="px-10 py-4 text-[10px] font-black uppercase tracking-widest bg-primary text-primary-foreground rounded-2xl hover:bg-primary/90 transition-all disabled:opacity-50 shadow-2xl shadow-primary/20 active:scale-95 order-1 sm:order-2">{{ isSubmitting ? 'Submitting...' : 'Submit Report' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>

        <!-- Image Cropper Modal -->
        <transition enter-active-class="transition-all duration-300 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition-all duration-200 ease-in" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
            <div v-if="isCropModalOpen" class="fixed inset-0 bg-transparent backdrop-blur-xl z-[60] flex items-center justify-center p-4">
                <div class="bg-card border border-secondary rounded-[3rem] shadow-2xl w-full max-w-2xl overflow-hidden" @click.stop>
                    <div class="px-8 py-6 border-b border-secondary flex items-center justify-center bg-secondary/10">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary"><Scissors class="w-5 h-5 stroke-[3]" /></div>
                            <h2 class="text-lg font-black uppercase tracking-tight text-primary">Adjust Photo</h2>
                        </div>
                    </div>
                    <div class="p-8 flex flex-col items-center">
                        <div class="bg-black/5 rounded-[2.5rem] overflow-hidden border border-secondary aspect-square w-full max-md mx-auto flex items-center justify-center shadow-inner">
                            <Cropper ref="cropperRef" :src="rawImage" :stencil-props="{ aspectRatio: 1 }" class="w-full h-full" />
                        </div>
                        <div class="pt-8 flex flex-col sm:flex-row justify-center gap-4 w-full max-w-md">
                            <button @click="applyCrop" class="w-full sm:flex-1 py-4 text-[10px] font-black uppercase tracking-[0.2em] bg-primary text-primary-foreground rounded-2xl hover:bg-primary/90 transition-all flex items-center justify-center gap-3 shadow-xl shadow-primary/20 active:scale-95"><Check class="w-4 h-4 stroke-[4]" /> Save Crop</button>
                            <button @click="isCropModalOpen = false" class="w-full sm:w-auto px-8 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground hover:bg-secondary rounded-2xl transition-all font-black">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Global Confirmation Modal -->
        <ConfirmationModal
            :show="showConfirmModal"
            :title="confirmData.title"
            :message="confirmData.message"
            :type="confirmData.type"
            :confirmText="confirmData.confirmText"
            @close="showConfirmModal = false"
            @confirm="confirmData.action"
        />

        <!-- Pagination -->
        <div v-if="opnames.links && opnames.links.length > 3" class="flex flex-wrap justify-center gap-2 mt-8">
            <template v-for="(link, key) in opnames.links" :key="key">
                <div v-if="link.url === null" class="px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-muted-foreground/30 bg-card border border-secondary rounded-xl" v-html="link.label" />
                <Link v-else :href="link.url" :class="['px-4 py-2.5 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all border', link.active ? 'bg-primary text-primary-foreground border-primary shadow-lg shadow-primary/20' : 'bg-card border-secondary text-muted-foreground hover:bg-secondary hover:text-primary']" v-html="link.label" />
            </template>
        </div>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
.vue-advanced-cropper { background: transparent; }
:deep(.vue-simple-handler) { background: #8b5e34; border: 2px solid white; width: 12px; height: 12px; border-radius: 4px; }
:deep(.vue-simple-line) { border-color: #8b5e34; border-width: 2px; }
</style>