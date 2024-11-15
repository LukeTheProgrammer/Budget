<script setup>
const props = defineProps({
  props: {
    type: Object,
    default: () => {},
  },
})

const onClick = () => props.params.handleClick(props.params)
</script>

<template>
  <button type="button" class="btn btn-outline-secondary btn-sm" @click="onClick">
    <i class="bi bi-pencil"></i>
  </button>
</template>
