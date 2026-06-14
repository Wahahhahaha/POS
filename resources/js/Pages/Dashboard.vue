<script setup>
import { Head } from '@inertiajs/vue3'
import Card from '@/Components/ui/Card.vue'
</script>

<template>
    <Head title="Dashboard" />
    <div class="space-y-6">
        <h1 class="text-3xl font-bold">Dashboard Staff</h1>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <Card>
                <template #header><h3 class="font-bold">Total Orders</h3></template>
                <div class="text-2xl font-bold">0</div>
            </Card>
            <Card>
                <template #header><h3 class="font-bold">Today Revenue</h3></template>
                <div class="text-2xl font-bold">Rp 0</div>
            </Card>
            <Card>
                <template #header><h3 class="font-bold">Active Staff</h3></template>
                <div class="text-2xl font-bold">1</div>
            </Card>
        </div>
    </div>
</template>
