<script setup>
import { ref, watch, computed } from 'vue'
import { Head, router, usePage, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Package, Search, Edit, Trash2, X, Plus, Tag, QrCode, Scale, Layers } from '@lucide/vue'
import { debounce } from 'lodash-es'

defineOptions({ layout: AppLayout })

const props = defineProps({
    items: Object,
    filters: Object
})

const page = usePage()
const search = ref(props.filters?.search || '')
const ui = computed(() => page.props.translations?.ui || {})

const isModalOpen = ref(false)
const isDeleteModalOpen = ref(false)
const selectedItem = ref(null)
const isSubmitting = ref(false)
const errors = ref({})

const form = ref({
    name: '',
    barcode: '',
    unit: 'Pcs',
    type: 'Items',
})

const itemTypes = ['Items', 'Products', 'Ingredients', 'Merchandise']
const units = ['Pcs', 'Pack', 'Kg', 'L']

watch(search, debounce((value) => {
    router.get(route('item.data-items.index'), { search: value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    })
}, 300))

const openModal = (item = null) => {
    errors.value = {}
    if (item) {
        selectedItem.value = item
        form.value = {
            name: item.name,
            barcode: item.barcode || '',
            unit: item.unit,
            type: item.type,
        }
    } else {
        selectedItem.value = null
        form.value = {
            name: '',
            barcode: '',
            unit: 'Pcs',
            type: 'Items',
        }
    }
    isModalOpen.value = true
}

const closeModal = () => {
    isModalOpen.value = false
    selectedItem.value = null
    errors.value = {}
}

const openDeleteModal = (item) => {
    selectedItem.value = item
    isDeleteModalOpen.value = true
}

const closeDeleteModal = () => {
    isDeleteModalOpen.value = false
    selectedItem.value = null
}

const submit = () => {
    isSubmitting.value = true
    errors.value = {}

    const submitRoute = selectedItem.value 
        ? route('item.data-items.update', selectedItem.value.itemid)
        : route('item.data-items.store')
    
    const method = selectedItem.value ? 'put' : 'post'

    router[method](submitRoute, form.value, {
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

const deleteItem = () => {
    isSubmitting.value = true
    router.delete(route('item.data-items.destroy', selectedItem.value.itemid), {
        preserveScroll: true,
        onSuccess: () => closeDeleteModal(),
        onFinish: () => {
            isSubmitting.value = false
        }
    })
}
</script>

<template>
    <Head :title="ui.inventory_item || 'Inventory Item'" />

    <div class="max-w-7xl mx-auto space-y-6 lg:space-y-8">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between gap-4 items-start sm:items-center px-2 lg:px-0">
            <div>
                <h1 class="text-2xl lg:text-3xl font-black uppercase tracking-tight text-primary">{{ ui.inventory_item || 'Inventory Item' }}</h1>
                <p class="text-muted-foreground text-[10px] lg:text-sm font-medium uppercase tracking-widest mt-1 opacity-60">{{ ui.manage_inventory_items || 'Manage your inventory items.' }}</p>
            </div>
            <button @click="openModal()" class="px-6 py-3 bg-primary text-primary-foreground font-black uppercase tracking-widest text-xs rounded-2xl hover:bg-primary/90 transition-all flex items-center gap-2 shadow-lg shadow-primary/20 active:scale-95 h-12">
                <Plus class="w-4 h-4 stroke-[3]" />
                {{ ui.add_item || 'Add Item' }}
            </button>
        </div>

        <!-- Filters -->
        <div class="bg-card rounded-3xl shadow-sm border border-secondary p-5 mx-2 lg:mx-0">
            <div class="relative max-w-md">
                <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <input 
                    v-model="search"
                    type="text" 
                    :placeholder="ui.search || 'Search...'" 
                    class="w-full pl-11 pr-4 py-3 bg-secondary/30 border-secondary rounded-2xl text-sm focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all font-bold"
                >
            </div>
        </div>

        <!-- Desktop View -->
        <div class="hidden md:block bg-card rounded-3xl shadow-sm border border-secondary overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-secondary/20 border-b border-secondary">
                            <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/60">{{ ui.item_name || 'Item Name' }}</th>
                            <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/60">{{ ui.barcode || 'Barcode' }}</th>
                            <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/60">{{ ui.type || 'Type' }}</th>
                            <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/60">{{ ui.unit || 'Unit' }}</th>
                            <th class="px-8 py-5 text-right text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/60">{{ ui.actions || 'Actions' }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/50">
                        <tr v-for="item in items.data" :key="item.itemid" class="hover:bg-primary/5 transition-colors group">
                            <td class="px-8 py-5">
                                <span class="font-black text-primary uppercase tracking-tight">{{ item.name }}</span>
                            </td>
                            <td class="px-8 py-5">
                                <span class="text-muted-foreground font-mono text-xs font-bold">{{ item.barcode || '-' }}</span>
                            </td>
                            <td class="px-8 py-5">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-primary/10 text-primary border border-primary/10">
                                    {{ item.type }}
                                </span>
                            </td>
                            <td class="px-8 py-5 text-muted-foreground font-bold text-sm">
                                {{ item.unit }}
                            </td>
                            <td class="px-8 py-5">
                                <div class="flex items-center justify-end gap-2 transition-opacity">
                                    <button @click="openModal(item)" class="p-2.5 text-primary hover:bg-primary hover:text-primary-foreground rounded-xl transition-all" title="Edit">
                                        <Edit class="w-4 h-4" />
                                    </button>
                                    <button @click="openDeleteModal(item)" class="p-2.5 text-destructive hover:bg-destructive hover:text-destructive-foreground rounded-xl transition-all" title="Delete">
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="items.data.length === 0">
                            <td colspan="5" class="px-8 py-20 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center gap-4">
                                    <div class="w-16 h-16 rounded-full bg-secondary/30 flex items-center justify-center">
                                        <Package class="w-8 h-8 opacity-20" />
                                    </div>
                                    <span class="font-black uppercase tracking-widest text-xs opacity-40">{{ ui.no_items_found || 'No items found.' }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile View -->
        <div class="md:hidden space-y-4">
            <div v-for="item in items.data" :key="item.itemid" class="bg-card rounded-3xl shadow-sm border border-secondary p-5 flex flex-col gap-5">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="font-black text-primary uppercase tracking-tight">{{ item.name }}</h3>
                        <p class="text-[10px] font-mono font-bold text-muted-foreground mt-1 uppercase tracking-wider">{{ item.barcode || '-' }}</p>
                    </div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-[0.2em] bg-primary/10 text-primary border border-primary/10">
                        {{ item.type }}
                    </span>
                </div>
                
                <div class="flex items-center justify-between pt-5 border-t border-secondary/50">
                    <span class="text-xs text-muted-foreground font-black uppercase tracking-widest">{{ item.unit }}</span>
                    <div class="flex items-center gap-2">
                        <button @click="openModal(item)" class="p-3 text-primary bg-primary/5 hover:bg-primary hover:text-primary-foreground rounded-2xl transition-all">
                            <Edit class="w-4 h-4" />
                        </button>
                        <button @click="openDeleteModal(item)" class="p-3 text-destructive bg-destructive/5 hover:bg-destructive hover:text-destructive-foreground rounded-2xl transition-all">
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
            
            <div v-if="items.data.length === 0" class="bg-card rounded-3xl shadow-sm border border-secondary p-12 text-center text-muted-foreground flex flex-col items-center gap-4">
                <div class="w-16 h-16 rounded-full bg-secondary/30 flex items-center justify-center">
                    <Package class="w-8 h-8 opacity-20" />
                </div>
                <span class="font-black uppercase tracking-widest text-xs opacity-40">{{ ui.no_items_found || 'No items found.' }}</span>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="items.links && items.links.length > 3" class="flex flex-wrap justify-center gap-2 mt-8">
            <template v-for="(link, key) in items.links" :key="key">
                <div v-if="link.url === null" class="px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-muted-foreground/30 bg-card border border-secondary rounded-xl" v-html="link.label" />
                <Link v-else :href="link.url" :class="[
                    'px-4 py-2.5 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all border',
                    link.active 
                        ? 'bg-primary text-primary-foreground border-primary shadow-lg shadow-primary/20' 
                        : 'bg-card border-secondary text-muted-foreground hover:bg-secondary hover:text-primary'
                ]" v-html="link.label" />
            </template>
        </div>

        <!-- Add/Edit Modal -->
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
                                <Package class="w-5 h-5" />
                            </div>
                            <h2 class="text-lg font-black uppercase tracking-tight text-primary">{{ selectedItem ? 'Edit Item' : 'Add New Item' }}</h2>
                        </div>
                        <button @click="closeModal" class="p-3 text-muted-foreground hover:bg-secondary hover:text-primary rounded-2xl transition-all">
                            <X class="w-5 h-5 stroke-[3]" />
                        </button>
                    </div>
                    
                    <form @submit.prevent="submit" class="p-8 space-y-6">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center gap-2">
                                <Tag class="w-3.5 h-3.5" />
                                Item Name
                            </label>
                            <input 
                                v-model="form.name" 
                                type="text" 
                                required
                                placeholder="e.g. Coca Cola"
                                class="w-full px-6 py-4 bg-secondary/30 border-secondary rounded-[1.25rem] text-sm focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all font-bold text-primary uppercase placeholder:normal-case"
                                :class="{ 'border-destructive focus:ring-destructive/10': errors.name }"
                            >
                            <span v-if="errors.name" class="text-[10px] text-destructive font-black uppercase tracking-wider">{{ errors.name }}</span>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center gap-2">
                                <QrCode class="w-3.5 h-3.5" />
                                Barcode (Optional)
                            </label>
                            <input 
                                v-model="form.barcode" 
                                type="text" 
                                placeholder="e.g. 8991234567890"
                                class="w-full px-6 py-4 bg-secondary/30 border-secondary rounded-[1.25rem] text-sm focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all font-bold font-mono"
                                :class="{ 'border-destructive focus:ring-destructive/10': errors.barcode }"
                            >
                            <span v-if="errors.barcode" class="text-[10px] text-destructive font-black uppercase tracking-wider">{{ errors.barcode }}</span>
                        </div>

                        <div class="grid grid-cols-2 gap-6 pt-2">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center gap-2">
                                    <Layers class="w-3.5 h-3.5" />
                                    Type
                                </label>
                                <select 
                                    v-model="form.type" 
                                    class="w-full px-6 py-4 bg-secondary/30 border-secondary rounded-[1.25rem] text-sm focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all font-black uppercase tracking-widest cursor-pointer appearance-none shadow-sm"
                                    required
                                >
                                    <option v-for="type in itemTypes" :key="type" :value="type" class="font-bold uppercase tracking-widest text-foreground">
                                        {{ type }}
                                    </option>
                                </select>
                                <span v-if="errors.type" class="text-[10px] text-destructive font-black uppercase tracking-wider">{{ errors.type }}</span>
                            </div>

                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center gap-2">
                                    <Scale class="w-3.5 h-3.5" />
                                    Unit
                                </label>
                                <select 
                                    v-model="form.unit" 
                                    class="w-full px-6 py-4 bg-secondary/30 border-secondary rounded-[1.25rem] text-sm focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all font-black uppercase tracking-widest cursor-pointer appearance-none shadow-sm"
                                    required
                                >
                                    <option v-for="unit in units" :key="unit" :value="unit" class="font-bold uppercase tracking-widest text-foreground">
                                        {{ unit }}
                                    </option>
                                </select>
                                <span v-if="errors.unit" class="text-[10px] text-destructive font-black uppercase tracking-wider">{{ errors.unit }}</span>
                            </div>
                        </div>

                        <div class="pt-6 flex justify-end gap-4">
                            <button type="button" @click="closeModal" class="px-6 py-3 text-[10px] font-black uppercase tracking-widest text-muted-foreground hover:bg-secondary rounded-2xl transition-all active:scale-95">
                                Cancel
                            </button>
                            <button type="submit" :disabled="isSubmitting" class="px-8 py-3 text-[10px] font-black uppercase tracking-widest bg-primary text-primary-foreground rounded-2xl hover:bg-primary/90 transition-all disabled:opacity-50 shadow-xl shadow-primary/20 active:scale-95">
                                {{ isSubmitting ? 'Saving...' : (selectedItem ? 'Update Item' : 'Create Item') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>

        <!-- Delete Modal -->
        <transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div v-if="isDeleteModalOpen" class="fixed inset-0 bg-transparent backdrop-blur-md z-50 flex items-center justify-center p-4">
                <div class="bg-card border border-secondary rounded-[2.5rem] shadow-2xl w-full max-w-sm overflow-hidden" @click.stop>
                    <div class="p-10 text-center space-y-6">
                        <div class="w-20 h-20 bg-destructive/10 text-destructive rounded-[2rem] flex items-center justify-center mx-auto mb-2 border border-destructive/10">
                            <Trash2 class="w-10 h-10" />
                        </div>
                        <div class="space-y-2">
                            <h2 class="text-xl font-black uppercase tracking-tight text-primary">Delete Item?</h2>
                            <p class="text-muted-foreground text-xs font-bold leading-relaxed uppercase tracking-wider opacity-60 px-4">
                                Are you sure you want to delete <br/><span class="text-primary font-black">"{{ selectedItem?.name }}"</span>? <br/>This action cannot be undone.
                            </p>
                        </div>
                        <div class="flex flex-col gap-3 pt-2">
                            <button @click="deleteItem" :disabled="isSubmitting" class="w-full py-4 text-[10px] font-black uppercase tracking-[0.2em] bg-destructive text-destructive-foreground rounded-2xl hover:bg-destructive/90 transition-all disabled:opacity-50 flex items-center justify-center gap-2 shadow-xl shadow-destructive/20 active:scale-95">
                                <Trash2 class="w-4 h-4 stroke-[3]" />
                                {{ isSubmitting ? 'Deleting...' : 'Confirm Delete' }}
                            </button>
                            <button @click="closeDeleteModal" class="w-full py-4 text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground hover:bg-secondary rounded-2xl transition-all active:scale-95 font-black">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>