<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <main>
        <div class="container-fluid">
            <div class="container">
                <slot />
            </div>
        </div>
    </main>
</template>

<style>
body {
    background-image: url("https://laravel.com/assets/img/welcome/background.svg");
    background-size: cover;
}
</style>
