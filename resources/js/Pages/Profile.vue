<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import Card from '@/Components/ui/Card.vue'
import Input from '@/Components/ui/Input.vue'
import Button from '@/Components/ui/Button.vue'
import { User, Mail, Phone, Lock, Save, ShieldCheck, Camera, CameraOff, CheckCircle } from '@lucide/vue'
import { computed, ref, onUnmounted, nextTick } from 'vue'
import * as faceapi from 'face-api.js'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const page = usePage()
const user = page.props.auth.user
const ui = computed(() => page.props.translations?.ui || {})

const profileForm = useForm({
    name: user.name,
    email: user.email,
    phonenumber: user.phonenumber || '',
})

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const updateProfile = () => {
    profileForm.put(route('user-profile-information.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Optional: toast notification
        }
    })
}

const updatePassword = () => {
    passwordForm.put(route('user-password.update'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    })
}

// Face Enrollment Logic
const videoEl = ref(null)
const isCameraActive = ref(false)
const isModelsLoaded = ref(false)
const enrollmentStatus = ref('')
const hasFaceData = ref(!!user.face_descriptor)
let stream = null

const startCamera = async () => {
    enrollmentStatus.value = 'Loading AI Models...'
    try {
        if (!isModelsLoaded.value) {
            const modelPath = '/POS/models' // Ensure correct path on the server
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(modelPath),
                faceapi.nets.faceLandmark68Net.loadFromUri(modelPath),
                faceapi.nets.faceRecognitionNet.loadFromUri(modelPath)
            ])
            isModelsLoaded.value = true
        }
        
        enrollmentStatus.value = 'Starting camera...'
        stream = await navigator.mediaDevices.getUserMedia({ video: true })
        isCameraActive.value = true
        
        await nextTick() // Wait for DOM to update after setting isCameraActive to true
        
        if (videoEl.value) {
            videoEl.value.srcObject = stream
            enrollmentStatus.value = 'Look at the camera and click Scan'
        } else {
            throw new Error('Video element not found in DOM')
        }
    } catch (err) {
        console.error(err)
        enrollmentStatus.value = 'Camera error: ' + err.message
    }
}

const stopCamera = () => {
    if (stream) {
        stream.getTracks().forEach(track => track.stop())
        stream = null
    }
    isCameraActive.value = false
    enrollmentStatus.value = ''
}

const scanFace = async () => {
    if (!videoEl.value) return
    
    enrollmentStatus.value = 'Detecting face...'
    
    const detection = await faceapi.detectSingleFace(videoEl.value, new faceapi.TinyFaceDetectorOptions())
                                .withFaceLandmarks()
                                .withFaceDescriptor()
                                
    if (detection) {
        enrollmentStatus.value = 'Face detected! Saving...'
        
        try {
            await axios.post(route('profile.face.update'), {
                face_descriptor: Array.from(detection.descriptor)
            })
            
            hasFaceData.value = true
            enrollmentStatus.value = 'Face data saved successfully!'
            stopCamera()
            router.reload({ only: ['auth'] })
        } catch (err) {
            enrollmentStatus.value = 'Failed to save face data.'
            console.error(err)
        }
    } else {
        enrollmentStatus.value = 'No face detected. Try moving closer or adjusting lighting.'
    }
}

onUnmounted(() => {
    stopCamera()
})

</script>

<template>
    <Head :title="ui.my_profile || 'My Profile'" />

    <div class="max-w-7xl mx-auto space-y-8 lg:space-y-12 pb-20 px-2 lg:px-0">
        <div>
            <h1 class="text-2xl lg:text-3xl font-black tracking-tight text-primary uppercase">{{ ui.my_profile || 'My Profile' }}</h1>
            <p class="text-muted-foreground text-[10px] lg:text-sm font-medium uppercase tracking-widest mt-1">{{ ui.manage_account_info || 'Manage your account information' }}</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
            <div class="lg:col-span-1 space-y-2 lg:space-y-4">
                <h2 class="text-sm lg:text-lg font-black text-primary uppercase tracking-tight">{{ ui.profile_information || 'Profile Information' }}</h2>
                <p class="text-[9px] lg:text-xs text-muted-foreground font-medium leading-relaxed">
                    {{ ui.profile_instruction || 'Update your account\'s profile information, email address, and phone number.' }}
                </p>
            </div>

            <Card class="lg:col-span-2 border-none shadow-sm bg-card/50">
                <form @submit.prevent="updateProfile" class="space-y-4 lg:space-y-6 p-4 lg:p-6">
                    <div class="space-y-1.5 lg:space-y-2">
                        <label class="text-[9px] lg:text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground ml-1">{{ ui.full_name || 'Full Name' }}</label>
                        <div class="relative">
                            <User class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                            <Input v-model="profileForm.name" class="pl-10 h-11 lg:h-11 bg-background text-[13px] lg:text-sm" />
                        </div>
                        <div v-if="profileForm.errors.name" class="text-xs text-destructive font-bold">{{ profileForm.errors.name }}</div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-6">
                        <div class="space-y-1.5 lg:space-y-2">
                            <label class="text-[9px] lg:text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground ml-1">{{ ui.email_address || 'Email Address' }}</label>
                            <div class="relative">
                                <Mail class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                                <Input v-model="profileForm.email" type="email" class="pl-10 h-11 lg:h-11 bg-background text-[13px] lg:text-sm" />
                            </div>
                            <div v-if="profileForm.errors.email" class="text-xs text-destructive font-bold">{{ profileForm.errors.email }}</div>
                        </div>

                        <div class="space-y-1.5 lg:space-y-2">
                            <label class="text-[9px] lg:text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground ml-1">{{ ui.phone_number || 'Phone Number' }}</label>
                            <div class="relative">
                                <Phone class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                                <Input v-model="profileForm.phonenumber" type="tel" placeholder="e.g. 08..." class="pl-10 h-11 lg:h-11 bg-background text-[13px] lg:text-sm" />
                            </div>
                            <div v-if="profileForm.errors.phonenumber" class="text-xs text-destructive font-bold">{{ profileForm.errors.phonenumber }}</div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 mt-6 border-t border-secondary/50">
                        <Button type="submit" class="w-full sm:w-auto gap-2 px-8 lg:px-8 h-12 lg:h-11 rounded-2xl lg:rounded-xl shadow-lg shadow-primary/20 font-black tracking-widest text-[10px] uppercase" :disabled="profileForm.processing">
                            <Save class="w-4 h-4" />
                            {{ ui.save_changes || 'Save Changes' }}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>

        <div class="h-px bg-secondary/50 my-8 lg:my-12"></div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8 pb-12">
            <div class="lg:col-span-1 space-y-2 lg:space-y-4">
                <h2 class="text-sm lg:text-lg font-black text-primary uppercase tracking-tight">Face Enrollment</h2>
                <p class="text-[9px] lg:text-xs text-muted-foreground font-medium leading-relaxed">
                    Register your face for faster and more secure logins, attendance, and high-security verifications.
                </p>
            </div>

            <Card class="lg:col-span-2 border-none shadow-sm bg-card/50 overflow-hidden relative">
                <div class="p-6 lg:p-8 space-y-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center" :class="hasFaceData ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'">
                            <CheckCircle v-if="hasFaceData" class="w-6 h-6" />
                            <Camera v-else class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="font-black text-lg uppercase tracking-tight" :class="hasFaceData ? 'text-primary' : ''">
                                {{ hasFaceData ? 'Face Registered' : 'No Face Data' }}
                            </h3>
                            <p class="text-xs font-bold text-muted-foreground">
                                {{ hasFaceData ? 'Your face is ready to be used for verification.' : 'Click below to register your face.' }}
                            </p>
                        </div>
                    </div>

                    <div v-if="isCameraActive" class="space-y-4">
                        <div class="relative max-w-sm mx-auto aspect-square bg-black rounded-3xl overflow-hidden shadow-2xl border-4 border-primary/20">
                            <video ref="videoEl" autoplay muted playsinline class="w-full h-full object-cover"></video>
                            
                            <!-- Overlay UI -->
                            <div class="absolute inset-0 pointer-events-none flex flex-col items-center justify-center">
                                <div class="w-48 h-48 border-2 border-primary/50 rounded-full relative animate-pulse">
                                    <div class="absolute -top-1 -left-1 w-6 h-6 border-t-4 border-l-4 border-primary rounded-tl-full"></div>
                                    <div class="absolute -top-1 -right-1 w-6 h-6 border-t-4 border-r-4 border-primary rounded-tr-full"></div>
                                    <div class="absolute -bottom-1 -left-1 w-6 h-6 border-b-4 border-l-4 border-primary rounded-bl-full"></div>
                                    <div class="absolute -bottom-1 -right-1 w-6 h-6 border-b-4 border-r-4 border-primary rounded-br-full"></div>
                                </div>
                            </div>
                        </div>
                        
                        <p class="text-center text-xs font-bold text-muted-foreground animate-pulse">{{ enrollmentStatus }}</p>

                        <div class="flex justify-center gap-4">
                            <Button @click="stopCamera" variant="ghost" class="gap-2 font-black uppercase tracking-widest text-[10px]">
                                <CameraOff class="w-4 h-4" /> Cancel
                            </Button>
                            <Button @click="scanFace" class="gap-2 px-8 shadow-lg shadow-primary/20 rounded-xl font-black uppercase tracking-widest text-[10px]">
                                <Camera class="w-4 h-4" /> Scan Face
                            </Button>
                        </div>
                    </div>
                    <div v-show="!isCameraActive" class="flex justify-end pt-4 mt-6 border-t border-secondary/50">
                        <Button @click="startCamera" variant="secondary" class="w-full sm:w-auto gap-2 px-8 h-12 lg:h-11 rounded-2xl lg:rounded-xl text-primary font-black uppercase tracking-widest text-[10px]">
                            <Camera class="w-4 h-4" />
                            {{ hasFaceData ? 'Update Face Data' : 'Register Face' }}
                        </Button>
                    </div>
                </div>
            </Card>
        </div>

        <div class="h-px bg-secondary/50 my-8 lg:my-12"></div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8 pb-12">
            <div class="lg:col-span-1 space-y-2 lg:space-y-4">
                <h2 class="text-sm lg:text-lg font-black text-primary uppercase tracking-tight">{{ ui.security || 'Security' }}</h2>
                <p class="text-[9px] lg:text-xs text-muted-foreground font-medium leading-relaxed">
                    {{ ui.security_instruction || 'Ensure your account is using a long, random password to stay secure.' }}
                </p>
            </div>

            <Card class="lg:col-span-2 border-none shadow-sm bg-card/50">
                <form @submit.prevent="updatePassword" class="space-y-4 lg:space-y-6 p-4 lg:p-6">
                    <div class="space-y-1.5 lg:space-y-2">
                        <label class="text-[9px] lg:text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground ml-1">{{ ui.current_password || 'Current Password' }}</label>
                        <Input v-model="passwordForm.current_password" type="password" class="h-11 lg:h-11 bg-background text-[13px] lg:text-sm" autocomplete="current-password" />
                        <div v-if="passwordForm.errors.current_password" class="text-xs text-destructive font-bold">{{ passwordForm.errors.current_password }}</div>
                    </div>

                    <div class="space-y-1.5 lg:space-y-2">
                        <label class="text-[9px] lg:text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground ml-1">{{ ui.new_password || 'New Password' }}</label>
                        <Input v-model="passwordForm.password" type="password" class="h-11 lg:h-11 bg-background text-[13px] lg:text-sm" autocomplete="new-password" />
                        <div v-if="passwordForm.errors.password" class="text-xs text-destructive font-bold">{{ passwordForm.errors.password }}</div>
                    </div>

                    <div class="space-y-1.5 lg:space-y-2">
                        <label class="text-[9px] lg:text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground ml-1">{{ ui.confirm_password || 'Confirm Password' }}</label>
                        <Input v-model="passwordForm.password_confirmation" type="password" class="h-11 lg:h-11 bg-background text-[13px] lg:text-sm" autocomplete="new-password" />
                        <div v-if="passwordForm.errors.password_confirmation" class="text-xs text-destructive font-bold">{{ passwordForm.errors.password_confirmation }}</div>
                    </div>

                    <div class="flex justify-end pt-4 mt-6 border-t border-secondary/50">
                        <Button type="submit" variant="secondary" class="w-full sm:w-auto gap-2 px-8 lg:px-8 h-12 lg:h-11 rounded-2xl lg:rounded-xl text-primary font-black uppercase tracking-widest text-[10px]" :disabled="passwordForm.processing">
                            <ShieldCheck class="w-4 h-4" />
                            {{ ui.update_password || 'Update Password' }}
                        </Button>
                    </div>
                </form>

                </Card>

                </div>

                </div>

                </template>

                <style scoped>
                video {
                transform: scaleX(-1);
                -webkit-transform: scaleX(-1);
                }
                </style>
