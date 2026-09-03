<template>
  <section>
    <div class="form-group">
      <label><b>Eje</b><span class="text-danger">*</span></label>
      <select class="custom-select" v-model="selectedEje" @change="onEjeChange" required>
        <option :value="null" disabled>Seleccione un eje</option>
        <option v-for="eje in ejes" :key="`eje${eje.id}`" :value="eje.id">{{ eje.title }}</option>
      </select>
    </div>
    <div class="form-group">
      <label><b>Objetivo Estratégico</b><span class="text-danger">*</span></label>
      <select class="custom-select" :name="name" v-model="selectedStrategicObjective" :disabled="!selectedEje" required>
        <option :value="null" disabled>Seleccione un objetivo estratégico</option>
        <option v-for="strategicObjective in strategicObjectiveOptions" :key="`strategicObjective${strategicObjective.id}`" :value="strategicObjective.id">{{ strategicObjective.codigo }} - {{ strategicObjective.title }}</option>
      </select>
    </div>
  </section>
</template>

<script>
export default {
  props: {
    name: {
      type: String,
      default: 'strategic_objective'
    },
    ejes: {
      type: Array,
      default: () => []
    },
    selected: {
      type: Number,
      default: null
    }
  },
  data(){
    return {
      selectedEje: null,
      selectedStrategicObjective: null
    }
  },
  computed: {
    strategicObjectiveOptions: function(){
      const eje = this.ejes.find(eje => eje.id === this.selectedEje);
      return eje ? eje.strategic_objectives : [];
    }
  },
  mounted: function(){
    if(!this.selected) return;
    for (const eje of this.ejes) {
      const strategicObjective = eje.strategic_objectives.find(strategicObjective => strategicObjective.id === this.selected);
      if(strategicObjective){
        this.selectedEje = eje.id;
        this.selectedStrategicObjective = strategicObjective.id;
        return;
      }
    }
  },
  methods: {
    onEjeChange: function(){
      this.selectedStrategicObjective = null;
    }
  }
}
</script>