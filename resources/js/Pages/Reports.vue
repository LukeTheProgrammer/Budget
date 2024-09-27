<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { parse, format } from '@formkit/tempo'

const props = defineProps({
  transactions: {
    type: Array,
    default: () => [],
  },
})

</script>

<template>
    <Head title="Reports" />

    <AppLayout>
        <template #header>
            <h1>Reports</h1>
        </template>

        <div class="card p-4">
            Reports
        </div>
    </AppLayout>
</template>
