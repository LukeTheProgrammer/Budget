<script setup>
import NavLink from '@/Components/NavLink.vue'
</script>

<template>
  <div>
    <nav class="navbar navbar-expand-sm bg-dark border-bottom border-body" data-bs-theme="dark">
      <div class="container">
        <a class="navbar-brand" href="/">Budget</a>

        <button
          class="navbar-toggler"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#navbarNav"
          aria-controls="navbarNav"
          aria-expanded="false"
          aria-label="Toggle navigation"
        >
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="navbar-collapse d-flex justify-content-between" id="navbarNav">
          <ul class="navbar-nav">
            <li class="nav-item">
              <NavLink route="dashboard">Dashboard</NavLink>
            </li>
            <li class="nav-item">
              <NavLink route="reports">Reports</NavLink>
            </li>
            <li class="nav-item">
              <NavLink route="vendors">Vendors</NavLink>
            </li>
            <li class="nav-item">
              <NavLink route="vendor-aliases">Vendor Aliases</NavLink>
            </li>
          </ul>

          <ul class="navbar-nav">
            <li class="nav-item dropdown">
              <a
                class="nav-link dropdown-toggle"
                href="#"
                role="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
              >
                <i class="bi bi-person-fill"></i>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><NavLink route="profile.edit">Profile</NavLink></li>
                <li><hr class="dropdown-divider" /></li>
                <li><NavLink route="logout" method="post" as="button">Log Out</NavLink></li>
              </ul>
            </li>
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
