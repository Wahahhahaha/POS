<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { 
    ChevronLeft, 
    CreditCard, 
    Wallet, 
    ShoppingCart,
    CheckCircle2,
    UtensilsCrossed,
    Minus,
    Plus,
    Trash2,
    Info,
    Mic,
    MicOff,
    MessageSquareText
} from '@lucide/vue'
import { useCart } from '@/Composables/useCart'
import LanguageToggle from '@/Components/shared/LanguageToggle.vue'
import Modal from '@/Components/ui/Modal.vue'
import Button from '@/Components/ui/Button.vue'

const page = usePage()
const props = defineProps({
    table: Object,
    promotions: Array,
    error: String,
})

const ui = computed(() => page.props.translations?.ui || {})

const { items, totalItems, totalPrice: rawTotalPrice, updateQuantity, removeFromCart, clearCart } = useCart()
const selectedPayment = ref(null)
const showPromoInfo = ref(false)
const orderNote = ref('')
const isListening = ref(false)
const listeningField = ref(null) // 'order' or item index

const toggleListening = (field = 'order') => {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition
    if (!SpeechRecognition) {
        alert('Voice recognition not supported in this browser.')
        return
    }

    const recognition = new SpeechRecognition()
    // Strictly set language based on current toggle
    const currentLocale = page.props.locale === 'id' ? 'id-ID' : 'en-US'
    recognition.lang = currentLocale
    recognition.interimResults = false
    recognition.maxAlternatives = 1

    console.log(`Speech recognition started in strict mode: ${currentLocale}`)

    if (!isListening.value || listeningField.value !== field) {
        // If already listening but for a different field, stop first
        if (isListening.value) recognition.stop()
        
        recognition.start()
        isListening.value = true
        listeningField.value = field
        
        recognition.onresult = (event) => {
            const transcript = event.results[0][0].transcript
            if (field === 'order') {
                orderNote.value = (orderNote.value ? orderNote.value + ' ' : '') + transcript
            } else if (typeof field === 'number') {
                const currentItem = items.value[field]
                currentItem.note = (currentItem.note ? currentItem.note + ' ' : '') + transcript
            }
        }

        recognition.onend = () => {
            isListening.value = false
            listeningField.value = null
        }

        recognition.onerror = (event) => {
            console.error('Speech recognition error', event.error)
            isListening.value = false
            listeningField.value = null
        }
    } else {
        recognition.stop()
        isListening.value = false
        listeningField.value = null
    }
}

const subtotal = computed(() => items.value.reduce((acc, item) => acc + item.totalPrice, 0))
const tax = computed(() => subtotal.value * 0.10)

const activeDiscount = computed(() => {
    if (!props.promotions || props.promotions.length === 0) return null
    
    // 1. Identify all applicable promos
    const applicable = props.promotions.filter(promo => {
        if (promo.min_purchase > 0 && subtotal.value < promo.min_purchase) return false
        
        if (promo.type === 'BUY_X_GET_Y') {
            const cartItem = items.value.find(i => i.menu.id === promo.buy_menuid)
            return cartItem && cartItem.quantity >= promo.buy_qty
        }
        return true
    })

    if (applicable.length === 0) return null

    // 2. Calculate values and find the BEST one (No stacking)
    let bestPromo = null
    let maxAmount = -1

    applicable.forEach(promo => {
        let currentAmount = 0
        if (promo.type === 'DISCOUNT_PERCENT') {
            currentAmount = subtotal.value * (promo.discount_value / 100)
        } else if (promo.type === 'DISCOUNT_FIXED') {
            currentAmount = promo.discount_value
        } else if (promo.type === 'BUY_X_GET_Y') {
            currentAmount = 0.1 // Just to differentiate from no discount
        }

        if (currentAmount > maxAmount) {
            maxAmount = currentAmount
            bestPromo = promo
        }
    })

    if (!bestPromo) return null

    const promo = bestPromo
    let finalAmount = 0

    if (promo.type === 'DISCOUNT_PERCENT') {
        finalAmount = subtotal.value * (promo.discount_value / 100)
    } else if (promo.type === 'DISCOUNT_FIXED') {
        finalAmount = promo.discount_value
    } else if (promo.type === 'BUY_X_GET_Y') {
        return {
            id: promo.promotionid,
            name: promo.name,
            type: promo.type,
            amount: 0,
            text: `${ui.value.qualified_for || 'Qualified for'}: ${ui.value.free || 'Free'} ${promo.get_menu?.name}`,
            get_menu: promo.get_menu,
            get_qty: promo.get_qty
        }
    }

    return {
        id: promo.promotionid,
        name: promo.name,
        type: promo.type,
        amount: finalAmount,
        text: `${ui.value.discount_applied || 'Discount applied'}: ${promo.name}`
    }
})

const totalPrice = computed(() => (subtotal.value - (activeDiscount.value?.amount || 0)) + tax.value)

const paymentMethods = computed(() => [
    { id: 'QRIS', name: ui.value.digital_pay || 'QRIS / Digital Pay', icon: CreditCard, description: ui.value.pay_instantly || 'Pay instantly with any banking app' },
    { id: 'Cashier Payment', name: ui.value.pay_at_cashier || 'Pay at Cashier', icon: Wallet, description: ui.value.pay_with_cash_card || 'Pay with cash or card at the counter' },
])

const formatPrice = (price) => {
    return new Intl.NumberFormat(page.props.locale === 'id' ? 'id-ID' : 'en-US', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(price)
}

const confirmOrder = () => {
    if (!selectedPayment.value) {
        alert(ui.value.select_payment_method || 'Please select a payment method')
        return
    }
    
    router.post(route('public.table.processCheckout', { barcode: props.table.barcode }), {
        items: items.value,
        paymentMethod: selectedPayment.value,
        totalPrice: totalPrice.value,
        note: orderNote.value,
        appliedPromotionId: activeDiscount.value?.id,
        appliedPromotionName: activeDiscount.value?.name,
        appliedPromotionDiscount: activeDiscount.value?.amount || 0,
        freeMenuItemId: activeDiscount.value?.type === 'BUY_X_GET_Y' ? activeDiscount.value.get_menu?.menuid : null,
        freeMenuItemQty: activeDiscount.value?.type === 'BUY_X_GET_Y' ? activeDiscount.value.get_qty : 0
    }, {
        onSuccess: (page) => {
            // Only clear cart if there's no error in flash
            if (!page.props.flash?.error && Object.keys(page.props.errors).length === 0) {
                clearCart()
            }
        }
    })
}
</script>

<template>
    <Head :title="ui.review_order || 'Review Order'" />

    <div class="min-h-screen bg-background pb-[480px]">
        <!-- Header Nav -->
        <div class="p-6 flex items-center gap-4 bg-white/80 backdrop-blur-md sticky top-0 z-30 border-b border-secondary/50">
            <Link 
                :href="route('public.table.menu', { barcode: table.barcode })"
                class="p-3 rounded-2xl bg-secondary text-primary hover:bg-primary/10 transition-all"
            >
                <ChevronLeft class="w-6 h-6" />
            </Link>
            <div class="min-w-0 text-center flex-1 pr-4">
                <h1 class="text-xs font-black uppercase tracking-[0.2em] text-primary truncate">{{ ui.review_order || 'Review Order' }}</h1>
                <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest">{{ ui.table || 'Table' }} {{ table.name }}</p>
            </div>
            <LanguageToggle />
        </div>

        <main class="p-6 space-y-10">
            <!-- Errors -->
            <div v-if="error || $page.props.flash?.error || Object.keys($page.props.errors).length > 0" class="bg-destructive/10 text-destructive p-4 rounded-2xl border border-destructive/20 text-xs font-bold space-y-1">
                <p v-if="error">{{ error }}</p>
                <p v-if="$page.props.flash?.error">{{ $page.props.flash.error }}</p>
                <p v-for="(err, key) in $page.props.errors" :key="key">{{ err }}</p>
            </div>

            <!-- Order Items -->
            <section class="space-y-4">
                <div class="flex items-center gap-4">
                    <h2 class="text-xs font-black uppercase tracking-[0.3em] text-primary shrink-0">{{ ui.your_order || 'Your Order' }}</h2>
                    <div class="h-px w-full bg-secondary"></div>
                </div>

                <div class="grid gap-4">
                    <div 
                        v-for="(item, index) in items" 
                        :key="index"
                        class="flex flex-col bg-card p-5 rounded-[2rem] border border-secondary/50 shadow-sm space-y-4"
                    >
                        <div class="flex gap-4">
                            <div class="w-20 h-20 rounded-2xl bg-secondary shrink-0 overflow-hidden">
                                <img v-if="item.menu.image" :src="item.menu.image" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-muted-foreground/30">
                                    <UtensilsCrossed class="w-8 h-8" />
                                </div>
                            </div>
                            <div class="flex-1 min-w-0 py-1 flex flex-col justify-between">
                                <div class="flex justify-between items-start">
                                    <div class="flex flex-col">
                                        <h3 class="text-sm font-black text-foreground truncate uppercase tracking-tight">{{ item.menu.name }}</h3>
                                        <span v-if="item.menu.is_package" class="text-[8px] font-black bg-primary/10 text-primary px-2 py-0.5 rounded w-fit uppercase tracking-widest mt-0.5 border border-primary/20">{{ ui.promo_package || 'Promo Package' }}</span>
                                    </div>
                                    <button @click="removeFromCart(index)" class="text-destructive hover:bg-destructive/10 p-1.5 rounded-lg transition-colors">
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                                
                                <!-- Package Contents -->
                                <div v-if="item.menu.is_package" class="space-y-1.5 mt-2">
                                    <div v-for="pMenu in item.menu.menus" :key="pMenu.id" class="flex flex-col">
                                        <span class="text-[10px] font-bold text-foreground/80 uppercase tracking-tight leading-none">• {{ pMenu.qty }}x {{ pMenu.name }}</span>
                                        <span v-if="pMenu.addonname" class="text-[8px] font-bold text-primary uppercase tracking-widest ml-3">+ {{ pMenu.addonname }}</span>
                                    </div>
                                    <div v-for="freebie in item.menu.freebies" :key="freebie.id" class="flex items-center gap-1.5 ml-0.5">
                                        <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div>
                                        <span class="text-[9px] font-black uppercase text-green-600 tracking-widest">Free: {{ freebie.qty }}x {{ freebie.name }}</span>
                                    </div>
                                </div>

                                <!-- Standard Addons -->
                                <div v-if="!item.menu.is_package && item.addons?.length" class="flex flex-wrap gap-1 mt-1">
                                    <span v-for="addon in item.addons" :key="addon.addonid" class="text-[8px] font-black bg-secondary text-muted-foreground px-2 py-0.5 rounded-md uppercase tracking-widest border border-secondary/50">
                                        + {{ addon.name }}
                                    </span>
                                </div>

                                <div class="flex justify-between items-end mt-2">
                                    <p class="text-sm font-black text-primary tracking-tight">{{ formatPrice(item.totalPrice) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Item Note Input -->
                        <div class="pt-4 border-t border-secondary/30 space-y-2">
                            <div class="flex items-center justify-between px-1">
                                <label class="text-[9px] font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center gap-1.5">
                                    <MessageSquareText class="w-3 h-3" /> {{ ui.special_instructions || 'Special Instructions' }}
                                </label>
                                <button 
                                    @click="toggleListening(index)"
                                    :class="['p-1.5 rounded-lg transition-all', isListening && listeningField === index ? 'bg-primary text-primary-foreground animate-pulse' : 'bg-secondary text-primary hover:bg-primary/10']"
                                >
                                    <Mic v-if="!(isListening && listeningField === index)" class="w-3.5 h-3.5" />
                                    <MicOff v-else class="w-3.5 h-3.5" />
                                </button>
                            </div>
                            <textarea 
                                v-model="item.note"
                                :placeholder="ui.eg_extra_spicy || 'e.g. Extra spicy, no onions...'"
                                class="w-full bg-secondary/30 border-transparent rounded-xl p-3 text-[11px] font-bold focus:ring-1 focus:ring-primary focus:bg-background transition-all outline-none min-h-[60px] resize-none"
                            ></textarea>
                        </div>

                        <!-- Quantity Controls -->
                        <div class="flex items-center justify-between pt-4 border-t border-secondary/30">
                            <span class="text-[10px] font-black uppercase tracking-widest text-muted-foreground">{{ ui.adjust_quantity || 'Adjust Quantity' }}</span>
                            <div class="flex items-center gap-4 bg-secondary/30 rounded-xl p-1 border border-secondary/50">
                                <button 
                                    @click="updateQuantity(index, item.quantity - 1)"
                                    class="p-2 rounded-lg bg-background border border-secondary hover:text-primary transition-all disabled:opacity-30"
                                    :disabled="item.quantity <= 1"
                                >
                                    <Minus class="w-3.5 h-3.5" stroke-width="3" />
                                </button>
                                <span class="text-xs font-black w-4 text-center">{{ item.quantity }}</span>
                                <button 
                                    @click="updateQuantity(index, item.quantity + 1)"
                                    class="p-2 rounded-lg bg-primary text-primary-foreground hover:scale-105 transition-all shadow-md shadow-primary/20"
                                >
                                    <Plus class="w-3.5 h-3.5" stroke-width="3" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Buy X Get Y Free Item Display -->
                    <div 
                        v-if="activeDiscount?.type === 'BUY_X_GET_Y' && activeDiscount.get_menu"
                        class="flex flex-col bg-green-50/50 p-5 rounded-[2rem] border border-green-100 shadow-sm space-y-4 animate-in slide-in-from-right-4 duration-500"
                    >
                        <div class="flex gap-4">
                            <div class="w-20 h-20 rounded-2xl bg-white border border-green-100 shrink-0 overflow-hidden flex items-center justify-center relative">
                                <UtensilsCrossed class="w-8 h-8 text-green-200" />
                                <div class="absolute inset-0 bg-green-500/5 flex items-center justify-center">
                                    <CheckCircle2 class="w-10 h-10 text-green-500/20" />
                                </div>
                            </div>
                            <div class="flex-1 min-w-0 py-1 flex flex-col justify-between">
                                <div class="flex justify-between items-start">
                                    <div class="flex flex-col">
                                        <h3 class="text-sm font-black text-green-800 truncate uppercase tracking-tight">{{ activeDiscount.get_menu.name }}</h3>
                                        <span class="text-[8px] font-black bg-green-500 text-white px-2 py-0.5 rounded w-fit uppercase tracking-widest mt-0.5 shadow-sm shadow-green-500/20">{{ ui.free_item || 'FREE ITEM' }}</span>
                                    </div>
                                    <div class="p-1.5 rounded-lg bg-green-100 text-green-600">
                                        <CheckCircle2 class="w-4 h-4" />
                                    </div>
                                </div>
                                <div class="flex justify-between items-end mt-2">
                                    <div class="flex flex-col">
                                        <p class="text-[8px] font-black text-green-600/60 uppercase tracking-widest">{{ ui.promotion_bonus || 'Promotion Bonus' }}</p>
                                        <p class="text-[10px] font-black text-green-600 uppercase tracking-tight">{{ ui.quantity || 'Quantity' }}: {{ activeDiscount.get_qty }}x</p>
                                    </div>
                                    <p class="text-sm font-black text-green-600 tracking-tight">Rp 0</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- General Order Note -->
            <section class="space-y-4">
                <div class="flex items-center gap-4">
                    <h2 class="text-xs font-black uppercase tracking-[0.3em] text-primary shrink-0">{{ ui.additional_notes || 'Additional Notes' }}</h2>
                    <div class="h-px w-full bg-secondary"></div>
                </div>

                <div class="bg-card p-5 rounded-[2rem] border border-secondary/50 shadow-sm space-y-3">
                    <div class="flex items-center justify-between px-1">
                        <p class="text-[9px] font-black uppercase tracking-[0.2em] text-muted-foreground">{{ ui.general_requests || 'General Requests' }}</p>
                        <button 
                            @click="toggleListening('order')"
                            :class="['p-1.5 rounded-lg transition-all', isListening && listeningField === 'order' ? 'bg-primary text-primary-foreground animate-pulse' : 'bg-secondary text-primary hover:bg-primary/10']"
                        >
                            <Mic v-if="!(isListening && listeningField === 'order')" class="w-3.5 h-3.5" />
                            <MicOff v-else class="w-3.5 h-3.5" />
                        </button>
                    </div>
                    <textarea 
                        v-model="orderNote"
                        :placeholder="ui.eg_all_together || 'e.g. Please serve all items together...'"
                        class="w-full bg-secondary/30 border-transparent rounded-2xl p-4 text-xs font-bold focus:ring-1 focus:ring-primary focus:bg-background transition-all outline-none min-h-[100px] resize-none"
                    ></textarea>
                </div>
            </section>

            <!-- Payment Methods -->
            <section class="space-y-4">
                <div class="flex items-center gap-4">
                    <h2 class="text-xs font-black uppercase tracking-[0.3em] text-primary shrink-0">{{ ui.payment_method || 'Payment Method' }}</h2>
                    <div class="h-px w-full bg-secondary"></div>
                </div>

                <div class="grid gap-3">
                    <button 
                        v-for="method in paymentMethods" 
                        :key="method.id"
                        @click="selectedPayment = method.id"
                        :class="[
                            'flex items-start gap-4 p-5 rounded-3xl border transition-all duration-300 text-left',
                            selectedPayment === method.id
                                ? 'bg-primary/5 border-primary shadow-md ring-1 ring-primary/20'
                                : 'bg-card border-secondary hover:border-primary/20 shadow-sm'
                        ]"
                    >
                        <div :class="['p-3 rounded-2xl transition-colors', selectedPayment === method.id ? 'bg-primary text-primary-foreground' : 'bg-secondary text-primary']">
                            <component :is="method.icon" class="w-6 h-6" />
                        </div>
                        <div class="flex-1 space-y-1 py-1">
                            <div class="flex justify-between items-center">
                                <h3 :class="['text-xs font-black uppercase tracking-widest', selectedPayment === method.id ? 'text-primary' : 'text-foreground']">
                                    {{ method.name }}
                                </h3>
                                <CheckCircle2 v-if="selectedPayment === method.id" class="w-5 h-5 text-primary" />
                            </div>
                            <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest leading-relaxed">
                                {{ method.description }}
                            </p>
                        </div>
                    </button>
                </div>
            </section>
        </main>

        <!-- Fixed Footer Action -->
        <div class="fixed bottom-0 left-0 right-0 p-6 z-40 bg-white/60 backdrop-blur-xl border-t border-secondary/50 shadow-[0_-10px_40px_rgba(0,0,0,0.05)]">
            <div class="max-w-md mx-auto space-y-4">
                <div class="px-2 space-y-2">
                    <div class="flex items-center justify-between opacity-60">
                        <p class="text-[9px] font-black uppercase tracking-[0.3em] text-muted-foreground">{{ ui.subtotal || 'Subtotal' }}</p>
                        <p class="text-xs font-black text-muted-foreground">{{ formatPrice(subtotal) }}</p>
                    </div>
                    <div class="flex items-center justify-between opacity-60">
                        <p class="text-[9px] font-black uppercase tracking-[0.3em] text-muted-foreground">{{ ui.tax || 'Tax (10%)' }}</p>
                        <p class="text-xs font-black text-muted-foreground">{{ formatPrice(tax) }}</p>
                    </div>
                    <div class="flex items-center justify-between pt-1">
                        <p class="text-[10px] font-black uppercase tracking-[0.3em] text-muted-foreground">{{ ui.total_payment || 'Total Payment' }}</p>
                        <div class="flex items-baseline gap-2">
                            <p class="text-xl font-black text-primary tracking-tighter">{{ formatPrice(totalPrice) }}</p>
                            <p v-if="activeDiscount?.amount > 0" class="text-[10px] font-bold opacity-30 line-through decoration-primary/50">{{ formatPrice(subtotal + tax) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Promo Applied Notification -->
                <transition enter-active-class="animate-in slide-in-from-bottom-4 duration-300" leave-active-class="animate-out fade-out duration-200">
                    <div v-if="activeDiscount" class="bg-white/90 backdrop-blur-md border border-primary/20 px-4 py-2 rounded-2xl shadow-lg flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <div class="w-5 h-5 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-[10px] font-black">!</div>
                            <p class="text-[10px] font-black uppercase tracking-tight text-primary">{{ activeDiscount.text }}</p>
                        </div>
                        <button @click="showPromoInfo = true" class="text-[8px] font-black uppercase text-muted-foreground underline decoration-dotted">{{ ui.details || 'Details' }}</button>
                    </div>
                </transition>
                
                <button 
                    @click="confirmOrder"
                    :disabled="!selectedPayment"
                    class="w-full flex items-center justify-between bg-primary p-2 rounded-2xl shadow-xl shadow-primary/20 hover:scale-[1.02] active:scale-[0.98] transition-all group disabled:opacity-50 disabled:grayscale disabled:hover:scale-100"
                >
                    <div class="flex items-center gap-4 pl-4">
                        <div class="bg-primary-foreground/10 p-2 rounded-xl text-primary-foreground group-hover:rotate-12 transition-transform">
                            <ShoppingCart class="w-5 h-5" />
                        </div>
                        <div class="text-left">
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-foreground/60 leading-none mb-1">{{ ui.confirm || 'Confirm' }}</p>
                            <p class="text-sm font-black text-primary-foreground uppercase tracking-wider leading-none">{{ ui.confirm_place_order || 'Place Order' }}</p>
                        </div>
                    </div>
                    <div class="bg-white/10 px-6 py-3 rounded-xl backdrop-blur-md">
                        <span class="text-xs font-black text-primary-foreground uppercase tracking-widest">
                            {{ ui.ready || 'Ready' }}
                        </span>
                    </div>
                </button>
            </div>
        </div>

        <!-- Promo Info Modal (Brief) -->
        <Modal :show="showPromoInfo" :title="ui.promotion_applied || 'Promotion Applied'" @close="showPromoInfo = false">
            <div class="p-6 text-center space-y-4">
                <div class="inline-flex p-4 rounded-full bg-primary/10 text-primary">
                    <CheckCircle2 class="w-8 h-8" />
                </div>
                <div>
                    <h3 class="text-lg font-black uppercase text-primary tracking-tight">{{ activeDiscount?.name }}</h3>
                    <p class="text-xs font-bold text-muted-foreground uppercase tracking-widest mt-1">{{ ui.status || 'Status' }}: {{ ui.qualified || 'Qualified' }}</p>
                </div>
                <div class="bg-secondary/30 p-4 rounded-2xl border border-secondary text-sm font-bold text-foreground leading-relaxed">
                    <template v-if="activeDiscount?.type === 'BUY_X_GET_Y'">
                        {{ ui.buy_x_get_y_msg || "Awesome! You've added the required items for this deal. You'll receive your free item when this order is processed." }}
                    </template>
                    <template v-else-if="activeDiscount?.amount > 0">
                        {{ (ui.discount_applied_msg || "Nice! Your order total reached the minimum spend, so we've applied a :amount discount to your cart!").replace(':amount', formatPrice(activeDiscount.amount)) }}
                    </template>
                </div>
                <Button @click="showPromoInfo = false" class="w-full h-12 rounded-xl font-black uppercase tracking-widest text-xs">{{ ui.got_it || 'Got it!' }}</Button>
            </div>
        </Modal>
    </div>
</template>