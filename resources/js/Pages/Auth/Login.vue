<script setup>
import { useForm, Head, Link, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import Button from '@/Components/ui/Button.vue'
import Input from '@/Components/ui/Input.vue'
import Card from '@/Components/ui/Card.vue'
import Footer from '@/Components/Footer.vue'
import LanguageToggle from '@/Components/shared/LanguageToggle.vue'
import { Lock, User, LogIn, Eye, EyeOff, Mail } from 'lucide-vue-next'

const page = usePage()
const props = defineProps({
    errors: Object,
    auth: Object,
    ziggy: Object,
    system: Object,
    translations: Object,
})

const ui = computed(() => page.props.translations?.ui || {})

const form = useForm({
    email: '',
    password: '',
    remember: false,
    latitude: null,
    longitude: null,
})

const showPassword = ref(false)

const getLocationAndSubmit = () => {
    form.processing = true
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                form.latitude = position.coords.latitude
                form.longitude = position.coords.longitude
                // Save to localStorage so future Inertia requests can attach them via Axios headers
                localStorage.setItem('pos_user_lat', form.latitude)
                localStorage.setItem('pos_user_lng', form.longitude)
                submitForm()
            },
            (error) => {
                console.warn('Geolocation error:', error.message)
                submitForm()
            },
            { timeout: 5000, maximumAge: 60000 }
        )
    } else {
        submitForm()
    }
}

const submitForm = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <div class="min-h-screen flex flex-col bg-background relative overflow-hidden">
        <Head :title="ui.staff_login || 'Login Staff'" />

        <!-- Floating Language Toggle -->
        <div class="fixed top-6 right-6 z-50">
            <LanguageToggle />
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex items-center justify-center p-4 sm:p-8">
            <div class="w-full max-w-sm space-y-8 relative z-10">
                <div class="text-center space-y-2">
                    <div class="inline-flex items-center justify-center w-24 h-24 rounded-3xl bg-white border-2 border-primary/5 shadow-xl shadow-primary/5 mb-4 overflow-hidden transition-all duration-300">
                        <img 
                            v-if="$page.props.system?.logo" 
                            :src="$page.props.system.logo" 
                            :alt="$page.props.system.name"
                            class="w-full h-full object-cover"
                        />
                        <div v-else class="w-full h-full bg-primary/10 flex items-center justify-center text-primary">
                            <LogIn class="w-10 h-10" />
                        </div>
                    </div>
                    <h2 class="text-4xl font-black tracking-tighter text-primary uppercase">{{ $page.props.system.name }}</h2>
                    <p class="text-sm font-medium text-muted-foreground uppercase tracking-widest">{{ ui.staff_access_portal || 'Staff Access Portal' }}</p>
                </div>

                <div class="space-y-6">
                    <form @submit.prevent="getLocationAndSubmit" class="space-y-5">
                        <div class="space-y-2">
                            <label class="text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground ml-1">
                                {{ ui.email_address || 'Email Address' }}
                            </label>
                            <Input 
                                v-model="form.email" 
                                type="email" 
                                placeholder="staff@pos.com"
                                class="h-12 bg-secondary/50 border-transparent focus:bg-white transition-all text-lg rounded-xl shadow-none"
                                required
                                autocomplete="username"
                            />
                            <div v-if="form.errors.email" class="text-xs text-destructive mt-1 font-semibold">{{ form.errors.email }}</div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground ml-1">
                                {{ ui.password || 'Password' }}
                            </label>
                            <div class="relative group/pass">
                                <Input 
                                    v-model="form.password" 
                                    :type="showPassword ? 'text' : 'password'" 
                                    placeholder="••••••••"
                                    class="h-12 bg-secondary/50 border-transparent focus:bg-white transition-all text-lg rounded-xl shadow-none pr-12"
                                    required
                                    autocomplete="current-password"
                                />
                                <button 
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 p-2 text-muted-foreground hover:text-primary transition-colors"
                                >
                                    <Eye v-if="!showPassword" class="w-5 h-5" />
                                    <EyeOff v-else class="w-5 h-5" />
                                </button>
                            </div>
                            <div v-if="form.errors.password" class="text-xs text-destructive mt-1 font-semibold">{{ form.errors.password }}</div>
                        </div>

                        <div class="flex items-center justify-between px-1">
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input 
                                    type="checkbox" 
                                    v-model="form.remember"
                                    class="rounded-md border-input bg-secondary text-primary focus:ring-primary h-4 w-4 transition-colors"
                                />
                                <span class="text-xs font-bold text-muted-foreground group-hover:text-primary transition-colors uppercase tracking-wider">{{ ui.remember_me || 'Remember Me' }}</span>
                            </label>

                            <Link 
                                :href="route('password.request')"
                                class="text-xs font-bold text-muted-foreground hover:text-primary transition-colors uppercase tracking-wider"
                            >
                                {{ ui.forgot_password || 'Forgot Password?' }}
                            </Link>
                        </div>

                        <Button 
                            type="submit" 
                            class="w-full h-14 text-base font-black uppercase tracking-widest rounded-2xl shadow-lg shadow-primary/20 transition-all hover:scale-[1.02] active:scale-[0.98]"
                            :disabled="form.processing"
                        >
                            <span v-if="form.processing">{{ ui.processing || 'Processing...' }}</span>
                            <span v-else>{{ ui.sign_in_now || 'Sign In Now' }}</span>
                        </Button>
                    </form>

                    <div class="space-y-6 pt-2">
                        <div class="relative">
                            <div class="absolute inset-0 flex items-center">
                                <span class="w-full border-t border-secondary/50"></span>
                            </div>
                            <div class="relative flex justify-center text-[10px] uppercase font-black tracking-widest">
                                <span class="bg-background px-4 text-muted-foreground/60">{{ ui.or_continue_with || 'Or continue with' }}</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <a 
                                :href="route('login.google')"
                                class="flex items-center justify-center gap-3 h-12 bg-white border border-secondary/50 rounded-xl hover:bg-secondary/20 transition-all group"
                            >
                                <svg class="w-5 h-5 group-hover:scale-110 transition-transform" viewBox="0 0 24 24">
                                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.18 1-.78 1.85-1.63 2.51v2.08h2.64c1.55-1.42 2.43-3.52 2.43-5.6z" fill="#4285F4"/>
                                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-2.64-2.08c-.5.33-1.14.53-2.14.53-1.85 0-3.41-1.25-3.97-2.93H7.82v2.1C8.96 20.48 10.37 23 12 23z" fill="#34A853"/>
                                    <path d="M8.03 15.86c-.14-.42-.22-.87-.22-1.34s.08-.92.22-1.34V11.1H7.82C7.31 12.1 7 13.5 7 14.5s.31 2.4.82 3.4l.21-1.04z" fill="#FBBC05"/>
                                    <path d="M12 5.07c1.62 0 3.07.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 10.37 1 8.96 3.52 7.82 5.58l2.53 1.96c.56-1.68 2.12-2.93 3.97-2.93z" fill="#EA4335"/>
                                </svg>
                                <span class="text-[10px] font-black uppercase tracking-wider text-primary">Google</span>
                            </a>
                            
                            <Link 
                                :href="route('otp.login')" 
                                class="flex items-center justify-center gap-3 h-12 bg-white border border-secondary/50 rounded-xl hover:bg-secondary/20 transition-all group"
                            >
                                <div class="p-1 rounded-lg bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition-colors">
                                    <Mail class="w-3.5 h-3.5" />
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-wider text-primary">OTP Email</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Decorative Background Elements -->
        <div class="absolute top-[-10%] -left-[10%] w-[40%] h-[40%] bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute bottom-[-10%] -right-[10%] w-[40%] h-[40%] bg-secondary rounded-full blur-3xl pointer-events-none"></div>

        <Footer />
    </div>
</template>
