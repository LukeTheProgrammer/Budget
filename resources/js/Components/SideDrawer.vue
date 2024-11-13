<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
})

const classes = computed(() => {
  return {
    'side-drawer': true,
    'drawer-open': props.open,
  }
})
</script>

<template>
  <div :class="classes">
    <p>{{ classes }}</p>
  </div>
</template>

<style>
.side-drawer {
  position: absolute;
  top: 57px;
  right: -5vw;
  width: 5vw;
  height: 90vh;
  border: 2px solid red;
  z-index: 1;
  background-color: var(--bs-body-bg);
  transition: right 0.5s ease;
}
.side-drawer.drawer-open {
  right: 0;
}
</style>
