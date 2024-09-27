<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="d-flex justify-content-center my-5">
        <div class="card py-3" style="width: 24rem;">
            <div class="card-img-top d-flex justify-content-center">
                <Link href="/">
                    <ApplicationLogo style="width: 10rem;" />
                </Link>
            </div>

            <div class="card-body">
                <slot />
            </div>
        </div>
    </div>
</template>
