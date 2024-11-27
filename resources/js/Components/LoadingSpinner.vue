<script setup>
import { computed } from 'vue'

const props = defineProps({
  title: {
    type: String,
    default: 'Loading...',
  },
  outerClasses: {
    type: [Array, Object, String],
    default: 'text-center',
  },
  small: {
    type: Boolean,
    default: false,
  },
})

const innerClasses = computed(() => {
  return {
    'spinner-border': true,
    'spinner-border-sm': props.small,
  }
})
</script>

<template>
  <div :class="outerClasses">
    <div class="spinner-border" :class="innerClasses" role="status">
      <span class="visually-hidden">{{ props.title }}</span>
    </div>
  </div>
</template>
