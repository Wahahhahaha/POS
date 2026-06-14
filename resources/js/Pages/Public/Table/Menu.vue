<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { ChevronLeft, Search, UtensilsCrossed, ShoppingBag, X, Clock, Tag, Minus, Plus, CheckCircle2, Percent, Wallet, Gift, Info, Trash2 } from '@lucide/vue'
import { useCart } from '@/Composables/useCart'
import LanguageToggle from '@/Components/shared/LanguageToggle.vue'
import Modal from '@/Components/ui/Modal.vue'
import Button from '@/Components/ui/Button.vue'

defineOptions({ layout: null })

const page = usePage()

const props = defineProps({
    table: Object,
    categories: Array,
    packages: Array,
    promotions: Array,
    pendingOrders: Array,
})

const ui = computed(() => page.props.translations?.ui || {})

const { items, totalItems, totalPrice, addToCart, updateQuantity, removeFromCart } = useCart()

const searchQuery = ref('')
const selectedCategoryId = ref(null)
const showPromoModal = ref(false)
const selectedPromo = ref(null)

const showItemModal = ref(false)
const showCartDetails = ref(false)
const selectedItem = ref(null)
const itemQuantity = ref(1)
const selectedItemAddons = ref([])

onMounted(() => {
    console.log('Promotions received in Menu:', props.promotions);
})

const filteredPromotions = computed(() => {
    const list = Array.isArray(props.packages) ? props.packages : []
    if (!searchQuery.value) return list
    
    return list.filter(promo => 
        promo.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
})

const filteredCategories = computed(() => {
    return props.categories.map(cat => ({
        ...cat,
        menus: cat.menus.filter(menu => 
            menu.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            menu.description?.toLowerCase().includes(searchQuery.value.toLowerCase())
        )
    })).filter(cat => cat.menus.length > 0)
})

const formatPrice = (price) => {
    return new Intl.NumberFormat(page.props.locale === 'id' ? 'id-ID' : 'en-US', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(price || 0)
}

const scrollToCategory = (id) => {
    selectedCategoryId.value = id
    const element = document.getElementById(`category-${id}`)
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
}

const openPromo = (promo) => {
    selectedPromo.value = promo
    showPromoModal.value = true
}

const addPromoToCart = () => {
    if (selectedPromo.value) {
        // Create a pseudo-menu object for the cart
        const packageMenu = {
            id: `pkg-${selectedPromo.value.packageid}`,
            packageid: selectedPromo.value.packageid,
            name: selectedPromo.value.name,
            price: selectedPromo.value.price,
            image: null, // Packages don't have images yet
            is_package: true,
            menus: selectedPromo.value.menus,
            freebies: selectedPromo.value.freebies,
            all_promoids: selectedPromo.value.all_promoids
        }
        
        addToCart(packageMenu, 1, [], selectedPromo.value.price)
        showPromoModal.value = false
        selectedPromo.value = null
    }
}

const openItemModal = (menu) => {
    if (!menu.is_available) return
    selectedItem.value = menu
    itemQuantity.value = 1
    selectedItemAddons.value = []
    showItemModal.value = true
}

const toggleItemAddon = (addon) => {
    const index = selectedItemAddons.value.findIndex(a => a.addonid === addon.addonid)
    if (index > -1) {
        selectedItemAddons.value.splice(index, 1)
    } else {
        selectedItemAddons.value.push(addon)
    }
}

const isItemAddonSelected = (addonId) => {
    return selectedItemAddons.value.some(a => a.addonid === addonId)
}

const itemTotalPrice = computed(() => {
    if (!selectedItem.value) return 0
    const addonsTotal = selectedItemAddons.value.reduce((sum, addon) => sum + addon.price, 0)
    // Always use original price for cart base to handle "tidak berlaku kelipatan" at checkout
    return (selectedItem.value.price + addonsTotal) * itemQuantity.value
})

const addItemToCartFromModal = () => {
    if (selectedItem.value) {
        // Pass original price to addToCart, checkout will handle the one-time discount
        addToCart(selectedItem.value, itemQuantity.value, selectedItemAddons.value, itemTotalPrice.value)
        showItemModal.value = false
        selectedItem.value = null
    }
}
</script>

<template>
    <Head title="Menu" />

    <div class="min-h-screen bg-background pb-32">
        <!-- Sticky Header -->
        <header class="sticky top-0 z-50 bg-background/80 backdrop-blur-xl border-b border-secondary/50 px-6 py-4">
            <div class="flex items-center justify-between gap-4 mb-4">
                <Link :href="route('public.table.welcome', { barcode: table.barcode })" class="p-2 rounded-xl bg-secondary/50 text-primary">
                    <ChevronLeft class="w-5 h-5" />
                </Link>
                <div class="flex-1 text-center truncate">
                    <h1 class="text-xs font-black uppercase tracking-[0.2em] text-primary">{{ $page.props.system?.name }}</h1>
                    <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest">{{ ui.table || 'Table' }} {{ table.name }}</p>
                </div>
                <LanguageToggle />
            </div>

            <!-- Search Bar -->
            <div class="relative group">
                <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground transition-colors group-focus-within:text-primary" />
                <input 
                    v-model="searchQuery"
                    type="text" 
                    :placeholder="ui.what_to_eat || 'Apa yang ingin Anda makan hari ini?'" 
                    class="w-full bg-secondary/30 border-transparent rounded-2xl pl-11 pr-4 py-3 text-sm font-bold focus:ring-1 focus:ring-primary focus:bg-background transition-all outline-none"
                />
            </div>

            <!-- Categories Scroll -->
            <div class="flex gap-2 overflow-x-auto no-scrollbar py-4 mt-2">
                <!-- Promotions Shortcut -->
                <button 
                    v-if="promotions?.length > 0"
                    @click="scrollToCategory('promos')"
                    :class="[
                        'shrink-0 px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all border',
                        selectedCategoryId === 'promos' ? 'bg-primary border-primary text-primary-foreground shadow-lg shadow-primary/20 scale-105' : 'bg-secondary/50 border-transparent text-muted-foreground'
                    ]"
                >
                    {{ ui.promotions || 'Promotions' }}
                </button>

                <button 
                    v-for="cat in categories" 
                    :key="cat.id"
                    @click="scrollToCategory(cat.id)"
                    :class="[
                        'shrink-0 px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all border',
                        selectedCategoryId === cat.id ? 'bg-primary border-primary text-primary-foreground shadow-lg shadow-primary/20 scale-105' : 'bg-secondary/50 border-transparent text-muted-foreground'
                    ]"
                >
                    {{ cat.name }}
                </button>
            </div>
        </header>

        <!-- Menu List -->
        <main class="px-4 sm:px-6 pt-6 space-y-10">
            <!-- Pending Orders Notification -->
            <div v-if="pendingOrders?.length > 0" class="space-y-3">
                <div v-for="order in pendingOrders" :key="order.id" class="p-4 bg-primary/10 border border-primary/20 rounded-[2rem] flex items-center justify-between gap-4 animate-in slide-in-from-top-4 duration-500">
                    <div class="flex items-center gap-3">
                        <div class="p-2 rounded-xl bg-primary text-primary-foreground">
                            <Clock class="w-4 h-4" />
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-primary">{{ ui.pending_payment || 'Pending Payment' }}</p>
                            <p class="text-xs font-bold text-muted-foreground uppercase tracking-tight">{{ ui.order || 'Order' }} #{{ order.id }} &bull; {{ formatPrice(order.total) }}</p>
                        </div>
                    </div>
                    <Link 
                        :href="route('public.table.waiting', { barcode: table.barcode, order: order.id })"
                        class="px-4 py-2 rounded-xl bg-primary text-primary-foreground text-[10px] font-black uppercase tracking-widest shadow-md shadow-primary/20 hover:scale-105 transition-all"
                    >
                        {{ ui.pay_now || 'Pay Now' }}
                    </Link>
                </div>
            </div>

            <!-- Active Promotions Hero Banner -->
            <section v-if="promotions?.length > 0" class="relative -mx-4 sm:-mx-6 mb-8">
                <div class="flex overflow-x-auto gap-4 px-4 sm:px-6 pb-6 no-scrollbar snap-x snap-mandatory">
                    <div 
                        v-for="promo in promotions" 
                        :key="promo.promotionid"
                        class="shrink-0 w-[90vw] sm:w-[500px] bg-primary border-4 border-white shadow-2xl rounded-[2.5rem] p-6 flex flex-col justify-center min-h-[160px] relative overflow-hidden snap-center"
                    >
                        <!-- Decorative background -->
                        <div class="absolute -right-10 -bottom-10 opacity-10 transform rotate-12">
                            <Percent v-if="promo.type === 'DISCOUNT_PERCENT'" class="w-48 h-48" />
                            <Wallet v-else-if="promo.type === 'DISCOUNT_FIXED'" class="w-48 h-48" />
                            <Gift v-else class="w-48 h-48" />
                        </div>

                        <div class="relative z-10 flex flex-col h-full justify-center">
                            <div class="space-y-1 mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="px-3 py-1 rounded-full bg-white text-primary text-[10px] font-black uppercase tracking-[0.2em] shadow-sm flex items-center gap-1.5">
                                        <Percent v-if="promo.type === 'DISCOUNT_PERCENT'" class="w-3.5 h-3.5" />
                                        <Wallet v-else-if="promo.type === 'DISCOUNT_FIXED'" class="w-3.5 h-3.5" />
                                        <Gift v-else class="w-3.5 h-3.5" />
                                        {{ promo.type.replace(/_/g, ' ') }}
                                    </span>
                                    <span v-if="promo.status === 'Certain Period'" class="text-[9px] font-black text-white/80 uppercase tracking-widest bg-black/20 px-2.5 py-1 rounded-full backdrop-blur-sm">
                                        {{ new Date(promo.datefrom).toLocaleDateString([], {day: '2-digit', month: 'short'}) }} - {{ new Date(promo.dateto).toLocaleDateString([], {day: '2-digit', month: 'short', year: 'numeric'}) }}
                                    </span>
                                </div>
                                <h3 class="text-2xl sm:text-3xl font-black text-white uppercase tracking-tighter leading-none mt-3 drop-shadow-lg">{{ promo.name }}</h3>
                            </div>

                            <div class="text-base sm:text-lg font-bold text-white/95 leading-tight max-w-[90%] mt-2">
                                <template v-if="promo.type === 'DISCOUNT_PERCENT'">Save <span class="font-black text-yellow-300 text-2xl underline decoration-4 underline-offset-4">{{ promo.discount_value }}%</span> on your next favorites!</template>
                                <template v-else-if="promo.type === 'DISCOUNT_FIXED'">Save <span class="font-black text-yellow-300 text-2xl underline decoration-4 underline-offset-4">{{ formatPrice(promo.discount_value) }}</span> on selected items!</template>
                                <template v-else-if="promo.type === 'BUY_X_GET_Y'">
                                    Buy {{ promo.buy_qty }} {{ promo.buy_menu?.name }} <br/>
                                    <span class="font-black text-yellow-300 uppercase tracking-tight text-xl">Get {{ promo.get_qty }} {{ promo.get_menu?.name }} FREE</span>
                                </template>
                            </div>
                            
                            <div v-if="promo.min_purchase > 0" class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white/10 backdrop-blur-md border border-white/20 self-start">
                                <Info class="w-4 h-4 text-white" />
                                <span class="text-[10px] font-black text-white uppercase tracking-widest">Min. spend {{ formatPrice(promo.min_purchase) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Promotions Section -->
            <section v-if="filteredPromotions.length > 0" id="category-promos" class="space-y-4">
                <div class="flex items-center gap-4">
                    <h2 class="text-sm font-black uppercase tracking-[0.3em] text-primary shrink-0">{{ ui.specials_for_you || 'Specials For You' }}</h2>
                    <div class="h-px w-full bg-secondary"></div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 lg:gap-4">
                    <div 
                        v-for="promo in filteredPromotions" 
                        :key="promo.packageid"
                        @click="openPromo(promo)"
                        class="group relative bg-primary rounded-[2rem] shadow-xl shadow-primary/20 overflow-hidden cursor-pointer active:scale-[0.98] transition-all aspect-[4/5] flex flex-col"
                    >
                        <!-- Background Pattern / Icon Area -->
                        <div class="relative h-1/2 flex items-center justify-center overflow-hidden bg-white/5">
                            <UtensilsCrossed class="w-20 h-20 text-white/10 rotate-12 absolute -right-4 -bottom-4" />
                            <div class="bg-white/20 p-4 rounded-3xl backdrop-blur-md border border-white/20 z-10">
                                <Tag class="w-8 h-8 text-white" />
                            </div>
                        </div>

                        <div class="p-4 flex flex-col justify-between flex-1 relative z-10">
                            <div>
                                <span class="text-[8px] font-black text-white/60 uppercase tracking-widest block mb-1">{{ ui.promo_package || 'Promo' }}</span>
                                <h3 class="text-sm font-black text-white uppercase tracking-tight leading-tight line-clamp-2">{{ promo.name }}</h3>
                            </div>
                            
                            <div class="flex flex-col items-start gap-1">
                                <span class="text-[8px] font-black text-white/50 uppercase tracking-[0.1em] line-through decoration-white/40">{{ formatPrice(promo.menus.reduce((acc, m) => acc + (m.price || 0), 0) || promo.price * 1.5) }}</span>
                                <span class="text-sm font-black text-white tracking-tighter">{{ formatPrice(promo.price) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <template v-if="filteredCategories.length > 0">
                <section v-for="cat in filteredCategories" :key="cat.id" :id="`category-${cat.id}`" class="space-y-4">
                    <div class="flex items-center gap-4">
                        <h2 class="text-sm font-black uppercase tracking-[0.3em] text-primary shrink-0">{{ cat.name }}</h2>
                        <div class="h-px w-full bg-secondary"></div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 lg:gap-4">
                        <div 
                            v-for="menu in cat.menus" 
                            :key="menu.id"
                            @click="openItemModal(menu)"
                            :class="[
                                'group relative flex flex-col bg-card rounded-[2rem] border shadow-sm transition-all duration-300 overflow-hidden aspect-[3/4] cursor-pointer',
                                menu.is_available ? 'border-secondary/50 hover:shadow-md hover:border-primary/20 active:scale-95' : 'opacity-60 grayscale border-secondary/20 cursor-not-allowed'
                            ]"
                        >
                            <!-- Image -->
                            <div class="relative w-full h-1/2 overflow-hidden bg-secondary">
                                <img 
                                    v-if="menu.image" 
                                    :src="menu.image" 
                                    class="w-full h-full object-cover transition-transform duration-500" 
                                    :class="menu.is_available ? 'group-hover:scale-110' : ''"
                                />
                                <div v-else class="w-full h-full flex items-center justify-center text-muted-foreground/30">
                                    <UtensilsCrossed class="w-8 h-8" />
                                </div>
                                <!-- Sold Out Overlay -->
                                <div v-if="!menu.is_available" class="absolute inset-0 bg-black/40 flex flex-col items-center justify-center">
                                    <span class="text-[8px] font-black text-white uppercase tracking-widest bg-destructive px-2 py-1 rounded-full shadow-lg">{{ ui.sold_out || 'Sold Out' }}</span>
                                </div>
                            </div>

                            <!-- Details -->
                            <div class="p-4 flex flex-col justify-between flex-1 min-w-0">
                                <div class="space-y-1">
                                    <h3 class="text-xs font-black text-foreground uppercase tracking-tight leading-tight line-clamp-2">{{ menu.name }}</h3>
                                    <p class="text-[8px] font-bold text-muted-foreground line-clamp-2 leading-relaxed">
                                        {{ menu.description || ui.no_description || 'No description available.' }}
                                    </p>
                                </div>
                                <div class="flex flex-col gap-1 mt-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-black text-primary tracking-tight">
                                            {{ formatPrice(menu.discounted_price || menu.price) }}
                                        </span>
                                        <span v-if="menu.discounted_price < menu.price" class="text-[8px] font-bold text-muted-foreground line-through decoration-muted-foreground/30">
                                            {{ formatPrice(menu.price) }}
                                        </span>
                                    </div>
                                    <div v-if="menu.applied_promo" class="inline-flex self-start px-1.5 py-0.5 rounded-md bg-primary/10 border border-primary/20">
                                        <span class="text-[7px] font-black text-primary uppercase tracking-widest">{{ menu.applied_promo }}</span>
                                    </div>
                                    <div v-if="!menu.is_available" class="text-[7px] font-black text-destructive uppercase tracking-widest mt-1">
                                        {{ ui.sold_out || 'Sold Out' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </template>

            <!-- Empty State -->
            <div v-else class="flex flex-col items-center justify-center py-20 text-center space-y-4">
                <div class="w-20 h-20 bg-secondary rounded-[2rem] flex items-center justify-center text-muted-foreground/30">
                    <Search class="w-8 h-8" />
                </div>
                <div>
                    <h3 class="text-sm font-black uppercase text-primary">{{ ui.no_menu_found || 'No menus found' }}</h3>
                    <p class="text-[10px] font-bold text-muted-foreground uppercase mt-1">{{ ui.different_keyword || 'Try a different search keyword' }}</p>
                </div>
            </div>
        </main>

        <div v-if="totalItems > 0" class="fixed bottom-8 left-1/2 -translate-x-1/2 w-[calc(100%-3rem)] max-w-md z-50">
            <div class="flex items-center justify-between bg-primary text-primary-foreground p-2 pl-6 rounded-[2rem] shadow-2xl shadow-primary/40 group transition-all duration-300 w-full border border-white/20">
                <!-- Clickable area to open cart details -->
                <div @click="showCartDetails = true" class="flex-1 flex flex-col cursor-pointer active:scale-95 transition-transform py-1">
                    <span class="text-[10px] font-black uppercase tracking-widest opacity-70">{{ ui.items_count ? ui.items_count.replace(':count', totalItems) : `${totalItems} Items` }}</span>
                    <span class="text-sm font-black tracking-tight">{{ formatPrice(totalPrice) }}</span>
                </div>
                
                <!-- Direct link to checkout -->
                <Link 
                    :href="route('public.table.checkout', { barcode: table.barcode })"
                    class="flex items-center gap-3 bg-background/20 py-3 px-6 rounded-full font-black uppercase text-xs tracking-widest hover:bg-background/30 active:scale-95 transition-all"
                >
                    {{ ui.view_cart || 'View Cart' }}
                    <ShoppingBag class="w-4 h-4" />
                </Link>
            </div>
        </div>

        <!-- Cart Bottom Sheet -->
        <transition 
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showCartDetails" @click="showCartDetails = false" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-[55]"></div>
        </transition>

        <transition
            enter-active-class="transition-transform duration-500 ease-out-back"
            enter-from-class="translate-y-full"
            enter-to-class="translate-y-0"
            leave-active-class="transition-transform duration-300 ease-in"
            leave-from-class="translate-y-0"
            leave-to-class="translate-y-full"
        >
            <div v-if="showCartDetails" class="fixed bottom-0 left-0 right-0 bg-background rounded-t-[3rem] shadow-[0_-20px_60px_rgba(0,0,0,0.15)] z-[60] max-h-[85vh] flex flex-col border-t border-secondary/50">
                <!-- Sheet Handle -->
                <div @click="showCartDetails = false" class="w-full flex justify-center py-4 cursor-grab active:cursor-grabbing">
                    <div class="w-12 h-1.5 rounded-full bg-secondary"></div>
                </div>

                <div class="px-6 pb-8 overflow-y-auto no-scrollbar space-y-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-2xl font-black text-primary uppercase tracking-tight">{{ ui.order_summary || 'Order Summary' }}</h2>
                        <span class="px-4 py-1.5 bg-primary/10 text-primary text-xs font-black rounded-full uppercase tracking-widest">{{ totalItems }} Items</span>
                    </div>

                    <div class="space-y-4">
                        <div v-for="(item, index) in items" :key="index" class="bg-secondary/20 p-5 rounded-[2rem] border border-secondary/50 space-y-4">
                            <div class="flex gap-4">
                                <div class="w-20 h-20 rounded-2xl bg-white border border-secondary/50 shrink-0 overflow-hidden shadow-sm">
                                    <img v-if="item.menu.image" :src="item.menu.image" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex items-center justify-center text-muted-foreground/30">
                                        <UtensilsCrossed class="w-8 h-8" />
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0 flex flex-col justify-between py-1">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="text-sm font-black text-foreground truncate uppercase tracking-tight">{{ item.menu.name }}</h3>
                                            <div v-if="item.addons?.length" class="flex flex-wrap gap-1 mt-1">
                                                <span v-for="addon in item.addons" :key="addon.addonid" class="text-[8px] font-black bg-white text-muted-foreground px-2 py-0.5 rounded-md border border-secondary/50 uppercase tracking-widest">+ {{ addon.name }}</span>
                                            </div>
                                        </div>
                                        <button @click="removeFromCart(index)" class="text-destructive p-2 hover:bg-destructive/10 rounded-xl transition-colors">
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>

                                    <div class="flex justify-between items-end mt-2">
                                        <p class="text-sm font-black text-primary tracking-tight">{{ formatPrice(item.totalPrice) }}</p>
                                        
                                        <!-- Mini Quantity Controls -->
                                        <div class="flex items-center gap-4 bg-white rounded-xl p-1 shadow-sm border border-secondary/50">
                                            <button 
                                                @click="updateQuantity(index, item.quantity - 1)"
                                                class="w-8 h-8 flex items-center justify-center rounded-lg bg-secondary/30 hover:text-primary transition-all disabled:opacity-30"
                                                :disabled="item.quantity <= 1"
                                            >
                                                <Minus class="w-3.5 h-3.5" stroke-width="3" />
                                            </button>
                                            <span class="text-xs font-black w-4 text-center">{{ item.quantity }}</span>
                                            <button 
                                                @click="updateQuantity(index, item.quantity + 1)"
                                                class="w-8 h-8 flex items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-md shadow-primary/20"
                                            >
                                                <Plus class="w-3.5 h-3.5" stroke-width="3" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-secondary/50 space-y-6">
                        <div class="flex items-center justify-between px-2">
                            <div class="flex flex-col">
                                <span class="text-[10px] font-black uppercase tracking-widest text-muted-foreground">{{ ui.estimated_total || 'Estimated Total' }}</span>
                                <span class="text-xs font-bold text-muted-foreground/60 leading-none mt-1">Excl. Tax & Service</span>
                            </div>
                            <span class="text-3xl font-black text-primary tracking-tighter">{{ formatPrice(totalPrice) }}</span>
                        </div>
                        
                        <Link 
                            :href="route('public.table.checkout', { barcode: table.barcode })"
                            class="w-full h-16 bg-primary text-primary-foreground rounded-2xl flex items-center justify-center text-sm font-black uppercase tracking-[0.2em] shadow-xl shadow-primary/20 gap-4 hover:scale-[1.02] active:scale-95 transition-all"
                        >
                            {{ ui.process_checkout || 'Process Checkout' }}
                            <div class="bg-white/20 p-1.5 rounded-lg">
                                <ChevronLeft class="w-4 h-4 rotate-180" stroke-width="4" />
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Promo Detail Modal -->
        <Modal :show="showPromoModal" :title="ui.promo_details || 'Promo Details'" @close="showPromoModal = false">
            <div class="p-6 space-y-6">
                <div class="text-center space-y-2">
                    <div class="inline-flex p-4 rounded-[2rem] bg-primary/10 text-primary mb-2">
                        <Tag class="w-10 h-10" />
                    </div>
                    <h2 class="text-2xl font-black text-primary uppercase tracking-tight">{{ selectedPromo?.name }}</h2>
                    <p class="text-xs font-bold text-muted-foreground uppercase tracking-[0.2em]">{{ ui.special_package_offer || 'Special Package Offer' }}</p>
                </div>

                <div class="space-y-4">
                    <h3 class="text-[10px] font-black uppercase tracking-[0.3em] text-muted-foreground ml-1">{{ ui.package_includes || 'Package Includes' }}</h3>
                    
                    <div class="grid gap-3">
                        <div v-for="menu in selectedPromo?.menus" :key="menu.id" class="flex items-center gap-4 bg-secondary/30 p-4 rounded-3xl border border-secondary/50">
                            <div class="w-10 h-10 rounded-xl bg-background flex items-center justify-center text-primary font-black text-xs border border-secondary shadow-sm">
                                {{ menu.qty }}x
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-black text-foreground uppercase tracking-tight">{{ menu.name }}</p>
                                <div v-if="menu.addons?.length" class="flex flex-wrap gap-x-2 gap-y-0.5 mt-0.5">
                                    <span v-for="addon in menu.addons" :key="addon.addonid" class="text-[9px] font-black uppercase text-primary tracking-widest">+ {{ addon.name }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-for="freebie in selectedPromo?.freebies" :key="freebie.id" class="flex items-center gap-4 bg-green-50 p-4 rounded-3xl border border-green-100">
                            <div class="w-10 h-10 rounded-xl bg-green-500 flex items-center justify-center text-white font-black text-xs shadow-md shadow-green-500/20">
                                {{ freebie.qty }}x
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-black text-green-700 uppercase tracking-tight">{{ freebie.name }}</p>
                                <p class="text-[9px] font-black text-green-600/60 uppercase tracking-[0.2em]">{{ ui.bonus_freebie || 'Bonus Freebie' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-secondary/50 flex flex-col gap-4">
                    <div class="flex items-center justify-between px-2">
                        <span class="text-xs font-black uppercase tracking-widest text-muted-foreground">{{ ui.total_price || 'Total Price' }}</span>
                        <span class="text-2xl font-black text-primary tracking-tighter">{{ formatPrice(selectedPromo?.price) }}</span>
                    </div>
                    
                    <Button @click="addPromoToCart" class="w-full h-14 rounded-2xl text-xs font-black uppercase tracking-[0.2em] shadow-xl shadow-primary/20 gap-3">
                        <ShoppingBag class="w-5 h-5" />
                        {{ ui.add_to_cart || 'Add to Cart' }}
                    </Button>
                </div>
            </div>
        </Modal>

        <!-- Item Detail Modal -->
        <Modal :show="showItemModal" :title="ui.item_details || 'Item Details'" @close="showItemModal = false">
            <div class="p-6 space-y-8">
                <!-- Hero Section in Modal -->
                <div class="flex flex-col sm:flex-row gap-6">
                    <div class="relative w-full sm:w-48 aspect-square shrink-0 overflow-hidden rounded-[2.5rem] bg-secondary shadow-xl shadow-primary/10 border-4 border-white">
                        <img 
                            v-if="selectedItem?.image" 
                            :src="selectedItem?.image" 
                            class="w-full h-full object-cover" 
                        />
                        <div v-else class="w-full h-full flex items-center justify-center text-muted-foreground/20">
                            <UtensilsCrossed class="w-16 h-16" />
                        </div>
                    </div>

                    <div class="flex-1 space-y-3 py-1">
                        <div class="space-y-1">
                            <h2 class="text-2xl font-black text-primary uppercase tracking-tight leading-tight">{{ selectedItem?.name }}</h2>
                            <div class="flex items-baseline gap-3">
                                <span class="text-xl font-black text-primary tracking-tighter">{{ formatPrice(selectedItem?.discounted_price || selectedItem?.price) }}</span>
                                <span v-if="selectedItem?.discounted_price < selectedItem?.price" class="text-xs font-bold text-muted-foreground line-through decoration-muted-foreground/30">
                                    {{ formatPrice(selectedItem?.price) }}
                                </span>
                            </div>
                            <div v-if="selectedItem?.applied_promo" class="inline-flex px-2 py-0.5 rounded-lg bg-primary/10 border border-primary/20 mt-1">
                                <span class="text-[8px] font-black text-primary uppercase tracking-widest">{{ selectedItem?.applied_promo }}</span>
                            </div>
                        </div>
                        <div class="h-1 w-10 bg-primary/20 rounded-full"></div>
                        <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest leading-relaxed">
                            {{ selectedItem?.description || ui.no_description || 'No description available for this item.' }}
                        </p>
                    </div>
                </div>

                <!-- Addons Section -->
                <div v-if="selectedItem?.addons?.length > 0" class="space-y-4">
                    <div class="flex items-center gap-4">
                        <h2 class="text-[10px] lg:text-xs font-black uppercase tracking-[0.3em] text-primary shrink-0">{{ ui.add_ons || 'Add-ons' }}</h2>
                        <div class="h-px w-full bg-secondary"></div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <button 
                            v-for="addon in selectedItem.addons" 
                            :key="addon.addonid"
                            @click="addon.is_available ? toggleItemAddon(addon) : null"
                            :class="[
                                'flex items-center justify-between p-4 rounded-2xl border transition-all duration-300',
                                isItemAddonSelected(addon.addonid)
                                    ? 'bg-primary/5 border-primary shadow-sm'
                                    : (addon.is_available ? 'bg-card border-secondary hover:border-primary/20' : 'bg-secondary/30 border-secondary opacity-50 grayscale cursor-not-allowed')
                            ]"
                        >
                            <div class="flex items-center gap-3">
                                <div 
                                    :class="[
                                        'w-4 h-4 rounded-md border flex items-center justify-center transition-colors',
                                        isItemAddonSelected(addon.addonid) ? 'bg-primary border-primary text-white' : 'border-secondary'
                                    ]"
                                >
                                    <CheckCircle2 v-if="isItemAddonSelected(addon.addonid)" class="w-2.5 h-2.5" />
                                </div>
                                <div class="flex flex-col">
                                    <span :class="['text-[10px] font-black uppercase tracking-tight', isItemAddonSelected(addon.addonid) ? 'text-primary' : 'text-foreground']">
                                        {{ addon.name }}
                                    </span>
                                    <span v-if="!addon.is_available" class="text-[7px] font-black text-destructive uppercase tracking-widest">
                                        {{ ui.sold_out || 'Sold Out' }}
                                    </span>
                                </div>
                            </div>
                            <span class="text-[10px] font-bold text-muted-foreground">+{{ formatPrice(addon.price) }}</span>
                        </button>
                    </div>
                </div>

                <!-- Quantity Selection -->
                <div class="space-y-4">
                    <div class="flex items-center gap-4">
                        <h2 class="text-[10px] lg:text-xs font-black uppercase tracking-[0.3em] text-primary shrink-0">{{ ui.quantity || 'Quantity' }}</h2>
                        <div class="h-px w-full bg-secondary"></div>
                    </div>

                    <div class="flex items-center justify-center gap-8 bg-secondary/30 p-4 rounded-[2.5rem]">
                        <button 
                            @click="itemQuantity > 1 ? itemQuantity-- : null"
                            class="p-4 rounded-2xl bg-white text-primary border-2 border-secondary shadow-sm hover:scale-110 active:scale-95 transition-all"
                            :disabled="itemQuantity <= 1"
                        >
                            <Minus class="w-5 h-5" stroke-width="3" />
                        </button>
                        
                        <div class="text-center min-w-[3rem]">
                            <span class="text-4xl font-black text-primary tracking-tighter">{{ itemQuantity }}</span>
                            <p class="text-[8px] font-black uppercase tracking-widest text-muted-foreground">{{ ui.portions || 'Portions' }}</p>
                        </div>

                        <button 
                            @click="itemQuantity < 50 ? itemQuantity++ : null"
                            class="p-4 rounded-2xl bg-primary text-primary-foreground shadow-xl shadow-primary/20 hover:scale-110 active:scale-95 transition-all"
                            :disabled="itemQuantity >= 50"
                        >
                            <Plus class="w-5 h-5" stroke-width="3" />
                        </button>
                    </div>
                </div>

                <!-- Footer Summary & Add Button -->
                <div class="pt-6 border-t border-secondary/50 flex flex-col gap-4">
                    <div class="flex items-center justify-between px-2">
                        <span class="text-xs font-black uppercase tracking-widest text-muted-foreground">{{ ui.total_price || 'Total Price' }}</span>
                        <span class="text-2xl font-black text-primary tracking-tighter">{{ formatPrice(itemTotalPrice) }}</span>
                    </div>
                    
                    <Button @click="addItemToCartFromModal" class="w-full h-14 rounded-2xl text-xs font-black uppercase tracking-[0.2em] shadow-xl shadow-primary/20 gap-3">
                        <ShoppingBag class="w-5 h-5" />
                        {{ ui.add_to_cart || 'Add to Cart' }}
                    </Button>
                </div>
            </div>
        </Modal>
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
