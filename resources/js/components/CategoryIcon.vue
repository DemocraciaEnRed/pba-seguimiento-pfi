<template>
  <span v-if="url" class="category-icon" :class="size ? `category-icon--${size}` : null" :style="{ '--category-icon-url': `url('${url}')` }" aria-hidden="true"></span>
</template>

<script>
export default {
  props: {
    url: {
      type: String,
      default: null
    },
    size: {
      type: String,
      default: null
    }
  }
}
</script>
