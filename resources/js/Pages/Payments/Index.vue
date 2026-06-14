<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { 
    CreditCard, 
    Utensils, 
    ListTodo, 
    Search,
    ChevronRight,
    ShoppingBag,
    Plus,
    Minus,
    Trash2,
    X,
    CheckCircle2,
    Wallet,
    Tag,
    Building,
    UtensilsCrossed,
    Package,
    Barcode,
    XCircle,
    MessageSquareText,
    Camera,
    CameraOff,
    Users as UsersIcon,
    UserCircle,
    Smartphone,
    RefreshCw
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import Button from '@/Components/ui/Button.vue'
import Modal from '@/Components/ui/Modal.vue'
import QrcodeVue from 'qrcode.vue'
import { Html5QrcodeScanner, Html5Qrcode } from 'html5-qrcode'

const page = usePage()
const props = defineProps({
    categories: Array,
    packages: Array,
    products: Array,
    unpaidOrders: Array,
    vacantTables: Array,
    activePromotions: Array,
    branches: Array,
    activeBranchId: [Number, String],
    midtransClientKey: String,
    midtransIsProduction: Boolean,
    errors: Object,
    auth: Object,
    ziggy: Object,
    system: Object,
    locale: String,
    translations: Object,
})

const ui = computed(() => page.props.translations?.ui || {})

const activeTab = ref('menu') // 'menu' or 'unpaid'
const searchQuery = ref('')
const activeCategory = ref(null)
const selectedBranch = ref(props.activeBranchId || '')

const updateBranch = () => {
    router.get(route('payments.index'), { branch: selectedBranch.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    })
}

watch(selectedBranch, updateBranch)

// Camera Barcode Scanning
const showCameraModal = ref(false)
const showDeviceListModal = ref(false)
const cameras = ref([])
const activeDevices = ref([])
const guestDevices = ref([])
const selectedCameraId = ref(null)
const isRequestingRemote = ref(false)
const networkId = computed(() => `room-${page.props.auth?.user?.id || 'main'}`)
const echoConnected = ref(false)

const scannerUrl = computed(() => {
    const path = route('guest-scanner');
    const url = new URL(path.startsWith('http') ? path : window.location.origin + path);
    url.searchParams.set('room', networkId.value);
    return url.toString();
})
let html5QrScanner = null

const combinedDevices = computed(() => {
    const authList = Array.isArray(activeDevices.value) ? activeDevices.value : []
    const guestList = Array.isArray(guestDevices.value) ? guestDevices.value : []
    const authDevices = authList.map(d => ({ ...d, id: d.id || d.userid, type: 'user' }))
    const guestItems = guestList.map(d => ({ ...d, type: 'guest' }))
    return [...authDevices, ...guestItems]
})

const manualRefresh = () => {
    guestDevices.value = []
    sendDiscoveryPing()
}

const combinedOffers = computed(() => {
    const promoItems = (props.activePromotions || []).map(p => ({ ...p, carouselType: 'promo' }))
    return [...promoItems]
})

// Lifecycle Hooks & Real-time Discovery
let pollingInterval = null
const isMobileView = ref(false)
const showMobileOrder = ref(false)

const checkViewSize = () => {
    isMobileView.value = window.innerWidth < 1024
}

const toggleMobileOrder = () => {
    showMobileOrder.value = !showMobileOrder.value
}

const sendDiscoveryPing = () => {
    if (!networkId.value) return
    axios.post('/api/remote-camera/ping', {
        networkId: networkId.value,
        deviceId: 'cashier-' + (page.props.auth?.user?.id || 'main'),
        deviceName: 'Cashier Terminal'
    }).then(() => console.log("Cashier discovery ping sent to room:", networkId.value))
}

onMounted(async () => {
    checkViewSize()
    window.addEventListener('resize', checkViewSize)
    window.addEventListener('keydown', handleGlobalKeyDown)

    // Check Echo Connection
    if (window.Echo) {
        console.log("Initializing Echo connection monitor...")
        const pusher = window.Echo.connector.pusher;
        
        const updateStatus = () => {
            echoConnected.value = pusher.connection.state === 'connected';
            console.log("Broadcasting state changed to:", pusher.connection.state);
        };

        updateStatus();
        pusher.connection.bind('state_change', updateStatus);
        
        pusher.connection.bind('error', (err) => {
            console.error("Broadcasting Error:", err);
            // If it's a 403, it might be an auth issue
            if (err.error?.data?.code === 403) {
                console.error("Broadcasting Auth Failed. Check subfolder pathing.");
            }
        });

        // Periodic heartbeat to force UI sync
        setInterval(updateStatus, 5000);
    } else {
        console.error("Echo is not initialized! Check echo.js and app.js imports.");
    }

    // Load Midtrans Snap script
    const script = document.createElement('script')
    script.src = props.midtransIsProduction ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js'
    script.setAttribute('data-client-key', props.midtransClientKey)
    document.head.appendChild(script)
    
    // Listen for Guest Pings in our specific room (Reactive)
    watch(networkId, (newId, oldId) => {
        if (oldId && window.Echo) {
            window.Echo.leave(`guest-discovery.${oldId}`)
        }
        if (newId && window.Echo) {
            console.log("Joining Discovery Room:", newId)
            window.Echo.channel(`guest-discovery.${newId}`)
                .listen('.guest.discovery', (e) => {
                    console.log("Discovery event received:", e)
                    if (e.deviceId.startsWith('cashier-')) return // Skip other cashiers
                    
                    const exists = guestDevices.value.find(d => d.id === e.deviceId)
                    if (!exists) {
                        guestDevices.value.push({ id: e.deviceId, name: e.deviceName })
                    } else {
                        exists.name = e.deviceName
                    }
                })
        }
    }, { immediate: true })

    // Initial Ping
    sendDiscoveryPing()

    // 2. Join Branch Presence Channel
    const branchId = page.props.auth?.user?.branchid || 1
    window.Echo.join(`branch.${branchId}`)
        .here((users) => {
            activeDevices.value = users.filter(u => u.id !== page.props.auth?.user?.id)
        })
        .joining((user) => {
            activeDevices.value.push(user)
        })
        .leaving((user) => {
            activeDevices.value = activeDevices.value.filter(u => u.id !== user.id)
        })

    // 3. Listen for Scan Results
    window.Echo.private(`App.Models.User.${page.props.auth?.user?.id}`)
        .listen('.remote.camera.scanned', (e) => {
            processBarcode(e.barcode)
            isRequestingRemote.value = false
            showDeviceListModal.value = false
            stopCamera()
        })
    
    window.Echo.channel(`guest-result.${page.props.auth?.user?.id}`)
        .listen('.guest.scanned', (e) => {
            processBarcode(e.barcode)
            isRequestingRemote.value = false
            showDeviceListModal.value = false
            stopCamera()
        })

    // 4. Order Polling
    pollingInterval = setInterval(() => {
        router.reload({ 
            only: ['unpaidOrders'],
            preserveScroll: true,
            preserveState: true
        })
    }, 10000)
})

onUnmounted(() => {
    window.removeEventListener('resize', checkViewSize)
    window.removeEventListener('keydown', handleGlobalKeyDown)
    if (pollingInterval) clearInterval(pollingInterval)
    
    const branchId = page.props.auth?.user?.branchid || 1
    window.Echo.leave(`branch.${branchId}`)
    window.Echo.leave(`App.Models.User.${page.props.auth?.user?.id}`)
    if (networkId.value) {
        window.Echo.leave(`guest-discovery.${networkId.value}`)
    }
    window.Echo.leave(`guest-result.${page.props.auth?.user?.id}`)
})

const requestRemoteCamera = async (device) => {
    isRequestingRemote.value = true
    try {
        if (device.type === 'guest') {
            await axios.post('/api/remote-camera/guest-request', {
                networkId: networkId.value,
                deviceId: device.id,
                requesterId: page.props.auth?.user?.id,
                requesterName: page.props.auth?.user?.name
            })
        } else {
            await axios.post('/api/remote-camera/request', {
                toUserId: device.id
            })
        }
    } catch (err) {
        console.error(err)
        isRequestingRemote.value = false
    }
}

const getCameras = async () => {
    try {
        const devices = await Html5Qrcode.getCameras();
        if (devices && devices.length > 0) {
            cameras.value = devices;
            const phoneCam = devices.find(d => 
                d.label.toLowerCase().includes('usb') || 
                d.label.toLowerCase().includes('droid') || 
                d.label.toLowerCase().includes('iriun') ||
                d.label.toLowerCase().includes('camera')
            );
            selectedCameraId.value = phoneCam ? phoneCam.id : devices[0].id;
        }
    } catch (err) {
        console.error("Error getting cameras", err);
    }
}

const startCamera = async () => {
    showCameraModal.value = true
    await getCameras()
    
    // Proactive Ping for other devices when opening camera options
    if (networkId.value) {
        axios.post('/api/remote-camera/ping', {
            networkId: networkId.value,
            deviceId: 'cashier-' + (page.props.auth?.user?.id || 'main'),
            deviceName: 'Cashier Terminal'
        })
    }

    // Wait for modal to render
    setTimeout(async () => {
        if (html5QrScanner) {
            await html5QrScanner.clear()
        }

        html5QrScanner = new Html5QrcodeScanner(
            "reader",
            { fps: 15, qrbox: { width: 250, height: 250 }, rememberLastUsedCamera: true, supportedScanTypes: [0] },
            false
        );
        html5QrScanner.render(onScanSuccess, onScanFailure);
    }, 100);
}

const stopCamera = () => {
    if (html5QrScanner) {
        html5QrScanner.clear().then(() => {
            html5QrScanner = null
            showCameraModal.value = false
        }).catch(error => {
            console.error("Failed to clear scanner", error);
            showCameraModal.value = false
        });
    } else {
        showCameraModal.value = false
    }
}

const onScanSuccess = (decodedText) => {
    processBarcode(decodedText)
    stopCamera()
}

const onScanFailure = () => {}

// Barcode Scanning Logic
const barcodeBuffer = ref('')
let lastKeyTime = Date.now()
const barcodeSearchInput = ref('')

const handleGlobalKeyDown = (e) => {
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return
    const currentTime = Date.now()
    if (currentTime - lastKeyTime > 50) { barcodeBuffer.value = '' }
    lastKeyTime = currentTime
    if (e.key === 'Enter') {
        if (barcodeBuffer.value.length > 2) { processBarcode(barcodeBuffer.value) }
        barcodeBuffer.value = ''
    } else if (e.key.length === 1) {
        barcodeBuffer.value += e.key
    }
}

const playScanSound = () => {
    try {
        const audio = new Audio('/POS/sound/ting.mp3')
        audio.play().catch(e => console.warn('Sound play blocked or failed:', e))
    } catch (err) {
        console.error("Failed to play scan sound", err)
    }
}

const processBarcode = (code) => {
    if (!code) return
    const searchCode = code.toString().toLowerCase().trim()
    console.log("POS: Processing barcode:", searchCode)
    
    let foundItem = null

    // 1. Search in Products
    if (props.products) {
        foundItem = props.products.find(p => p.barcode && p.barcode.toLowerCase() === searchCode)
    }

    // 2. Search in Categories/Menus (Deep Search)
    if (!foundItem && props.categories) {
        for (const cat of props.categories) {
            if (cat.menus) {
                const menu = cat.menus.find(m => m.barcode && m.barcode.toLowerCase() === searchCode)
                if (menu) {
                    foundItem = menu
                    break
                }
            }
        }
    }

    // 3. Search in Packages
    if (!foundItem && props.packages) {
        foundItem = props.packages.find(p => p.barcode && p.barcode.toLowerCase() === searchCode)
    }

    if (foundItem) {
        console.log("POS: Item found!", foundItem.name)
        playScanSound()
        addToCart(foundItem)
        barcodeSearchInput.value = ''
        
        // Success feedback
        if (window.navigator.vibrate) window.navigator.vibrate(50)
    } else {
        console.warn('POS: Item not found for barcode:', code)
    }
}

const onManualBarcodeSubmit = () => {
    if (barcodeSearchInput.value) { processBarcode(barcodeSearchInput.value) }
}

const filteredPromotions = computed(() => {
    const pkgs = props.packages || []
    const query = (searchQuery.value || '').toLowerCase()
    if (!query) return pkgs
    
    return pkgs.filter(pkg => {
        const nameMatch = (pkg.name || '').toLowerCase().includes(query)
        const menus = pkg.menus || []
        // Convert to array if it's an object (just in case)
        const menusArr = Array.isArray(menus) ? menus : Object.values(menus)
        const menuMatch = menusArr.some(m => (m.name || '').toLowerCase().includes(query))
        return nameMatch || menuMatch
    })
})

const filteredProducts = computed(() => {
    if (!props.products) return []
    const query = searchQuery.value.toLowerCase()
    return props.products.filter(p => 
        p.name.toLowerCase().includes(query) ||
        p.barcode.toLowerCase().includes(query)
    )
})

const filteredCategories = computed(() => {
    if (!props.categories) return []
    const query = searchQuery.value.toLowerCase()
    return props.categories.map(cat => ({
        ...cat,
        menus: (cat.menus || []).filter(menu => 
            menu.name.toLowerCase().includes(query) ||
            menu.description?.toLowerCase().includes(query)
        )
    })).filter(cat => cat.menus.length > 0)
})

// Active Order State
const activeOrder = ref({
    orderId: null,
    tableName: '',
    customerName: '',
    type: 'Counter Order',
    tableId: null,
    items: [],
    subtotal: 0,
    tax: 0,
    totalPrice: 0,
    appliedPromotionId: null,
    appliedPromotionName: null,
    appliedPromotionDiscount: 0,
    note: ''
})

const formatPrice = (price) => {
    return new Intl.NumberFormat(page.props.locale === 'id' ? 'id-ID' : 'en-US', {
        style: 'currency', currency: 'IDR', minimumFractionDigits: 0,
    }).format(price || 0)
}

const selectCategory = (id) => {
    activeCategory.value = id
    const targetId = id === 'promos' ? 'cat-promos' : (id === 'products' ? 'cat-products' : `cat-${id}`)
    const element = document.getElementById(targetId)
    if (element) element.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

// Addon & Promo Modal State
const showAddonModal = ref(false)
const selectedMenuForAddons = ref(null)
const selectedAddons = ref([])
const selectedQuantity = ref(1)
const showPromoModal = ref(false)
const selectedPromo = ref(null)

const openMenuModal = (menu) => {
    selectedMenuForAddons.value = menu
    selectedAddons.value = []
    selectedQuantity.value = 1
    showAddonModal.value = true
}

const openPromoModal = (promo) => {
    selectedPromo.value = promo
    showPromoModal.value = true
}

const toggleAddon = (addon) => {
    const index = selectedAddons.value.findIndex(a => a.addonid === addon.addonid)
    index > -1 ? selectedAddons.value.splice(index, 1) : selectedAddons.value.push(addon)
}

const confirmAddons = () => {
    addToCart(selectedMenuForAddons.value, [...selectedAddons.value], selectedQuantity.value)
    showAddonModal.value = false
}

const addToCart = (item, addons = [], quantity = 1) => {
    const isPackage = !!item.is_package
    const isProduct = !!item.is_product
    const cartId = isPackage ? `direct-pkg-${item.packageid}` : (isProduct ? `direct-prod-${item.id}` : `direct-${item.id}-${addons.map(a => a.addonid).sort().join('-')}`)
    
    const existingIndex = activeOrder.value.items.findIndex(i => i.cartId === cartId)
    if (existingIndex > -1) {
        activeOrder.value.items[existingIndex].quantity = Math.min(activeOrder.value.items[existingIndex].quantity + quantity, item.max_quantity || 999)
    } else {
        activeOrder.value.items.push({
            id: item.id, cartId, name: item.name, price: item.price, quantity, addons, is_package: isPackage, is_product: isProduct,
            packageid: isPackage ? item.packageid : 0, menus: item.menus || [], freebies: item.freebies || [],
            all_promoids: item.all_promoids || [], max_quantity: item.max_quantity || 999,
            itemTotalPrice: item.price + addons.reduce((sum, a) => sum + a.price, 0)
        })
    }
    calculateTotal()
}

const removeItem = (cartId) => {
    activeOrder.value.items = activeOrder.value.items.filter(i => i.cartId !== cartId)
    calculateTotal()
}

const increaseQty = (cartId) => {
    const item = activeOrder.value.items.find(i => i.cartId === cartId)
    if (item) { item.quantity++; calculateTotal() }
}

const decreaseQty = (cartId) => {
    const item = activeOrder.value.items.find(i => i.cartId === cartId)
    if (item && item.quantity > 1) { item.quantity--; calculateTotal() }
}

const activeDiscount = computed(() => {
    if (activeOrder.value.appliedPromotionName) {
        return { 
            id: activeOrder.value.appliedPromotionId, 
            name: activeOrder.value.appliedPromotionName, 
            amount: activeOrder.value.appliedPromotionDiscount, 
            text: `Discount applied: ${activeOrder.value.appliedPromotionName}` 
        }
    }
    
    if (!props.activePromotions?.length) return null

    // Filter promos that meet subtotal requirements (Discounts only)
    const applicableDiscounts = props.activePromotions.filter(promo => {
        if (promo.type === 'BUY_X_GET_Y') return false
        if (promo.min_purchase > 0 && activeOrder.value.subtotal < promo.min_purchase) return false
        return true
    })

    if (!applicableDiscounts.length) return null

    let bestPromo = null
    let maxAmount = -1

    applicableDiscounts.forEach(promo => {
        let currentAmount = 0
        if (promo.type === 'DISCOUNT_PERCENT') {
            currentAmount = activeOrder.value.subtotal * (promo.discount_value / 100)
        } else if (promo.type === 'DISCOUNT_FIXED') {
            currentAmount = promo.discount_value
        }

        if (currentAmount > maxAmount) {
            maxAmount = currentAmount
            bestPromo = promo
        }
    })

    if (!bestPromo) return null

    return { 
        id: bestPromo.promotionid, 
        name: bestPromo.name, 
        type: bestPromo.type, 
        amount: maxAmount, 
        text: `Discount applied: ${bestPromo.name}` 
    }
})

const qualifiedRewards = computed(() => {
    if (!props.activePromotions?.length) return []
    
    return props.activePromotions.filter(promo => {
        if (promo.type !== 'BUY_X_GET_Y') return false
        
        // Find if the trigger menu is in the cart with enough quantity
        const cartItem = activeOrder.value.items.find(i => i.id === promo.buy_menuid)
        return cartItem && cartItem.quantity >= promo.buy_qty
    }).map(promo => ({
        id: promo.promotionid,
        name: promo.name,
        buy_menuid: promo.buy_menuid,
        get_menu_name: promo.get_menu?.name || 'Item',
        get_qty: promo.get_qty
    }))
})

const calculateTotal = () => {
    const subtotal = activeOrder.value.items.reduce((sum, item) => sum + (item.itemTotalPrice * item.quantity), 0)
    activeOrder.value.subtotal = subtotal
    activeOrder.value.tax = subtotal * 0.10
    const discount = activeDiscount.value?.amount || 0
    activeOrder.value.totalPrice = (subtotal - discount) + activeOrder.value.tax
}

// Payment Workflow
const selectedPaymentMethod = ref('Cash')
const amountPaid = ref(0)
const isPaymentProcessing = ref(false)
const lastCompletedOrderId = ref(null)

const changeAmount = computed(() => Math.max(0, amountPaid.value - activeOrder.value.totalPrice))
const canProcessPayment = computed(() => {
    if (!activeOrder.value.items.length) return false
    if (!activeOrder.value.orderId) {
        if (!activeOrder.value.customerName || !activeOrder.value.type) return false
        if (activeOrder.value.type === 'Counter Order' && !activeOrder.value.tableId) return false
    }
    if (selectedPaymentMethod.value === 'Cash' && amountPaid.value < activeOrder.value.totalPrice) return false
    return !isPaymentProcessing.value
})

const processPayment = async () => {
    if (!canProcessPayment.value) return
    isPaymentProcessing.value = true
    try {
        const response = await axios.post(route('api.cashier.pay'), {
            orderId: activeOrder.value.orderId || null,
            paymentMethod: selectedPaymentMethod.value,
            amountPaid: selectedPaymentMethod.value === 'Cash' ? amountPaid.value : activeOrder.value.totalPrice,
            totalPrice: activeOrder.value.totalPrice,
            items: activeOrder.value.items,
            customerName: activeOrder.value.customerName,
            orderType: activeOrder.value.type,
            tableId: activeOrder.value.tableId,
            branchId: selectedBranch.value, // Added branchId
            appliedPromotionId: activeDiscount.value?.id,
            appliedPromotionName: activeDiscount.value?.name,
            appliedPromotionDiscount: activeDiscount.value?.amount || 0,
            freeMenuItemId: qualifiedRewards.value[0]?.id ? props.activePromotions.find(p => p.promotionid === qualifiedRewards.value[0].id)?.get_menuid : null,
            freeMenuItemQty: qualifiedRewards.value[0]?.get_qty || 0
        })
        if (response.data.success) {
            if (response.data.snapToken) {
                window.snap.pay(response.data.snapToken, {
                    onSuccess: (result) => {
                        console.log('success', result)
                        finishOrder(response.data.orderId)
                    },
                    onPending: (result) => {
                        console.log('pending', result)
                        finishOrder(response.data.orderId)
                    },
                    onError: (result) => {
                        console.error('error', result)
                        isPaymentProcessing.value = false
                    },
                    onClose: () => {
                        console.log('customer closed the popup without finishing the payment')
                        isPaymentProcessing.value = false
                    }
                })
            } else {
                finishOrder(response.data.orderId)
            }
        }
    } catch (e) { console.error('Payment Error:', e.message) }
    finally { 
        if (selectedPaymentMethod.value === 'Cash') {
            isPaymentProcessing.value = false 
        }
    }
}

const finishOrder = (orderId) => {
    lastCompletedOrderId.value = orderId
    activeOrder.value = { 
        items: [], 
        subtotal: 0, 
        tax: 0, 
        totalPrice: 0, 
        customerName: '', 
        type: 'Counter Order', 
        tableId: null, 
        appliedPromotionId: null, 
        appliedPromotionName: null, 
        appliedPromotionDiscount: 0, 
        note: '' 
    }
    amountPaid.value = 0
    isPaymentProcessing.value = false
}

const printReceipt = (id) => id && window.open(route('api.cashier.receipt', { order: id }), '_blank')

const selectUnpaidOrder = (order) => {
    lastCompletedOrderId.value = null
    const subtotal = order.items.reduce((sum, item) => sum + (item.itemTotalPrice * item.quantity), 0)
    activeOrder.value = {
        orderId: order.id, tableName: order.tableName, customerName: order.name, type: order.orderType,
        items: order.items.map(item => ({ ...item, cartId: `order-${order.id}-${item.id}-${Date.now()}` })),
        subtotal, tax: subtotal * 0.10, totalPrice: order.totalPrice,
        appliedPromotionId: order.appliedPromotionId || null,
        appliedPromotionName: order.appliedPromotionName || null,
        appliedPromotionDiscount: order.appliedPromotionDiscount || 0,
        note: order.note || ''
    }
    amountPaid.value = 0
}
</script>

<template>
    <AppLayout>
        <Head :title="ui.payments_cashier || 'Payments'" />

        <div class="h-[calc(100vh-10rem)] lg:h-[calc(100vh-12rem)] flex flex-col lg:flex-row gap-6 overflow-hidden relative">
            <!-- MOBILE FAB for Order View -->
            <button 
                v-if="isMobileView && activeOrder.items.length > 0"
                @click="toggleMobileOrder"
                class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-6 py-4 rounded-3xl bg-primary text-primary-foreground shadow-2xl shadow-primary/40 animate-in slide-in-from-bottom-10 duration-300"
            >
                <div class="relative">
                    <ShoppingBag class="w-6 h-6" />
                    <span class="absolute -top-2 -right-2 bg-white text-primary text-[10px] font-black w-5 h-5 rounded-full flex items-center justify-center border-2 border-primary">
                        {{ activeOrder.items.reduce((sum, i) => sum + i.quantity, 0) }}
                    </span>
                </div>
                <div class="text-left">
                    <p class="text-[8px] font-black uppercase opacity-60 leading-none mb-1">{{ ui.current_order || 'Current Order' }}</p>
                    <p class="text-sm font-black leading-none tracking-tight">{{ formatPrice(activeOrder.totalPrice) }}</p>
                </div>
            </button>

            <!-- LEFT PANEL: Active Payment / Cart -->
            <div 
                v-if="!isMobileView || showMobileOrder"
                :class="[
                    'flex flex-col bg-card shadow-sm overflow-hidden min-w-0 transition-all duration-300 z-40',
                    isMobileView 
                        ? 'fixed inset-0 pt-20' 
                        : 'basis-3/5 rounded-[2rem] border border-secondary/50'
                ]"
            >
                <!-- Mobile Header for Drawer -->
                <div v-if="isMobileView" class="fixed top-0 left-0 right-0 p-6 bg-white z-50 flex items-center justify-between border-b border-secondary/50">
                    <button @click="toggleMobileOrder" class="p-3 rounded-2xl bg-secondary text-primary">
                        <X class="w-6 h-6" />
                    </button>
                    <div class="flex items-center gap-4">
                        <h2 class="text-xs font-black uppercase tracking-[0.2em] text-primary">{{ ui.current_order || 'Current Order' }}</h2>
                        <!-- Branch Filter Mobile -->
                        <div v-if="props.branches?.length > 0" class="relative min-w-[120px]">
                            <select 
                                v-model="selectedBranch"
                                class="w-full pl-3 pr-8 py-1.5 rounded-lg border-transparent bg-secondary/50 text-[9px] font-black uppercase tracking-widest shadow-sm outline-none appearance-none cursor-pointer"
                            >
                                <option v-for="branch in branches" :key="branch.branchid" :value="branch.branchid">
                                    {{ branch.branchname.split(' ')[1] || branch.branchname }}
                                </option>
                            </select>
                            <ChevronDown class="absolute right-2 top-1/2 -translate-y-1/2 w-3 h-3 text-muted-foreground pointer-events-none" />
                        </div>
                    </div>
                    <div class="w-12"></div>
                </div>

                <div class="p-4 border-b border-secondary/50 bg-secondary/10 flex items-center justify-between" v-if="!isMobileView">
                    <div class="flex items-center gap-4">
                        <h2 class="text-[10px] font-black uppercase tracking-[0.2em] text-primary flex items-center gap-2">
                            <ShoppingBag class="w-4 h-4" />
                            {{ ui.current_order || 'Current Order' }}
                        </h2>
                        
                        <!-- Branch Filter Desktop -->
                        <div v-if="props.branches?.length > 0" class="relative min-w-[150px]">
                            <Building class="absolute left-3 top-1/2 -translate-y-1/2 w-3 h-3 text-primary z-10" />
                            <select 
                                id="branch-filter"
                                name="branch-filter"
                                v-model="selectedBranch"
                                class="w-full pl-8 pr-8 py-2 rounded-xl border-transparent bg-card text-[9px] font-black uppercase tracking-widest shadow-sm outline-none focus:ring-2 focus:ring-primary/10 transition-all appearance-none cursor-pointer"
                            >
                                <option v-for="branch in branches" :key="branch.branchid" :value="branch.branchid">
                                    {{ branch.branchname }}
                                </option>
                            </select>
                            <ChevronDown class="absolute right-3 top-1/2 -translate-y-1/2 w-3 h-3 text-muted-foreground pointer-events-none" />
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button 
                            v-if="activeOrder.orderId || activeOrder.items.length > 0" 
                            @click="activeOrder = { items: [], totalPrice: 0, subtotal: 0, tax: 0, customerName: '', type: 'Counter Order', tableId: null, note: '' }; lastCompletedOrderId = null"
                            class="p-1 rounded-lg bg-destructive/10 text-destructive hover:bg-destructive hover:text-white transition-colors"
                            :title="ui.clear_selection || 'Clear selection'"
                        >
                            <X class="w-3 h-3" />
                        </button>
                        <span :class="['text-[9px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-md', activeOrder.orderId ? 'bg-primary text-primary-foreground' : 'bg-secondary text-muted-foreground']">
                            {{ activeOrder.orderId ? `${ui.table || 'Table'} ${activeOrder.tableName}` : (ui.direct || 'Direct') }}
                        </span>
                    </div>
                </div>
                
                <div class="flex-1 overflow-y-auto p-4 pb-32 space-y-2 bg-background">
                    <!-- Success View after Payment -->
                    <div v-if="lastCompletedOrderId && activeOrder.items.length === 0" class="h-full flex flex-col items-center justify-center text-center space-y-4 animate-in fade-in zoom-in duration-500">
                        <div class="w-16 h-16 rounded-full bg-green-500/10 flex items-center justify-center text-green-500 mb-1">
                            <CheckCircle2 class="w-10 h-10" />
                        </div>
                        <div>
                            <h3 class="text-sm font-black uppercase tracking-tight text-primary">{{ ui.payment_complete || 'Payment Complete' }}</h3>
                            <p class="text-[9px] font-bold text-muted-foreground uppercase tracking-widest">{{ ui.order || 'Order' }} #{{ lastCompletedOrderId }}</p>
                        </div>
                        <div class="flex gap-2 w-full max-w-xs">
                            <button 
                                @click="printReceipt(lastCompletedOrderId)"
                                class="flex-1 flex items-center justify-center gap-2 py-3 rounded-xl bg-primary text-primary-foreground font-black uppercase tracking-widest text-[10px] shadow-lg shadow-primary/20 hover:scale-[1.05] transition-all"
                            >
                                <Utensils class="w-3 h-3" />
                                {{ ui.receipt || 'Receipt' }}
                            </button>
                            <button 
                                @click="lastCompletedOrderId = null"
                                class="flex-1 py-3 rounded-xl bg-secondary text-primary font-black uppercase tracking-widest text-[10px] hover:bg-secondary-foreground/10 transition-all"
                            >
                                {{ ui.new_sale || 'New Sale' }}
                            </button>
                        </div>
                    </div>

                    <div v-else-if="activeOrder.items.length === 0" class="h-full flex flex-col items-center justify-center text-muted-foreground/30 space-y-2">
                        <ShoppingBag class="w-10 h-10" />
                        <p class="text-[9px] font-black uppercase tracking-[0.3em]">{{ ui.no_items || 'No items' }}</p>
                    </div>

                    <div v-else class="space-y-2">
                        <div v-if="activeOrder.orderId" class="p-3 bg-primary/5 border border-primary/20 rounded-xl mb-2">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="text-[8px] font-black uppercase tracking-widest text-primary/60">{{ ui.selected_order || 'Selected Order' }}</p>
                                    <h3 class="text-[11px] font-black uppercase text-primary">#{{ activeOrder.orderId }} - {{ activeOrder.customerName || (ui.no_name || 'No Name') }}</h3>
                                </div>
                                <div class="text-right">
                                    <p class="text-[8px] font-black uppercase tracking-widest text-primary/60">{{ ui.type || 'Type' }}</p>
                                    <span class="text-[9px] font-bold text-primary uppercase">{{ activeOrder.type === 'Take Away' ? (ui.takeaway || 'Takeaway') : (ui.eat_here || 'Eat Here') }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Direct Order Info -->
                        <div v-else class="p-3 bg-secondary/20 border border-secondary/50 rounded-xl mb-2 space-y-3">
                            <div class="grid grid-cols-2 gap-2">
                                <div class="space-y-1">
                                    <label class="text-[8px] font-black uppercase tracking-widest text-muted-foreground ml-1">{{ ui.customer_name || 'Customer Name' }}</label>
                                    <input 
                                        v-model="activeOrder.customerName"
                                        type="text"
                                        :placeholder="ui.optional || '(Optional)'"
                                        class="w-full px-3 py-2 rounded-lg bg-background border border-secondary text-[10px] font-bold focus:border-primary focus:ring-1 focus:ring-primary outline-none"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[8px] font-black uppercase tracking-widest text-muted-foreground ml-1">{{ ui.order_type || 'Order Type' }}</label>
                                    <select 
                                        v-model="activeOrder.type"
                                        class="w-full px-3 py-2 rounded-lg bg-background border border-secondary text-[10px] font-bold focus:border-primary focus:ring-1 focus:ring-primary outline-none"
                                    >
                                        <option value="Take Away">{{ ui.takeaway || 'Takeaway' }}</option>
                                        <option value="Counter Order">{{ ui.eat_here || 'Eat Here' }}</option>
                                    </select>
                                </div>
                            </div>

                            <div v-if="activeOrder.type === 'Counter Order'" class="space-y-1">
                                <label class="text-[8px] font-black uppercase tracking-widest text-muted-foreground ml-1">{{ ui.select_table || 'Select Table' }}</label>
                                <select 
                                    v-model="activeOrder.tableId"
                                    class="w-full px-3 py-2 rounded-lg bg-background border border-secondary text-[10px] font-bold focus:border-primary focus:ring-1 focus:ring-primary outline-none"
                                >
                                    <option v-for="table in props.vacantTables" :key="table.tableseatid" :value="table.tableseatid">
                                        {{ table.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div v-for="item in activeOrder.items" :key="item.cartId" class="flex flex-col bg-card border border-secondary p-3 rounded-xl shadow-sm space-y-2 relative group">
                            <button 
                                v-if="!activeOrder.orderId"
                                @click="removeItem(item.cartId)"
                                class="absolute top-1 right-1 p-1.5 rounded-lg bg-destructive/10 text-destructive hover:bg-destructive hover:text-white transition-colors opacity-0 group-hover:opacity-100"
                            >
                                <Trash2 class="w-3 h-3" />
                            </button>

                            <div class="flex justify-between items-start" :class="{'pr-8': !activeOrder.orderId}">
                                <div class="min-w-0 pr-2">
                                    <h4 class="text-[11px] font-black uppercase tracking-tight truncate">{{ item.name }}</h4>
                                    <div class="flex flex-wrap gap-1 mt-0.5">
                                        <span v-if="item.is_package" class="text-[8px] font-black bg-primary/10 text-primary px-2 py-0.5 rounded uppercase tracking-widest border border-primary/20">PROMO</span>
                                        <span v-if="item.is_product" class="text-[8px] font-black bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded uppercase tracking-widest border border-emerald-200">PRODUCT</span>
                                    </div>
                                    <p class="text-[9px] font-bold text-muted-foreground mt-1">{{ formatPrice(item.price) }}</p>

                                    <!-- Package Contents -->
                                    <div v-if="item.is_package" class="mt-2 space-y-1 pl-2 border-l-2 border-primary/20">
                                        <div v-for="pMenu in item.menus" :key="pMenu.id" class="flex flex-col">
                                            <span class="text-[9px] font-bold text-foreground/80 uppercase">· {{ pMenu.qty }}x {{ pMenu.name }}</span>
                                            <span v-if="pMenu.addonname" class="text-[8px] font-black text-primary uppercase tracking-widest ml-3">+ {{ pMenu.addonname }}</span>
                                        </div>
                                        <div v-for="freebie in item.freebies" :key="freebie.id">
                                            <span class="text-[9px] font-black text-green-600 uppercase tracking-widest leading-none">· Free: {{ freebie.qty }}x {{ freebie.name }}</span>
                                        </div>
                                    </div>

                                    <!-- Addons Display -->
                                    <div v-if="!item.is_package && item.addons?.length" class="mt-1 flex flex-wrap gap-1">
                                        <span v-for="addon in item.addons" :key="addon.addonid" class="text-[7px] font-bold bg-secondary text-muted-foreground px-1.5 py-0.5 rounded-full uppercase tracking-widest">
                                            + {{ addon.name }}
                                        </span>
                                    </div>

                                    <!-- FREE ITEM INDICATOR (Buy X Get Y) -->
                                    <template v-for="reward in qualifiedRewards" :key="reward.id">
                                        <div v-if="reward.buy_menuid === item.id" class="mt-2 animate-in fade-in slide-in-from-left-2">
                                            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-lg bg-green-50 border border-green-100 text-green-700 shadow-sm">
                                                <div class="relative">
                                                    <Tag class="w-3 h-3 animate-bounce" />
                                                    <CheckCircle2 class="absolute -top-1 -right-1 w-2 h-2 text-green-500 fill-white" />
                                                </div>
                                                <span class="text-[9px] font-black uppercase tracking-widest leading-none">FREE: {{ reward.get_qty }}x {{ reward.get_menu_name }}</span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="text-xs font-black text-primary">{{ formatPrice(item.itemTotalPrice * item.quantity) }}</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between border-t border-secondary/50 pt-2">
                                <span class="text-[9px] font-bold text-muted-foreground uppercase tracking-widest">{{ ui.qty || 'Qty' }}</span>
                                <div v-if="!activeOrder.orderId" class="flex items-center gap-3 bg-secondary/30 rounded-lg p-0.5">
                                    <button @click="decreaseQty(item.cartId)" class="p-1.5 rounded-md bg-background border border-secondary hover:text-primary transition-all disabled:opacity-50" :disabled="item.quantity <= 1">
                                        <Minus class="w-2.5 h-2.5" stroke-width="3" />
                                    </button>
                                    <span class="text-[10px] font-black w-3 text-center">{{ item.quantity }}</span>
                                    <button @click="increaseQty(item.cartId)" class="p-1.5 rounded-md bg-primary text-primary-foreground hover:scale-105 transition-all disabled:opacity-50" :disabled="item.quantity >= (item.max_quantity || 999)">
                                        <Plus class="w-2.5 h-2.5" stroke-width="3" />
                                    </button>
                                </div>
                                <span v-else class="text-[10px] font-black text-primary bg-secondary px-2 py-0.5 rounded-md">x{{ item.quantity }}</span>
                            </div>

                            <!-- Item Note Input/Display -->
                            <div class="mt-2 space-y-1.5">
                                <template v-if="!activeOrder.orderId">
                                    <div class="flex items-center gap-2 px-1">
                                        <MessageSquareText class="w-3 h-3 text-muted-foreground" />
                                        <span class="text-[8px] font-black uppercase tracking-widest text-muted-foreground">{{ ui.item_note || 'Item Note' }}</span>
                                    </div>
                                    <textarea 
                                        v-model="item.note"
                                        :placeholder="ui.add_special_instruction || 'Add special instruction...'"
                                        class="w-full bg-secondary/20 border-transparent rounded-lg p-2 text-[10px] font-bold focus:ring-1 focus:ring-primary focus:bg-background transition-all outline-none min-h-[40px] resize-none"
                                    ></textarea>
                                </template>
                                <div v-else-if="item.note" class="flex items-start gap-2 bg-primary/5 p-2 rounded-lg border border-primary/10">
                                    <MessageSquareText class="w-3 h-3 text-primary mt-0.5 shrink-0" />
                                    <p class="text-[9px] font-bold text-foreground leading-tight italic">"{{ item.note }}"</p>
                                </div>
                            </div>
                        </div>

                        <!-- General Order Note Display -->
                        <div v-if="activeOrder.note" class="bg-amber-50 border border-amber-200 p-3 rounded-xl shadow-sm space-y-1 animate-in fade-in">
                            <div class="flex items-center gap-2">
                                <MessageSquareText class="w-3.5 h-3.5 text-amber-600" />
                                <span class="text-[9px] font-black uppercase tracking-widest text-amber-700">{{ ui.order_note || 'Order Note' }}</span>
                            </div>
                            <p class="text-[10px] font-bold text-amber-900 leading-relaxed italic pl-5">"{{ activeOrder.note }}"</p>
                        </div>
                    </div>
                </div>

                <!-- Payment Summary -->
                <div v-if="activeOrder.items.length > 0" class="p-4 border-t border-secondary/50 bg-secondary/5 space-y-4">
                    <div class="px-1 space-y-2">
                        <div class="flex justify-between items-center opacity-60">
                            <span class="text-[9px] font-black uppercase tracking-widest text-muted-foreground">{{ ui.subtotal || 'Subtotal' }}</span>
                            <span class="text-[10px] font-black text-muted-foreground">{{ formatPrice(activeOrder.subtotal) }}</span>
                        </div>
                        <div class="flex justify-between items-center opacity-60">
                            <span class="text-[9px] font-black uppercase tracking-widest text-muted-foreground">{{ ui.tax || 'Tax (10%)' }}</span>
                            <span class="text-[10px] font-black text-muted-foreground">{{ formatPrice(activeOrder.tax) }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-1 border-t border-secondary/20">
                            <span class="text-[10px] font-black uppercase tracking-widest text-primary">{{ ui.total || 'Total' }}</span>
                            <span class="text-base font-black text-primary tracking-tighter">{{ formatPrice(activeOrder.totalPrice) }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <button @click="selectedPaymentMethod = 'Cash'" :class="['flex items-center justify-center gap-2 p-3 rounded-xl border transition-all', selectedPaymentMethod === 'Cash' ? 'bg-primary border-primary text-primary-foreground shadow-md' : 'bg-card border-secondary text-muted-foreground']">
                            <Wallet class="w-4 h-4" /> <span class="text-[9px] font-black uppercase tracking-widest">{{ ui.cash || 'Cash' }}</span>
                        </button>
                        <button @click="selectedPaymentMethod = 'QRIS'" :class="['flex items-center justify-center gap-2 p-3 rounded-xl border transition-all', selectedPaymentMethod === 'QRIS' ? 'bg-primary border-primary text-primary-foreground shadow-md' : 'bg-card border-secondary text-muted-foreground']">
                            <CreditCard class="w-4 h-4" /> <span class="text-[9px] font-black uppercase tracking-widest">QRIS</span>
                        </button>
                    </div>

                    <!-- Cash Input -->
                    <div v-if="selectedPaymentMethod === 'Cash'" class="space-y-2">
                        <div class="flex justify-between items-end px-1">
                            <label class="text-[9px] font-black uppercase tracking-[0.2em] text-muted-foreground">{{ ui.amount || 'Amount' }}</label>
                            <p v-if="amountPaid > 0" class="text-[9px] font-bold text-primary">{{ formatPrice(amountPaid) }}</p>
                        </div>
                        <div class="relative group">
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground font-black text-[10px]">Rp</div>
                            <input type="number" v-model.number="amountPaid" class="w-full pl-8 pr-3 py-2.5 rounded-xl bg-card border border-secondary focus:border-primary focus:ring-1 focus:ring-primary outline-none font-black text-sm tracking-tighter" placeholder="0" />
                        </div>
                        <div v-if="changeAmount > 0" class="flex justify-between items-center px-1 animate-in fade-in slide-in-from-top-1">
                            <span class="text-[9px] font-black uppercase tracking-widest text-muted-foreground">{{ ui.change || 'Change' }}</span>
                            <span class="text-xs font-black text-primary">{{ formatPrice(changeAmount) }}</span>
                        </div>
                    </div>

                    <button @click="processPayment" :disabled="!canProcessPayment" class="w-full flex items-center justify-center gap-2 py-4 rounded-xl bg-primary text-primary-foreground font-black uppercase tracking-widest text-xs shadow-lg shadow-primary/30 transition-all disabled:opacity-50">
                        <component :is="isPaymentProcessing ? Utensils : CheckCircle2" :class="['w-4 h-4', isPaymentProcessing ? 'animate-spin' : '']" />
                        {{ isPaymentProcessing ? '...' : (ui.pay_now || 'Pay Now') }}
                    </button>
                </div>
            </div>

            <!-- RIGHT PANEL: Menu & Pending Orders Tabs -->
            <div :class="['flex flex-col min-w-0 bg-card shadow-sm overflow-hidden transition-all duration-300 rounded-[2rem] border border-secondary/50', isMobileView ? 'basis-full' : 'basis-2/5']">
                <!-- Tabs Header -->
                <div class="flex p-2 gap-2 border-b border-secondary/50 bg-secondary/10 shrink-0">
                    <button 
                        @click="activeTab = 'menu'"
                        :class="[
                            'flex-1 flex items-center justify-center gap-2 py-3 rounded-xl text-xs font-black uppercase tracking-widest transition-all',
                            activeTab === 'menu' ? 'bg-primary text-primary-foreground shadow-md' : 'text-muted-foreground hover:bg-secondary'
                        ]"
                    >
                        <Utensils class="w-4 h-4" />
                        <span class="hidden sm:inline">{{ ui.menu_direct || 'Menu (Direct)' }}</span>
                        <span class="sm:hidden">{{ ui.menu || 'Menu' }}</span>
                    </button>
                    <button 
                        @click="activeTab = 'unpaid'"
                        :class="[
                            'flex-1 flex items-center justify-center gap-2 py-3 rounded-xl text-xs font-black uppercase tracking-widest transition-all relative',
                            activeTab === 'unpaid' ? 'bg-primary text-primary-foreground shadow-md' : 'text-muted-foreground hover:bg-secondary'
                        ]"
                    >
                        <ListTodo class="w-4 h-4" />
                        <span class="hidden sm:inline">{{ ui.unpaid_orders || 'Unpaid Orders' }}</span>        
                        <span class="sm:hidden">{{ ui.unpaid || 'Unpaid' }}</span>
                        <span v-if="props.unpaidOrders.length > 0" class="absolute top-2 right-2 flex h-3 w-3">      
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-destructive opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-destructive"></span>     
                        </span>
                    </button>
                </div>

                <!-- Tab Content: MENU -->
                <div v-if="activeTab === 'menu'" class="flex-1 flex flex-col overflow-hidden min-h-0">
                    <!-- Categories -->
                    <div class="px-4 lg:px-6 py-4 border-b border-secondary/50 flex gap-2 overflow-x-auto no-scrollbar shrink-0 bg-background/50">
                        <!-- Products Shortcut -->
                        <button 
                            v-if="props.products?.length > 0"
                            @click="selectCategory('products')"
                            :class="[
                                'shrink-0 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all',
                                activeCategory === 'products' ? 'bg-primary text-primary-foreground shadow-sm' : 'bg-secondary text-muted-foreground hover:bg-secondary-foreground/10'
                            ]"
                        >
                            {{ ui.products || 'Products' }}
                        </button>

                        <!-- Packages Shortcut -->
                        <button 
                            v-if="props.packages?.length > 0"
                            @click="selectCategory('promos')"
                            :class="[
                                'shrink-0 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all',
                                activeCategory === 'promos' ? 'bg-primary text-primary-foreground shadow-sm' : 'bg-secondary text-muted-foreground hover:bg-secondary-foreground/10'
                            ]"
                        >
                            {{ ui.packages || 'Packages' }}
                        </button>

                        <button 
                            v-for="cat in props.categories" 
                            :key="cat.id"
                            @click="selectCategory(cat.id)"
                            :class="[
                                'shrink-0 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all',
                                activeCategory === cat.id ? 'bg-primary text-primary-foreground shadow-sm' : 'bg-secondary text-muted-foreground hover:bg-secondary-foreground/10'
                            ]"
                        >
                            {{ cat.name }}
                        </button>
                    </div>

                    <!-- Menu Grid -->
                    <div class="flex-1 overflow-y-auto p-4 lg:p-6 pb-32 space-y-8 bg-background/30 no-scrollbar">    
                        <!-- Active Offers Section (Promos & Packages) -->
                        <div v-if="combinedOffers.length > 0" class="space-y-3">
                            <div class="flex items-center justify-between px-1">
                                <h3 class="text-xs font-black uppercase tracking-[0.2em] text-primary flex items-center gap-2">
                                    <Tag class="w-4 h-4" />
                                    {{ ui.active_offers || 'Current Offers' }}
                                </h3>
                                <span class="text-[9px] font-black bg-primary/10 text-primary px-2 py-0.5 rounded-full uppercase tracking-widest">{{ combinedOffers.length }} {{ ui.deals || 'Deals' }}</span>
                            </div>
                            
                            <div class="flex gap-4 overflow-x-auto no-scrollbar pb-2">
                                <div 
                                    v-for="offer in combinedOffers" 
                                    :key="offer.carouselType === 'promo' ? `promo-${offer.promotionid}` : `pkg-${offer.packageid}`"
                                    @click="offer.carouselType === 'package' ? openPromoModal(offer) : null"
                                    :class="[
                                        'shrink-0 w-72 p-4 rounded-[2rem] border shadow-sm flex flex-col gap-3 relative overflow-hidden group transition-all',
                                        offer.carouselType === 'package' ? 'bg-primary border-primary/20 cursor-pointer hover:scale-[1.02]' : 'bg-white border-primary/20'
                                    ]"
                                >
                                    <!-- Badge -->
                                    <div :class="['absolute top-0 right-0 px-4 py-1.5 rounded-bl-2xl text-[8px] font-black uppercase tracking-widest', offer.carouselType === 'package' ? 'bg-white text-primary' : 'bg-primary text-primary-foreground']">
                                        {{ offer.carouselType === 'package' ? (ui.package || 'PACKAGE') : offer.type.replace('_', ' ') }}
                                    </div>

                                    <div class="flex items-start gap-4">
                                        <div :class="['w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform', offer.carouselType === 'package' ? 'bg-white/20 text-white' : 'bg-primary/10 text-primary']">
                                            <UtensilsCrossed v-if="offer.carouselType === 'package' || offer.type === 'BUY_X_GET_Y'" class="w-6 h-6" />
                                            <Tag v-else class="w-6 h-6" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <h4 :class="['text-xs font-black uppercase truncate tracking-tight', offer.carouselType === 'package' ? 'text-white' : 'text-primary']">{{ offer.name }}</h4>
                                            <p :class="['text-[9px] font-bold line-clamp-2 leading-relaxed mt-0.5', offer.carouselType === 'package' ? 'text-white/80' : 'text-muted-foreground']">
                                                {{ offer.carouselType === 'package' ? (offer.menus?.map(m => `${m.qty}x ${m.name}`).join(', ')) : offer.description }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Price / Requirements Footer -->
                                    <div :class="['mt-auto pt-3 border-t flex items-center justify-between', offer.carouselType === 'package' ? 'border-white/10' : 'border-secondary/50']">
                                        <div class="flex flex-col">
                                            <span :class="['text-[7px] font-black uppercase tracking-widest', offer.carouselType === 'package' ? 'text-white/60' : 'text-muted-foreground/60']">
                                                {{ offer.carouselType === 'package' ? (ui.special_price || 'Special Price') : (ui.requirement || 'Requirement') }}
                                            </span>
                                            <span v-if="offer.carouselType === 'package'" class="text-[11px] font-black text-white">{{ formatPrice(offer.price) }}</span>
                                            <span v-else-if="offer.min_purchase > 0" class="text-[9px] font-black text-primary">{{ ui.min || 'Min' }} {{ formatPrice(offer.min_purchase) }}</span>
                                            <span v-else-if="offer.type === 'BUY_X_GET_Y'" class="text-[9px] font-black text-primary">{{ ui.buy || 'Buy' }} {{ offer.buy_qty }}x {{ offer.buy_menu?.name }}</span>
                                            <span v-else class="text-[9px] font-black text-primary">{{ ui.no_minimum || 'No minimum' }}</span>
                                        </div>
                                        <div v-if="offer.carouselType === 'promo'" :class="['px-3 py-1 rounded-full text-[8px] font-black uppercase tracking-widest border', activeDiscount?.id === offer.promotionid ? 'bg-green-500 text-white border-green-500 shadow-md shadow-green-500/20' : 'bg-secondary text-muted-foreground border-secondary']">
                                            {{ activeDiscount?.id === offer.promotionid ? (ui.applied || 'Applied') : (ui.available || 'Available') }}
                                        </div>
                                        <div v-else class="p-1.5 rounded-xl bg-white text-primary shadow-lg">
                                            <Plus class="w-3 h-3" stroke-width="4" />
                                        </div>
                                    </div>
                                    
                                    <!-- Background Pattern -->
                                    <div class="absolute -right-4 -bottom-4 opacity-[0.05] group-hover:opacity-[0.1] transition-opacity">
                                        <Package class="w-24 h-24 rotate-12" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="relative group">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground transition-colors group-focus-within:text-primary" />
                                <input v-model="searchQuery" type="text" :placeholder="ui.search_menu || 'Search menu...'" class="w-full pl-10 pr-4 py-3 rounded-2xl bg-white border border-secondary/50 text-xs font-bold outline-none focus:ring-1 focus:ring-primary/20 transition-all shadow-sm" />
                            </div>
                            <div class="relative group">
                                <Barcode class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-primary transition-colors group-focus-within:text-primary" />
                                <input v-model="barcodeSearchInput" type="text" :placeholder="ui.scan_barcode || 'Scan or type barcode...'" @keyup.enter="onManualBarcodeSubmit" class="w-full pl-10 pr-12 py-3 rounded-2xl bg-primary/5 border border-primary/20 text-xs font-black uppercase tracking-widest outline-none focus:ring-1 focus:ring-primary transition-all shadow-sm" />
                                <button @click="startCamera" class="absolute right-2 top-1/2 -translate-y-1/2 p-2 text-primary hover:scale-110 transition-transform"><Camera class="w-5 h-5" /></button>
                            </div>
                        </div>

                        <!-- Products List -->
                        <section v-if="filteredProducts.length > 0" id="cat-products" class="space-y-4">        
                            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-primary flex items-center gap-2"><Package class="w-4 h-4" /> {{ ui.products || 'Products' }}</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
                                <button v-for="product in filteredProducts" :key="product.id" @click="addToCart(product)" class="flex flex-col bg-white border border-secondary/50 rounded-[1.5rem] lg:rounded-[2rem] p-3 lg:p-4 text-left transition-all relative group overflow-hidden shadow-sm hover:border-primary/30 hover:shadow-lg hover:scale-[1.02] active:scale-95">
                                    <div class="aspect-square rounded-xl lg:rounded-2xl bg-secondary mb-2 lg:mb-3 flex items-center justify-center relative overflow-hidden">
                                        <Package class="w-10 h-10 text-muted-foreground/20" />
                                        <div class="absolute inset-0 bg-primary/5 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center"><Barcode class="w-8 h-8 text-primary/40" /></div>
                                    </div>
                                    <h4 class="text-[10px] lg:text-xs font-black uppercase tracking-tight line-clamp-2 mb-1 group-hover:text-primary transition-colors">{{ product.name }}</h4>
                                    <p class="text-[10px] lg:text-xs font-black text-primary">{{ formatPrice(product.price) }}</p>
                                    <div class="absolute bottom-3 right-3 lg:bottom-4 lg:right-4 p-1.5 lg:p-2 rounded-lg lg:rounded-xl bg-primary text-primary-foreground opacity-0 lg:group-hover:opacity-100 transition-all translate-y-2 lg:group-hover:translate-y-0"><Plus class="w-3 h-3 lg:w-4 lg:h-4" /></div>
                                </button>
                            </div>
                        </section>

                        <!-- Packages List -->
                        <section v-if="filteredPromotions.length > 0" id="cat-promos" class="space-y-4">        
                            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-primary flex items-center gap-2"><Tag class="w-4 h-4" /> {{ ui.packages || 'Packages' }}</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
                                <button v-for="promo in filteredPromotions" :key="promo.packageid" @click="openPromoModal(promo)" class="flex flex-col bg-primary border border-primary/20 rounded-[1.5rem] lg:rounded-[2rem] p-3 lg:p-4 text-left transition-all relative group overflow-hidden shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-95">
                                    <div class="aspect-square rounded-xl lg:rounded-2xl bg-white/10 mb-2 lg:mb-3 flex items-center justify-center relative overflow-hidden">
                                        <UtensilsCrossed class="w-10 h-10 text-white/20 absolute -right-2 -bottom-2 rotate-12" />
                                        <Tag class="w-8 h-8 text-white" />
                                    </div>
                                    <h4 class="text-[10px] lg:text-xs font-black uppercase tracking-tight line-clamp-2 mb-1 text-white">{{ promo.name }}</h4>
                                    <p class="text-[10px] lg:text-xs font-black text-white/90">{{ formatPrice(promo.price) }}</p>
                                    <div class="absolute bottom-3 right-3 lg:bottom-4 lg:right-4 p-1.5 lg:p-2 rounded-lg lg:rounded-xl bg-white text-primary"><Plus class="w-3 h-3 lg:w-4 lg:h-4" /></div>
                                </button>
                            </div>
                        </section>

                        <section v-for="cat in filteredCategories" :key="cat.id" :id="`cat-${cat.id}`" class="space-y-4">
                            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-primary">{{ cat.name }}</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
                                <button v-for="menu in cat.menus" :key="menu.id" @click="menu.is_available ? openMenuModal(menu) : null" :class="['flex flex-col bg-card border rounded-[1.5rem] lg:rounded-[2rem] p-3 lg:p-4 text-left transition-all relative group overflow-hidden', menu.is_available ? 'border-secondary/50 hover:border-primary/30 hover:shadow-lg active:scale-95' : 'opacity-60 grayscale cursor-not-allowed border-secondary/20']">
                                    <div class="aspect-square rounded-xl lg:rounded-2xl bg-secondary mb-2 lg:mb-3 overflow-hidden relative">
                                        <img v-if="menu.image" :src="menu.image" class="w-full h-full object-cover" />
                                        <div v-else class="w-full h-full flex items-center justify-center text-muted-foreground/20 italic text-[8px] font-black uppercase">No Image</div>
                                        <div v-if="!menu.is_available" class="absolute inset-0 bg-black/40 flex flex-col items-center justify-center text-center px-2"><span class="text-[8px] lg:text-[10px] font-black text-white uppercase tracking-widest bg-destructive px-2 lg:px-3 py-1 rounded-full shadow-lg">{{ ui.sold_out || 'Sold Out' }}</span></div>
                                    </div>
                                    <h4 class="text-[10px] lg:text-xs font-black uppercase tracking-tight truncate mb-1 group-hover:text-primary transition-colors">{{ menu.name }}</h4>
                                    <p class="text-[10px] lg:text-xs font-black text-primary">{{ formatPrice(menu.price) }}</p>
                                    <div v-if="menu.is_available" class="absolute bottom-3 right-3 lg:bottom-4 lg:right-4 p-1.5 lg:p-2 rounded-lg lg:rounded-xl bg-primary text-primary-foreground opacity-0 lg:group-hover:opacity-100 transition-all translate-y-2 lg:group-hover:translate-y-0"><Plus class="w-3 h-3 lg:w-4 lg:h-4" /></div>
                                </button>
                            </div>
                        </section>
                    </div>
                </div>

                <!-- Tab Content: UNPAID ORDERS -->
                <div v-else class="flex-1 overflow-y-auto p-4 lg:p-6 pb-32 bg-background/30 no-scrollbar">
                    <div v-if="props.unpaidOrders.length === 0" class="h-full flex flex-col items-center justify-center text-muted-foreground/40 space-y-2">
                        <ListTodo class="w-12 h-12" />
                        <p class="text-[10px] font-black uppercase tracking-widest">{{ ui.all_orders_paid || 'All orders paid' }}</p>
                    </div>
                    <div v-else class="grid gap-3 lg:gap-4">
                        <div v-for="order in props.unpaidOrders" :key="order.id" @click="selectUnpaidOrder(order)" class="flex items-center justify-between p-3 lg:p-4 rounded-2xl lg:rounded-3xl bg-card border border-secondary/50 shadow-sm hover:shadow-md transition-all cursor-pointer group/card">
                            <div class="flex items-center gap-3 lg:gap-4 min-w-0">
                                <div class="w-10 h-10 lg:w-12 lg:h-12 rounded-xl lg:rounded-2xl bg-destructive/10 text-destructive flex items-center justify-center font-black group-hover/card:bg-primary/10 group-hover/card:text-primary transition-colors shrink-0">{{ order.tableName }}</div>
                                <div class="min-w-0">
                                    <h4 class="text-xs lg:text-sm font-black uppercase tracking-tight group-hover/card:text-primary transition-colors truncate">{{ order.name || (ui.anonymous || 'Anonymous') }}</h4>
                                    <p class="text-[8px] lg:text-[10px] font-bold text-muted-foreground uppercase tracking-widest flex gap-2 truncate"><span>{{ new Date(order.orderTime).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}</span><span class="hidden sm:inline">&bull;</span><span class="hidden sm:inline">{{ order.orderType === 'Take Away' ? (ui.takeaway || 'Takeaway') : (ui.eat_here || 'Eat Here') }}</span></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 lg:gap-6 shrink-0 ml-2">
                                <span class="text-sm lg:text-lg font-black text-primary tracking-tighter">{{ formatPrice(order.totalPrice) }}</span>
                                <div class="p-2 lg:p-3 rounded-lg lg:rounded-xl bg-secondary text-primary group-hover/card:bg-primary group-hover/card:text-primary-foreground transition-all"><ChevronRight class="w-4 h-4 lg:w-5 lg:h-5" /></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Camera Scanner Modal -->
        <Modal :show="showCameraModal" title="Camera Scanner" @close="stopCamera">
            <div class="p-6 space-y-6">
                <div class="relative aspect-square w-full max-w-sm mx-auto overflow-hidden rounded-[2rem] bg-black border-4 border-primary/20 shadow-2xl">
                    <div id="reader" class="w-full h-full"></div>
                </div>
                <div class="flex flex-col gap-3">
                    <Button variant="secondary" @click="showDeviceListModal = true" class="w-full rounded-2xl font-black uppercase tracking-widest text-[10px] h-12 gap-2">
                        <Building class="w-4 h-4" /> Scan using other device
                    </Button>
                    <Button variant="ghost" @click="stopCamera" class="gap-2 rounded-xl font-black uppercase tracking-widest text-[10px]">
                        <CameraOff class="w-4 h-4" /> Stop Scanning
                    </Button>
                </div>
            </div>
        </Modal>

        <!-- Device List Modal -->
        <Modal :show="showDeviceListModal" title="Select Scanner" @close="showDeviceListModal = false" class="z-[100]">
            <div class="p-6 space-y-6">
                <!-- Discovery Status -->
                <div class="flex items-center justify-between px-2">
                    <div class="flex items-center gap-2">
                        <div :class="['w-2 h-2 rounded-full', echoConnected ? 'bg-green-500 animate-pulse' : 'bg-red-500']"></div>
                        <span class="text-[9px] font-black uppercase tracking-widest text-muted-foreground">
                            {{ echoConnected ? 'Network Online' : 'Connecting to Network...' }}
                        </span>
                    </div>
                    <button 
                        @click="manualRefresh" 
                        class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-secondary text-primary hover:bg-primary hover:text-white transition-all"
                    >
                        <RefreshCw :class="['w-3 h-3', isRequestingRemote ? 'animate-spin' : '']" />
                        <span class="text-[9px] font-black uppercase tracking-widest">Refresh Devices</span>
                    </button>
                </div>

                <div class="p-4 bg-primary/5 rounded-[2rem] border border-primary/10 flex items-center gap-6">
                    <div class="bg-white p-2 rounded-2xl shadow-sm shrink-0">
                        <QrcodeVue :value="scannerUrl" :size="80" level="H" />
                    </div>
                    <div>
                        <h4 class="text-[11px] font-black uppercase text-primary tracking-tight">Guest Scanner Setup</h4>
                        <p class="text-[9px] font-bold text-muted-foreground leading-relaxed mt-1">Scan this with any mobile device to use it as a remote scanner.</p>
                        <div class="mt-2 inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-secondary/50 border border-secondary text-[8px] font-black uppercase tracking-tighter text-muted-foreground">
                            <span>Room: {{ networkId }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="(combinedDevices || []).length === 0" class="text-center py-8 space-y-3">
                    <div class="w-16 h-16 bg-secondary rounded-full flex items-center justify-center mx-auto text-muted-foreground/30"><UsersIcon class="w-8 h-8" /></div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-muted-foreground">No active devices found</p>
                </div>
                
                <div v-else class="grid gap-3">
                    <button v-for="device in combinedDevices" :key="device.id" @click="requestRemoteCamera(device)" :disabled="isRequestingRemote" class="flex items-center justify-between p-4 rounded-2xl border border-secondary hover:border-primary/30 hover:bg-primary/5 transition-all text-left">
                        <div class="flex items-center gap-3">
                            <div :class="['w-10 h-10 rounded-xl flex items-center justify-center', device.type === 'guest' ? 'bg-amber-100 text-amber-600' : 'bg-primary/10 text-primary']">
                                <Smartphone v-if="device.type === 'guest'" class="w-6 h-6" />
                                <UserCircle v-else class="w-6 h-6" />
                            </div>
                            <div>
                                <span class="text-xs font-black uppercase tracking-tight text-primary">{{ device.name }}</span>
                                <p class="text-[8px] font-bold uppercase tracking-widest opacity-60">{{ device.type === 'guest' ? 'Guest Device' : 'Staff Device' }}</p>
                            </div>
                        </div>
                        <ChevronRight class="w-4 h-4 text-primary" />
                    </button>
                </div>

                <div v-if="isRequestingRemote" class="p-4 bg-primary/5 rounded-2xl border border-primary/10 flex items-center gap-4 animate-pulse">
                    <Utensils class="w-5 h-5 text-primary animate-spin" />
                    <p class="text-[10px] font-black uppercase tracking-widest text-primary">Waiting for remote device...</p>
                </div>

                <div class="flex justify-end">
                    <Button variant="ghost" @click="showDeviceListModal = false" class="rounded-xl font-bold uppercase tracking-widest text-[10px]">Cancel</Button>
                </div>
            </div>
        </Modal>

        <!-- Addons Modal -->
        <Modal :show="showAddonModal" :title="ui.add_menu_to_order || 'Add Menu to Order'" @close="showAddonModal = false">
            <div class="space-y-6">
                <!-- Menu Info & Quantity Selector -->
                <div class="p-6 bg-primary/5 rounded-[2rem] border border-primary/10 space-y-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-lg font-black uppercase text-primary tracking-tight">{{ selectedMenuForAddons?.name }}</h3>
                            <p class="text-xs font-bold text-muted-foreground">{{ formatPrice(selectedMenuForAddons?.price) }} / {{ ui.portions || 'portion' }}</p>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] font-black uppercase tracking-widest text-primary/60">{{ ui.stock_limit || 'Stock Limit' }}</span>
                            <p class="text-xs font-black text-primary">{{ selectedMenuForAddons?.max_quantity }} {{ ui.portions || 'portions' }}</p>
                        </div>
                    </div>

                    <!-- Portions Selector -->
                    <div class="flex items-center justify-between pt-4 border-t border-primary/10">
                        <span class="text-xs font-black uppercase tracking-widest text-primary">{{ ui.number_of_portions || 'Number of Portions' }}</span>
                        <div class="flex items-center gap-6 bg-background/50 rounded-2xl p-2 border border-primary/10">
                            <button
                                @click="selectedQuantity > 1 ? selectedQuantity-- : null"
                                class="p-2 rounded-xl bg-card border border-secondary hover:text-primary transition-all disabled:opacity-30"
                                :disabled="selectedQuantity <= 1"
                            >
                                <Minus class="w-4 h-4" stroke-width="3" />
                            </button>
                            <span class="text-xl font-black w-8 text-center text-primary">{{ selectedQuantity }}</span>
                            <button
                                @click="selectedQuantity < selectedMenuForAddons?.max_quantity ? selectedQuantity++ : null"
                                class="p-2 rounded-xl bg-primary text-primary-foreground hover:scale-110 transition-all disabled:opacity-30 shadow-lg shadow-primary/20"
                                :disabled="selectedQuantity >= selectedMenuForAddons?.max_quantity"
                            >
                                <Plus class="w-4 h-4" stroke-width="3" />
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="selectedMenuForAddons?.addons?.length" class="space-y-3">
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground ml-1">{{ ui.select_addons_optional || 'Select Addons (Optional)' }}</p>
                    <div class="grid grid-cols-1 gap-3 max-h-[300px] overflow-y-auto no-scrollbar pr-1">        
                        <button
                            v-for="addon in selectedMenuForAddons?.addons"
                            :key="addon.addonid"
                            @click="addon.is_available ? toggleAddon(addon) : null"
                            :class="[
                                'flex items-center justify-between p-4 rounded-2xl border transition-all',      
                                selectedAddons.some(a => a.addonid === addon.addonid) ? 'bg-primary border-primary text-primary-foreground' : 'bg-card border-secondary text-muted-foreground',
                                !addon.is_available ? 'opacity-40 grayscale cursor-not-allowed' : ''
                            ]"
                        >
                            <div class="flex items-center gap-3">
                                <Plus class="w-4 h-4" />
                                <span class="text-xs font-black uppercase tracking-widest">{{ addon.name }}</span>
                                <span v-if="!addon.is_available" class="text-[8px] font-black bg-destructive text-white px-2 py-0.5 rounded-full ml-2">{{ ui.sold_out || 'SOLD OUT' }}</span>
                            </div>
                            <span class="text-xs font-black">{{ formatPrice(addon.price) }}</span>
                        </button>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-6 border-t border-secondary/50">
                    <Button variant="ghost" @click="showAddonModal = false" class="rounded-xl font-bold uppercase tracking-widest text-[10px]">{{ ui.cancel || 'Cancel' }}</Button>
                    <Button @click="confirmAddons" class="px-8 h-12 rounded-2xl shadow-xl shadow-primary/30 font-black uppercase tracking-widest text-xs gap-2">
                        {{ ui.add_to_order || 'Add to Order' }}
                        <span class="px-2 py-0.5 rounded-md bg-white/20 text-[10px] font-black">{{ formatPrice((selectedMenuForAddons?.price + selectedAddons.reduce((sum, a) => sum + a.price, 0)) * selectedQuantity) }}</span>
                    </Button>
                </div>
            </div>
        </Modal>

        <!-- Promo Detail Modal for Cashier -->
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
                                <p v-if="menu.addonname" class="text-[9px] font-bold text-primary uppercase tracking-widest">+ {{ menu.addonname }}</p>
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

                    <Button @click="addToCart(selectedPromo); showPromoModal = false" class="w-full h-14 rounded-2xl text-xs font-black uppercase tracking-[0.2em] shadow-xl shadow-primary/20 gap-3">
                        <ShoppingBag class="w-5 h-5" />
                        {{ ui.add_to_order || 'Add to Order' }}
                    </Button>
                </div>
            </div>
        </Modal>

    </AppLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

#reader :deep(button) {
    background-color: white !important;
    color: var(--primary) !important;
    border: none !important;
    border-radius: 0.75rem !important;
    padding: 0.5rem 1rem !important;
    font-size: 10px !important;
    font-weight: 900 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.1em !important;
    cursor: pointer !important;
    transition: all 0.2s !important;
    margin: 5px !important;
}

#reader :deep(button:hover) {
    opacity: 0.9 !important;
    transform: scale(1.05) !important;
}

#reader :deep(select) {
    background-color: rgba(255, 255, 255, 0.1) !important;
    color: white !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    border-radius: 0.75rem !important;
    padding: 0.4rem !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    outline: none !important;
    margin: 5px !important;
}

#reader :deep(select option) {
    background-color: #1a1a1a !important;
    color: white !important;
}

#reader :deep(span), 
#reader :deep(label), 
#reader :deep(div) {
    color: white !important;
    font-size: 11px !important;
    font-weight: 800 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
}

#reader :deep(img[alt="Info icon"]),
#reader :deep(img[alt="Camera icon"]) {
    filter: invert(1) brightness(2) !important;
}

#reader :deep(video) {
    transform: scaleX(-1);
    -webkit-transform: scaleX(-1);
}
</style>
