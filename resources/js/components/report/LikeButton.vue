<template>
  <button type="button" class="btn is-700" :class="liked ? 'btn-white' : 'btn-outline-white'" :disabled="isSending" :aria-pressed="liked ? 'true' : 'false'" @click="toggle">
    <i :class="`${liked ? 'fas' : 'far'} fa-thumbs-up fa-fw`"></i>
    {{liked ? 'Te gusta' : 'Me gusta'}}
    <span class="badge ml-1" :class="liked ? 'badge-primary' : 'badge-white'">{{count}}</span>
  </button>
</template>

<script>
export default {
  props: {
    toggleUrl: {
      type: String,
      required: true
    },
    initialLiked: {
      type: Boolean,
      default: false
    },
    initialCount: {
      type: Number,
      default: 0
    },
    isAuthenticated: {
      type: Boolean,
      default: false
    },
    isVerified: {
      type: Boolean,
      default: false
    },
    loginUrl: {
      type: String,
      required: true
    },
    verifyUrl: {
      type: String,
      required: true
    }
  },
  data(){
    return {
      liked: this.initialLiked,
      count: this.initialCount,
      isSending: false,
    }
  },
  methods: {
    toggle: function(){
      if (!this.isAuthenticated) {
        window.location.href = this.loginUrl
        return
      }
      if (!this.isVerified) {
        this.$toasted.show('Verificá tu cuenta para poder marcar reportes con "Me gusta"', {
          icon: 'triangle-exclamation',
          action: {text: 'Verificar', onClick: () => { window.location.href = this.verifyUrl }}
        })
        return
      }
      const previous = {liked: this.liked, count: this.count}
      this.liked = !this.liked
      this.count += this.liked ? 1 : -1
      this.isSending = true
      this.$http.post(this.toggleUrl)
      .then( response => {
        this.liked = response.data.liked
        this.count = response.data.count
      })
      .catch( error => {
        this.liked = previous.liked
        this.count = previous.count
        this.$toasted.show('No pudimos registrar tu "Me gusta", intentá de nuevo', {icon: 'exclamation-triangle'})
        console.error(error)
      })
      .finally( () => {
        this.isSending = false
      })
    }
  }
}
</script>
