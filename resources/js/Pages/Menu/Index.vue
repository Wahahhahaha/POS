<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import Card from '@/Components/ui/Card.vue'
import Button from '@/Components/ui/Button.vue'
import Input from '@/Components/ui/Input.vue'
import Modal from '@/Components/ui/Modal.vue'
import ConfirmationModal from '@/Components/ui/ConfirmationModal.vue'
import Pagination from '@/Components/ui/Pagination.vue'
import { Plus, Pencil, Trash2, Search, ImageIcon, Save, Utensils, Crop, Upload, Tag, X, ChevronDown, Building2, MapPin } from '@lucide/vue'        
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Cropper } from 'vue-advanced-cropper'
import 'vue-advanced-cropper/dist/style.css'

defineOptions({ layout: AppLayout })

const page = usePage()
const props = defineProps({
    menus: Object,
    categories: Array,
    branches: Array,
    errors: Object,
    auth: Object,
    ziggy: Object,
    system: Object,
    locale: String,
    translations: Object,
    filters: Object,
})

const ui = computed(() => page.props.translations?.ui || {})

const searchQuery = ref(props.filters?.search || '')
const selectedCategory = ref(props.filters?.category || '')
const selectedBranch = ref(props.filters?.branch || '')

const showModal = ref(false)
const showCropModal = ref(false)
const showConfirmModal = ref(false)
const menuToDelete = ref(null)
const impactData = ref([])
const editingMenu = ref(null)
const rawImage = ref(null)
const cropperRef = ref(null)
const imagePreview = ref(null)

// Use server-side data directly
const filteredMenus = computed(() => props.menus.data)

const applyFilters = () => {
    router.get(route('menu.index'), {
        'filter[name]': searchQuery.value,
        'filter[categoryid]': selectedCategory.value,
        'filter[branchid]': selectedBranch.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    })
}

const clearFilters = () => {
    searchQuery.value = ''
    selectedCategory.value = ''
    selectedBranch.value = ''
}

// Watch filters with debounce
let timeout = null
watch([searchQuery, selectedCategory, selectedBranch], () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        applyFilters()
    }, 300)
})

const form = useForm({
    name: '',
    price: 0,
    categoryid: '',
    description: '',
    image: null,
    available_branches: [],
})

const openAddModal = () => {
    editingMenu.value = null
    form.reset()
    if (props.categories.length > 0) {
        form.categoryid = props.categories[0].categoryid
    }
    // Default to all branches for new items
    form.available_branches = props.branches.map(b => b.branchid)
    imagePreview.value = null
    showModal.value = true
}

const openEditModal = (menu) => {
    editingMenu.value = menu
    form.name = menu.name
    form.price = menu.price
    form.categoryid = menu.categoryid
    form.description = menu.description || ''
    form.image = null
    form.available_branches = menu.available_branches || []
    imagePreview.value = menu.image_url
    showModal.value = true
}

const onFileChange = (e) => {
    const file = e.target.files[0]
    if (file) {
        const reader = new FileReader()
        reader.onload = (event) => {
            rawImage.value = event.target.result
            showCropModal.value = true
        }
        reader.readAsDataURL(file)
        e.target.value = ''
    }
}

const cropImage = () => {
    const { canvas } = cropperRef.value.getResult()
    canvas.toBlob((blob) => {
        const croppedFile = new File([blob], 'menu-item.jpg', { type: 'image/jpeg' })
        form.image = croppedFile
        imagePreview.value = URL.createObjectURL(blob)
        showCropModal.value = false
    }, 'image/jpeg', 0.8)
}

const submit = () => {
    if (editingMenu.value) {
        form.transform((data) => ({
            ...data,
            _method: 'PUT',
        })).post(route('menu.update', editingMenu.value.menuid), {
            onSuccess: () => {
                showModal.value = false
                form.reset()
            },
            forceFormData: true,
        })
    } else {
        form.transform((data) => data)
            .post(route('menu.store'), {
                onSuccess: () => {
                    showModal.value = false
                    form.reset()
                }
            })
    }
}

const confirmDelete = (menu) => {
    menuToDelete.value = menu
    impactData.value = menu.impact || []
    showConfirmModal.value = true
}

const deleteMenu = () => {
    if (menuToDelete.value) {
        form.delete(route('menu.destroy', menuToDelete.value.menuid), {
            onFinish: () => {
                showConfirmModal.value = false
                menuToDelete.value = null
                impactData.value = []
            }
        })
    }
}

const formatPrice = (value) => {
    return new Intl.NumberFormat(page.props.locale === 'id' ? 'id-ID' : 'en-US', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(value)
}

const toggleBranch = (branchId) => {
    const index = form.available_branches.indexOf(branchId)
    if (index === -1) {
        form.available_branches.push(branchId)
    } else {
        form.available_branches.splice(index, 1)
    }
}
</script>

<template>
    <Head :title="ui.menu_management || 'Menu Management'" />

    <div class="max-w-7xl mx-auto space-y-6 lg:space-y-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 px-2 lg:px-0">
            <div>
                <h1 class="text-2xl lg:text-3xl font-black tracking-tight text-primary uppercase">{{ ui.menu_list || 'Menu List' }}</h1>
                <p class="text-muted-foreground text-[10px] lg:text-sm font-medium uppercase tracking-widest mt-1">{{ ui.manage_fnb || 'Manage your food and beverage items' }}</p>
            </div>
            
            <Button @click="openAddModal" class="gap-2 h-12 w-full sm:w-auto px-6 rounded-2xl shadow-lg shadow-primary/20">
                <Plus class="w-5 h-5" />
                {{ ui.add_new_menu || 'Add New Menu' }}
            </Button>
        </div>

        <!-- Filters & Search -->
        <div class="flex flex-col sm:flex-row items-center gap-4 px-2 lg:px-0">
            <div class="relative w-full sm:max-w-xs flex-1">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <Input 
                    v-model="searchQuery" 
                    :placeholder="ui.search_menu || 'Search menu name...'" 
                    class="pl-10 bg-card border-transparent shadow-sm h-11 h-12 rounded-2xl text-xs font-bold"
                />
            </div>

            <!-- Branch Filter -->
            <div class="relative w-full sm:w-56">
                <Building2 class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <select 
                    v-model="selectedBranch"
                    class="w-full pl-10 h-12 rounded-2xl border-transparent bg-card text-[10px] font-black uppercase tracking-widest shadow-sm outline-none focus:ring-1 focus:ring-primary/20 transition-all appearance-none cursor-pointer"
                >
                    <option value="">{{ ui.all_branches || 'All Branches' }}</option>
                    <option v-for="branch in branches" :key="branch.branchid" :value="branch.branchid">
                        {{ branch.branchname }}
                    </option>
                </select>
                <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
            </div>

            <!-- Category Filter -->
            <div class="relative w-full sm:w-56">
                <Tag class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <select 
                    v-model="selectedCategory"
                    class="w-full pl-10 h-12 rounded-2xl border-transparent bg-card text-[10px] font-black uppercase tracking-widest shadow-sm outline-none focus:ring-1 focus:ring-primary/20 transition-all appearance-none cursor-pointer"
                >
                    <option value="">{{ ui.all_categories || 'All Categories' }}</option>
                    <option v-for="cat in categories" :key="cat.categoryid" :value="cat.categoryid">
                        {{ cat.categoryname }}
                    </option>
                </select>
                <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
            </div>

            <Button 
                v-if="searchQuery || selectedCategory || selectedBranch" 
                variant="ghost" 
                @click="clearFilters"
                class="h-12 px-4 rounded-xl text-destructive hover:bg-destructive/10 gap-2 font-black uppercase tracking-widest text-[10px]"
            >
                <X class="w-4 h-4" /> {{ ui.reset || 'Reset' }}
            </Button>
        </div>

        <!-- Menu Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 lg:gap-6 px-2 lg:px-0 pb-20">
            <template v-for="menu in filteredMenus" :key="menu.menuid">
                <Card class="group overflow-hidden border-none shadow-sm hover:shadow-xl transition-all duration-300 bg-card/50">
                    <div class="aspect-[4/3] overflow-hidden relative bg-secondary/30">
                        <img 
                            v-if="menu.image_url" 
                            :src="menu.image_url" 
                            class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center text-muted-foreground/30">
                            <Utensils class="w-12 h-12" />
                        </div>
                        
                        <!-- Floating Actions (Always visible on mobile, hover on desktop) -->
                        <div class="absolute top-3 right-3 flex flex-col gap-2 lg:opacity-0 lg:group-hover:opacity-100 transition-opacity z-10">
                            <button 
                                @click="openEditModal(menu)"
                                class="p-2.5 bg-white/95 backdrop-blur text-primary rounded-xl shadow-xl hover:bg-primary hover:text-white transition-all border border-secondary/50"
                            >
                                <Pencil class="w-4 h-4 lg:w-3.5 lg:h-3.5" />
                            </button>
                            <button 
                                @click="confirmDelete(menu)"
                                class="p-2.5 bg-white/95 backdrop-blur text-destructive rounded-xl shadow-xl hover:bg-destructive hover:text-white transition-all border border-secondary/50"
                            >
                                <Trash2 class="w-4 h-4 lg:w-3.5 lg:h-3.5" />
                            </button>
                        </div>
                    </div>

                    <div class="p-4 lg:p-5 space-y-2 lg:space-y-3 min-w-0">
                        <div class="flex justify-between items-start gap-2">
                            <h3 class="font-black text-primary uppercase leading-tight tracking-tight text-sm lg:text-base whitespace-normal [overflow-wrap:anywhere]">
                                {{ menu.name }}
                            </h3>
                        </div>
                        <div v-if="menu.category" class="inline-flex items-center px-2 py-0.5 rounded-full text-[8px] lg:text-[9px] font-black bg-primary/10 text-primary uppercase tracking-widest border border-primary/20">
                            {{ menu.category.categoryname }}
                        </div>
                        <p class="text-[10px] lg:text-xs text-muted-foreground whitespace-normal [overflow-wrap:anywhere] line-clamp-2 min-h-[2.5rem]">
                            {{ menu.description || (ui.no_financial_data ? 'No description provided.' : 'Tidak ada deskripsi.') }}
                        </p>
                        <div class="pt-2 border-t border-secondary/50 flex items-center justify-between">
                            <span class="text-base lg:text-lg font-black text-primary">{{ formatPrice(menu.price) }}</span>
                        </div>
                    </div>
                </Card>
            </template>

            <div v-if="filteredMenus.length === 0" class="col-span-full py-20 text-center">
                <div class="inline-flex p-6 rounded-full bg-secondary/30 text-muted-foreground mb-4">
                    <Search class="w-12 h-12" />
                </div>
                <h3 class="text-xl font-bold text-primary uppercase">{{ ui.no_menu_found || 'No menu items found' }}</h3>
                <p class="text-muted-foreground text-sm">{{ ui.adjust_search_or_add || 'Try adjusting your search or add a new menu item.' }}</p>
            </div>
        </div>

        <!-- Pagination -->
        <div class="mt-12">
            <Pagination :links="menus.links" />
        </div>

        <!-- CRUD Modal -->
        <Modal :show="showModal" :title="editingMenu ? (ui.edit_menu_item || 'Edit Menu Item') : (ui.add_new_menu_item || 'Add New Menu Item')" @close="showModal = false">
            <form @submit.prevent="submit" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-12">
                    <!-- Left: Image & Availability -->
                    <div class="space-y-6">
                        <div class="space-y-3">
                            <label class="text-[9px] lg:text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground ml-1">{{ ui.menu_picture || 'Menu Picture' }}</label>
                            <div class="aspect-square rounded-[2rem] bg-secondary/30 border-2 border-dashed border-secondary flex flex-col items-center justify-center overflow-hidden relative group shadow-inner">
                                <img v-if="imagePreview" :src="imagePreview" class="w-full h-full object-cover" />
                                <div v-else class="flex flex-col items-center gap-2 text-muted-foreground/50">
                                    <ImageIcon class="w-10 h-10 lg:w-12 lg:h-12" />
                                    <span class="text-[8px] lg:text-[10px] font-black uppercase">{{ ui.upload_image || 'Upload Image' }}</span>
                                </div>
                                
                                <label class="absolute inset-0 cursor-pointer flex items-center justify-center bg-primary/0 hover:bg-primary/20 transition-colors">
                                    <input type="file" @change="onFileChange" class="hidden" accept="image/*" />
                                    <div class="p-3 bg-white rounded-2xl shadow-2xl opacity-0 group-hover:opacity-100 transition-all transform translate-y-4 group-hover:translate-y-0">
                                        <Upload class="w-5 h-5 text-primary" />
                                    </div>
                                </label>
                            </div>
                            <div v-if="form.errors.image" class="text-xs text-destructive font-bold text-center mt-2">{{ form.errors.image }}</div>
                        </div>

                        <!-- Availability Selection -->
                        <div class="space-y-3 bg-secondary/10 p-5 rounded-[2rem] border border-secondary/30">
                            <label class="text-[9px] lg:text-[10px] font-bold uppercase tracking-[0.2em] text-primary flex items-center gap-2">
                                <Building2 class="w-3.5 h-3.5" />
                                {{ ui.availability || 'Branch Availability' }}
                            </label>
                            <div class="space-y-2">
                                <button 
                                    v-for="branch in branches" 
                                    :key="branch.branchid"
                                    type="button"
                                    @click="toggleBranch(branch.branchid)"
                                    :class="[
                                        'w-full flex items-center justify-between px-4 py-3 rounded-xl border transition-all text-left group',
                                        form.available_branches.includes(branch.branchid)
                                            ? 'bg-primary border-primary text-primary-foreground shadow-lg shadow-primary/20'
                                            : 'bg-card border-secondary text-muted-foreground hover:border-primary/30'
                                    ]"
                                >
                                    <div class="flex items-center gap-3">
                                        <MapPin :class="['w-4 h-4', form.available_branches.includes(branch.branchid) ? 'text-primary-foreground' : 'text-primary']" />
                                        <span class="text-[11px] font-black uppercase tracking-wider">{{ branch.branchname }}</span>
                                    </div>
                                    <div :class="['w-4 h-4 rounded-full border-2 flex items-center justify-center transition-colors', form.available_branches.includes(branch.branchid) ? 'bg-white border-white' : 'border-secondary group-hover:border-primary/30']">
                                        <div v-if="form.available_branches.includes(branch.branchid)" class="w-2 h-2 rounded-full bg-primary animate-in zoom-in"></div>
                                    </div>
                                </button>
                            </div>
                            <div v-if="form.errors.available_branches" class="text-xs text-destructive font-bold mt-2">{{ form.errors.available_branches }}</div>
                        </div>
                    </div>

                    <!-- Right: Form Data -->
                    <div class="space-y-5 lg:space-y-6">
                        <div class="space-y-1.5 lg:space-y-2">
                            <label class="text-[9px] lg:text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground ml-1">{{ ui.item_name || 'Item Name' }}</label>
                            <Input v-model="form.name" placeholder="e.g. Avocado Toast" required class="h-12 lg:h-14 rounded-2xl text-base font-black uppercase tracking-tight text-primary placeholder:normal-case placeholder:font-medium" />
                            <div v-if="form.errors.name" class="text-xs text-destructive font-bold">{{ form.errors.name }}</div>
                        </div>

                        <div class="space-y-1.5 lg:space-y-2">
                            <label class="text-[9px] lg:text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground ml-1">{{ ui.category || 'Category' }}</label>
                            <div class="relative">
                                <Tag class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                                <select 
                                    v-model="form.categoryid"
                                    class="w-full pl-12 h-12 lg:h-14 rounded-2xl border border-input bg-card text-sm font-bold shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/10 focus-visible:border-primary appearance-none cursor-pointer"
                                    required
                                >
                                    <option value="" disabled>{{ ui.select_category || 'Select Category' }}</option>
                                    <option v-for="cat in categories" :key="cat.categoryid" :value="cat.categoryid">
                                        {{ cat.categoryname }}
                                    </option>
                                </select>
                                <ChevronDown class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
                            </div>
                            <div v-if="form.errors.categoryid" class="text-xs text-destructive font-bold">{{ form.errors.categoryid }}</div>
                        </div>

                        <div class="space-y-1.5 lg:space-y-2">
                            <label class="text-[9px] lg:text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground ml-1">{{ ui.price || 'Price' }} (IDR)</label>
                            <Input v-model="form.price" type="number" required min="0" class="h-12 lg:h-14 rounded-2xl text-lg font-black text-primary" />
                            <div v-if="form.errors.price" class="text-xs text-destructive font-bold">{{ form.errors.price }}</div>
                        </div>

                        <div class="space-y-1.5 lg:space-y-2">
                            <label class="text-[9px] lg:text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground ml-1">{{ ui.description || 'Description' }}</label>
                            <textarea 
                                v-model="form.description" 
                                rows="4"
                                class="w-full p-4 rounded-2xl border border-input bg-card text-sm font-medium shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/10 focus-visible:border-primary"
                                :placeholder="ui.short_desc_placeholder || 'Short description of the item...'"
                            ></textarea>
                            <div v-if="form.errors.description" class="text-xs text-destructive font-bold">{{ form.errors.description }}</div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row justify-end gap-3 pt-6 border-t border-secondary/50">
                    <Button type="button" variant="ghost" @click="showModal = false" class="rounded-xl font-bold order-2 sm:order-1 h-12">{{ ui.cancel || 'Cancel' }}</Button>
                    <Button type="submit" class="gap-2 px-12 h-12 rounded-2xl shadow-lg shadow-primary/20 order-1 sm:order-2 font-black uppercase tracking-widest text-xs" :disabled="form.processing">
                        <Save class="w-4 h-4" />
                        {{ editingMenu ? (ui.update_item || 'Update Item') : (ui.create_item || 'Create Item') }}
                    </Button>
                </div>
            </form>
        </Modal>

        <!-- Crop Modal -->
        <Modal :show="showCropModal" :title="ui.crop_menu_picture || 'Crop Menu Picture'" @close="showCropModal = false">
            <div class="space-y-4">
                <div class="overflow-hidden rounded-2xl border border-secondary bg-black/5 max-h-[60vh]">
                    <Cropper
                        ref="cropperRef"
                        class="h-[300px] sm:h-[400px]"
                        :src="rawImage"
                        :stencil-props="{
                            aspectRatio: 4/3
                        }"
                    />
                </div>
                <p class="text-[10px] text-muted-foreground text-center italic leading-tight">
                    {{ ui.crop_instruction || 'Crop your image to 4:3 ratio for the best card display.' }}
                </p>
            </div>
            
            <template #footer>
                <div class="flex flex-col sm:flex-row gap-3 w-full">
                    <Button variant="ghost" @click="showCropModal = false" class="rounded-xl font-bold w-full sm:w-auto order-2 sm:order-1 h-12">{{ ui.cancel || 'Cancel' }}</Button>
                    <Button @click="cropImage" class="gap-2 px-8 h-12 rounded-xl font-black uppercase tracking-widest text-xs w-full sm:w-auto order-1 sm:order-2 shadow-lg shadow-primary/20">
                        <Crop class="w-4 h-4" />
                        {{ ui.crop_apply || 'Crop & Apply' }}
                    </Button>
                </div>
            </template>
        </Modal>

        <!-- Confirmation Modal -->
        <ConfirmationModal 
            :show="showConfirmModal" 
            :title="ui.delete_menu_item || 'Delete Menu Item'"
            :message="ui.confirm_delete_menu || 'Are you sure you want to delete this menu item? This action cannot be undone.'"
            :impact="impactData"
            type="danger"
            :confirmText="ui.delete || 'Delete'"
            @close="showConfirmModal = false"
            @confirm="deleteMenu"
        />
    </div>
</template>
