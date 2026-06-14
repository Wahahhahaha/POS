<script setup>
import { router, usePage } from '@inertiajs/vue3'

const page = usePage()

const changeLanguage = (locale) => {
    router.post(route('language.switch', locale), {}, {
        preserveScroll: true,
        preserveState: true
    })
}
</script>

<template>
    <div class="flex items-center gap-1 bg-secondary/50 p-1 rounded-xl border border-secondary/50 backdrop-blur-sm">
        <button 
            @click="changeLanguage('en')"
            :class="[
                'px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all',
                $page.props.locale === 'en' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:bg-secondary hover:text-primary'
            ]"
        >
            EN
        </button>
        <button 
            @click="changeLanguage('id')"
            :class="[
                'px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all',
                $page.props.locale === 'id' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:bg-secondary hover:text-primary'
            ]"
        >
            ID
        </button>
    </div>
</template>
