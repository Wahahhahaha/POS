<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, router, usePage, usePoll } from '@inertiajs/vue3'
import { 
    ChefHat, 
    CheckCircle2, 
    Clock, 
    ListTodo,
    BellRing,
    MessageSquareText,
    Volume2
} from '@lucide/vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const page = usePage()
const props = defineProps({
    pendingOrders: Array,
    completedOrders: Array,
    totalPending: Number,
    currentLimit: Number,
    branches: Array,
    filters: Object,
    errors: Object,
    auth: Object,
    ziggy: Object,
    system: Object,
    locale: String,
    translations: Object,
})

const ui = computed(() => page.props.translations?.ui || {})
const branchFilter = ref(props.filters?.branch || '')

// Polling every 5 seconds to keep the queue fresh
usePoll(5000, {
    only: ['pendingOrders', 'completedOrders', 'totalPending'],
    data: { 
        limit: props.currentLimit,
        branch: branchFilter.value
    },
})

const updateBranch = () => {
    router.get(route('kitchen.index'), { 
        branch: branchFilter.value,
        limit: props.currentLimit 
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true
    })
}

const loadMore = () => {
    router.get(route('kitchen.index'), { 
        limit: props.currentLimit + 10,
        branch: branchFilter.value 
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['pendingOrders', 'totalPending', 'currentLimit']
    })
}

const markAsReady = (orderId) => {
    router.post(route('kitchen.complete', orderId), {}, {
        preserveScroll: true
    })
}

const speakOrder = (order) => {
    const synth = window.speechSynthesis
    if (!synth) {
        alert('Text-to-speech not supported in this browser.')
        return
    }

    // Stop any current speaking
    synth.cancel()

    const currentLocale = page.props.locale === 'id' ? 'id-ID' : 'en-US'
    
    // Construct the message
    let text = ''
    if (page.props.locale === 'id') {
        text = `Pesanan baru untuk meja ${order.tableName}. `
        order.items.forEach(item => {
            text += `${item.quantity} porsi ${item.name}. `
            if (item.note) text += `Catatan item: ${item.note}. `
        })
        if (order.note) text += `Catatan pesanan: ${order.note}.`
    } else {
        text = `New order for table ${order.tableName}. `
        order.items.forEach(item => {
            text += `${item.quantity} quantity of ${item.name}. `
            if (item.note) text += `Item note: ${item.note}. `
        })
        if (order.note) text += `Order note: ${order.note}.`
    }

    const utterance = new SpeechSynthesisUtterance(text)
    utterance.lang = currentLocale
    utterance.rate = 0.9
    utterance.pitch = 1
    
    synth.speak(utterance)
}

const activeMobileTab = ref('cooking') // 'cooking' or 'history'

// Helper to format price if needed (though not typically in kitchen)
const formatPrice = (price) => {
    return new Intl.NumberFormat(page.props.locale === 'id' ? 'id-ID' : 'en-US', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(price)
}
</script>

<template>
    <AppLayout>
        <Head :title="ui.kitchen_dashboard || 'Kitchen Dashboard'" />

        <div class="flex flex-col h-full -mx-4 -mt-4 lg:mx-0 lg:mt-0">
            <!-- MOBILE TAB SWITCHER (Only visible on small screens) -->
            <div class="lg:hidden flex p-2 gap-2 bg-background sticky top-0 z-30 border-b border-secondary/50">
                <button 
                    @click="activeMobileTab = 'cooking'"
                    :class="[
                        'flex-1 flex items-center justify-center gap-2 py-3.5 rounded-2xl text-xs font-black uppercase tracking-widest transition-all',
                        activeMobileTab === 'cooking' ? 'bg-primary text-primary-foreground shadow-lg shadow-primary/20' : 'bg-card text-muted-foreground border border-secondary/50'
                    ]"
                >
                    <ChefHat class="w-4 h-4" />
                    {{ ui.cooking || 'Cooking' }}
                    <span v-if="pendingOrders.length > 0" class="flex h-2 w-2 rounded-full bg-destructive animate-pulse"></span>
                </button>
                <button 
                    @click="activeMobileTab = 'history'"
                    :class="[
                        'flex-1 flex items-center justify-center gap-2 py-3.5 rounded-2xl text-xs font-black uppercase tracking-widest transition-all',
                        activeMobileTab === 'history' ? 'bg-primary text-primary-foreground shadow-lg shadow-primary/20' : 'bg-card text-muted-foreground border border-secondary/50'
                    ]"
                >
                    <ListTodo class="w-4 h-4" />
                    {{ ui.history || 'History' }}
                </button>
            </div>

            <div class="flex flex-col lg:flex-row gap-4 lg:gap-6 h-[calc(100vh-12rem)] lg:h-[calc(100vh-14rem)] overflow-hidden">
                
                <!-- LEFT PANEL: Active Cooking Queue -->
                <div 
                    :class="[
                        'flex flex-col bg-card shadow-sm border-y lg:border border-secondary/50 transition-all duration-300 min-w-0',
                        activeMobileTab === 'cooking' ? 'flex' : 'hidden lg:flex',
                        'lg:w-2/3 lg:rounded-[2.5rem]'
                    ]"
                >
                    <div class="p-4 lg:p-6 border-b border-secondary/50 bg-secondary/10 flex items-center justify-between shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-xl bg-primary/10 text-primary hidden sm:flex">
                                <ChefHat class="w-5 h-5" />
                            </div>
                            <div>
                                <h2 class="text-xs lg:text-sm font-black uppercase tracking-[0.2em] text-primary leading-none">{{ ui.cooking_queue || 'Cooking Queue' }}</h2>
                                <div class="flex items-center gap-1.5 mt-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                    <span class="text-[8px] font-black text-green-600 uppercase tracking-widest">Live Updates</span>
                                </div>
                            </div>
                        </div>

                        <!-- Branch Filter (Superadmin only) -->
                        <div v-if="branches && branches.length > 0" class="flex-1 max-w-[200px] ml-4 hidden md:block">
                            <div class="relative">
                                <Building2 class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-muted-foreground z-10" />
                                <select 
                                    v-model="branchFilter"
                                    @change="updateBranch"
                                    class="w-full pl-9 pr-8 py-2 bg-background border-secondary/50 rounded-xl text-[10px] font-black uppercase tracking-widest cursor-pointer appearance-none focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all shadow-sm"
                                >
                                    <option value="">{{ ui.all_branches || 'All Branches' }}</option>
                                    <option v-for="branch in branches" :key="branch.branchid" :value="branch.branchid">
                                        {{ branch.branchname }}
                                    </option>
                                </select>
                                <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-muted-foreground pointer-events-none" />
                            </div>
                        </div>

                        <span class="text-[10px] font-black text-primary uppercase bg-primary/5 border border-primary/10 px-3 py-1.5 rounded-xl ml-auto">
                            {{ pendingOrders.length }} {{ ui.items_cooking || 'Items' }}
                        </span>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto p-3 lg:p-6 bg-background/30 no-scrollbar">
                        <div v-if="pendingOrders.length === 0" class="h-full flex flex-col items-center justify-center text-muted-foreground/30 space-y-4 py-20">
                            <div class="w-24 h-24 rounded-[2.5rem] bg-secondary flex items-center justify-center">
                                <CheckCircle2 class="w-12 h-12 text-primary/20" />
                            </div>
                            <p class="text-xs font-black uppercase tracking-widest text-center">{{ ui.no_active_cooking || 'No active orders' }}</p>
                        </div>

                        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2 gap-4 lg:gap-5">
                            <div 
                                v-for="order in pendingOrders" 
                                :key="order.id" 
                                class="flex flex-col bg-white border border-secondary rounded-3xl lg:rounded-[2.5rem] shadow-sm overflow-hidden group hover:border-primary/30 transition-all"
                            >
                                <div class="p-4 lg:p-6 space-y-4">
                                    <!-- Ticket Header -->
                                    <div class="flex justify-between items-start gap-2">
                                        <div class="min-w-0">
                                            <h3 class="text-xl lg:text-2xl font-black text-primary uppercase tracking-tighter leading-none mb-1 truncate">{{ order.tableName }}</h3>
                                            <div class="flex items-center gap-2">
                                                <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest truncate">{{ order.name || 'Anonymous' }}</p>
                                                <span class="text-[10px] text-muted-foreground/30">&bull;</span>
                                                <p class="text-[9px] lg:text-[10px] font-bold text-muted-foreground uppercase tracking-widest">{{ new Date(order.orderTime).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <button 
                                                @click="speakOrder(order)"
                                                class="p-2 rounded-xl bg-primary/10 text-primary hover:bg-primary hover:text-white transition-all shadow-sm"
                                                title="Speak Order"
                                            >
                                                <Volume2 class="w-4 h-4" />
                                            </button>
                                            <div :class="['px-2 lg:px-3 py-1 rounded-lg lg:rounded-xl text-[8px] lg:text-[9px] font-black uppercase tracking-widest border', order.orderType === 'Take Away' ? 'bg-amber-50 text-amber-600 border-amber-200' : 'bg-blue-50 text-blue-600 border-blue-200']">
                                                {{ order.orderType === 'Take Away' ? 'Takeaway' : 'Eat In' }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Ticket Items -->
                                    <div class="space-y-4 py-1 lg:py-2">
                                        <div v-for="item in order.items" :key="item.id" class="flex gap-4">
                                            <!-- Standard Item -->
                                            <template v-if="!item.is_package">
                                                <div class="w-10 h-10 rounded-2xl bg-secondary flex items-center justify-center font-black text-primary text-lg shrink-0 shadow-inner border border-secondary-foreground/5">
                                                    {{ item.quantity }}
                                                </div>
                                                <div class="min-w-0 pt-0.5">
                                                    <h4 class="text-sm lg:text-base font-black uppercase leading-tight tracking-tight text-foreground">{{ item.name }}</h4>
                                                    
                                                    <!-- Item Note -->
                                                    <div v-if="item.note" class="mt-1 flex items-start gap-1.5 bg-amber-50/50 p-1.5 rounded-lg border border-amber-100/50">
                                                        <MessageSquareText class="w-3 h-3 text-amber-600 mt-0.5 shrink-0" />
                                                        <p class="text-[9px] font-bold text-amber-800 leading-tight italic">"{{ item.note }}"</p>
                                                    </div>

                                                    <div v-if="item.addons?.length" class="mt-1.5 flex flex-wrap gap-1">
                                                        <span v-for="(addon, idx) in item.addons" :key="idx" class="text-[9px] font-black bg-primary/5 text-primary/70 uppercase tracking-widest px-2 py-0.5 rounded-lg border border-primary/10">
                                                            + {{ addon }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- Package Group -->
                                            <template v-else>
                                                <div class="w-full flex items-start gap-3 bg-primary/5 p-3 rounded-2xl border border-primary/10">
                                                    <div class="w-10 h-10 rounded-2xl bg-primary text-primary-foreground flex items-center justify-center font-black text-lg shrink-0 shadow-md shadow-primary/20">
                                                        {{ item.quantity }}
                                                    </div>
                                                    <div class="min-w-0 pt-0.5 space-y-2 flex-1">
                                                        <h4 class="text-sm lg:text-base font-black uppercase leading-tight tracking-tight text-primary">PACKAGE: {{ item.name }}</h4>
                                                        
                                                        <div v-if="item.note" class="flex items-start gap-1.5 bg-amber-50 p-1.5 rounded-lg border border-amber-100">
                                                            <MessageSquareText class="w-3 h-3 text-amber-600 mt-0.5 shrink-0" />
                                                            <p class="text-[9px] font-bold text-amber-800 leading-tight italic">"{{ item.note }}"</p>
                                                        </div>

                                                        <div class="pl-3 border-l-2 border-primary/20 space-y-1.5">
                                                            <div v-for="sub in item.sub_items" :key="sub.name" class="flex flex-col">
                                                                <span class="text-xs font-bold text-foreground uppercase tracking-tight">• {{ sub.quantity }}x {{ sub.name }}</span>
                                                                <div v-if="sub.addons?.length" class="flex flex-wrap gap-1 mt-0.5 ml-4">
                                                                    <span v-for="addon in sub.addons" :key="addon" class="text-[8px] font-bold text-muted-foreground uppercase tracking-widest bg-secondary/50 px-1.5 py-0.5 rounded-md">
                                                                        + {{ addon }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- General Order Note -->
                                    <div v-if="order.note" class="bg-amber-50 border border-amber-200 p-3 rounded-2xl space-y-1 mt-2">
                                        <div class="flex items-center gap-2 text-amber-700">
                                            <MessageSquareText class="w-3.5 h-3.5" />
                                            <span class="text-[9px] font-black uppercase tracking-widest">{{ ui.order_note || 'Order Note' }}</span>
                                        </div>
                                        <p class="text-[10px] font-bold text-amber-900 leading-relaxed italic pl-5">"{{ order.note }}"</p>
                                    </div>
                                </div>

                                <!-- Ticket Footer / Action -->
                                <div class="p-4 bg-secondary/5 mt-auto border-t border-secondary/50">
                                    <button 
                                        @click="markAsReady(order.id)"
                                        class="w-full flex justify-center items-center gap-3 py-4 rounded-2xl lg:rounded-3xl bg-green-500 text-white font-black uppercase tracking-[0.15em] text-xs shadow-lg shadow-green-500/30 hover:bg-green-600 active:scale-[0.98] transition-all"
                                    >
                                        <BellRing class="w-4 h-4" />
                                        {{ ui.mark_as_ready || 'Mark as Ready' }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- View More Button -->
                        <div v-if="totalPending > pendingOrders.length" class="mt-8 flex justify-center pb-10">
                            <button 
                                @click="loadMore"
                                class="flex items-center gap-3 px-8 py-4 bg-white border-2 border-primary/20 text-primary font-black uppercase tracking-widest text-xs rounded-2xl hover:bg-primary hover:text-white hover:border-primary transition-all shadow-md group"
                            >
                                <Clock class="w-4 h-4 group-hover:animate-spin" />
                                {{ ui.view_more || 'View More' }} ({{ totalPending - pendingOrders.length }} remaining)
                            </button>
                        </div>
                    </div>
                </div>

                <!-- RIGHT PANEL: Served Today -->
                <div 
                    :class="[
                        'flex flex-col bg-card shadow-sm border-y lg:border border-secondary/50 transition-all duration-300 min-w-0',
                        activeMobileTab === 'history' ? 'flex' : 'hidden lg:flex',
                        'lg:w-1/3 lg:rounded-[2.5rem]'
                    ]"
                >
                    <div class="p-4 lg:p-6 border-b border-secondary/50 bg-secondary/10 flex items-center justify-between shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-xl bg-primary/10 text-primary hidden sm:flex">
                                <ListTodo class="w-5 h-5" />
                            </div>
                            <h2 class="text-xs lg:text-sm font-black uppercase tracking-[0.2em] text-primary leading-none">{{ ui.served_today || 'Served Today' }}</h2>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto p-3 lg:p-6 bg-background/30 no-scrollbar">
                        <div v-if="completedOrders.length === 0" class="h-full flex flex-col items-center justify-center text-muted-foreground/30 space-y-2 py-20">
                            <Clock class="w-10 h-10 text-primary/20" />
                            <p class="text-[10px] font-black uppercase tracking-widest text-center">{{ ui.no_orders_served || 'Empty history' }}</p>
                        </div>

                        <div v-else class="space-y-3">
                            <div v-for="order in completedOrders" :key="order.id" class="p-4 rounded-2xl lg:rounded-[1.5rem] border border-secondary/50 bg-white hover:bg-secondary/5 transition-colors shadow-sm">
                                <div class="flex justify-between items-center mb-3">
                                    <h4 class="text-sm font-black uppercase tracking-tight text-primary">{{ order.tableName }}</h4>
                                    <span 
                                        :class="[
                                            'text-[8px] lg:text-[9px] font-black uppercase tracking-[0.2em] px-2.5 py-1 rounded-full border',
                                            'bg-green-50 text-green-600 border-green-100'
                                        ]"
                                    >
                                        {{ 'Served' }}
                                    </span>
                                </div>
                                <div class="flex justify-between items-end">
                                    <p class="text-[9px] font-bold text-muted-foreground uppercase tracking-widest truncate max-w-[60%]">{{ order.name || 'Guest' }}</p>
                                    <div class="flex flex-col items-end">
                                        <span class="text-[8px] font-black text-muted-foreground/40 uppercase tracking-widest">{{ ui.completed || 'Finished' }}</span>
                                        <span class="text-[10px] font-black text-primary">{{ new Date(order.completionTime).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
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
