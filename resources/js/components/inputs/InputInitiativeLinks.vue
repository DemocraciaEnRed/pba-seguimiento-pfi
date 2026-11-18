<template>
  <section>
    <div v-for="(link, index) in entries" :key="link.key" class="border rounded p-3 mb-2">
      <div class="form-row">
        <div class="form-group col-md-4">
          <label :for="`initiative-link-label-${link.key}`">Etiqueta {{ index + 1 }}</label>
          <input :id="`initiative-link-label-${link.key}`" v-model="link.label" :name="`links[${index}][label]`" type="text" class="form-control" maxlength="255" required>
        </div>
        <div class="form-group col-md-8">
          <label :for="`initiative-link-url-${link.key}`">URL {{ index + 1 }}</label>
          <input :id="`initiative-link-url-${link.key}`" v-model="link.url" :name="`links[${index}][url]`" type="url" class="form-control" maxlength="500" placeholder="https://" required>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="index === 0" :aria-label="`Subir enlace ${index + 1}`" @click="move(index, -1)">
          <i class="fas fa-arrow-up" aria-hidden="true"></i> Subir
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="index === entries.length - 1" :aria-label="`Bajar enlace ${index + 1}`" @click="move(index, 1)">
          <i class="fas fa-arrow-down" aria-hidden="true"></i> Bajar
        </button>
        <button type="button" class="btn btn-outline-danger btn-sm" :aria-label="`Quitar enlace ${index + 1}`" @click="remove(index)">
          <i class="fas fa-xmark" aria-hidden="true"></i> Quitar
        </button>
      </div>
    </div>
    <p v-if="entries.length === 0" class="text-muted mb-2">Sin enlaces externos. La tarjeta igualmente permite ver el detalle.</p>
    <button ref="addButton" type="button" class="btn btn-outline-primary btn-sm" @click="add">
      <i class="fas fa-plus" aria-hidden="true"></i> Agregar enlace
    </button>
  </section>
</template>

<script>
export default {
  props: {
    links: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    return {
      nextKey: this.links.length,
      entries: this.links.map((link, key) => ({
        key,
        label: link?.label ?? '',
        url: link?.url ?? '',
      })),
    };
  },
  methods: {
    add() {
      const key = this.nextKey++;
      this.entries.push({ key, label: '', url: '' });
      this.$nextTick(() => document.getElementById(`initiative-link-label-${key}`).focus());
    },
    remove(index) {
      this.entries.splice(index, 1);
      this.$nextTick(() => this.$refs.addButton.focus());
    },
    move(index, direction) {
      const target = index + direction;
      const [link] = this.entries.splice(index, 1);
      this.entries.splice(target, 0, link);
      this.$nextTick(() => document.getElementById(`initiative-link-label-${link.key}`).focus());
    },
  },
};
</script>
