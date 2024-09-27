<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div>
        <nav class="navbar navbar-expand-sm bg-dark border-bottom border-body" data-bs-theme="dark">
            <div class="container-fluid">
                <a class="navbar-brand" href="/">Budget</a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <Link :href="route('dashboard')" class="nav-link">
                                Dashboard
                            </Link>
                        </li>
                        <!-- <li class="nav-item">
                            <a class="nav-link" href="#">Features</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">Pricing</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link disabled" aria-disabled="true">Disabled</a>
                        </li> -->
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Page Heading -->
        <header v-if="$slots.header">
            <div class="container py-4">
                <slot name="header" />
            </div>
        </header>

        <!-- Page Content -->
        <main>
            <div class="container">
                <slot />
            </div>
        </main>
    </div>
</template>
