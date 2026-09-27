<template>
  <section v-if="!isLoading">
    <div class="row">
      <div class="col-6 col-lg-3 mb-3">
        <div class="card rounded shadow-sm h-100">
          <div class="card-body text-center">
            <p class="h3 is-700 mb-1"><i class="fas fa-layer-group text-primary"></i>&nbsp;{{ categoriesTotal }}</p>
            <p class="mb-0">Ejes</p>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3 mb-3">
        <div class="card rounded shadow-sm h-100">
          <div class="card-body text-center">
            <p class="h3 is-700 mb-1"><i class="fas fa-flag text-info"></i>&nbsp;{{ strategicObjectivesTotal }}</p>
            <p class="mb-0">Objetivos estratégicos</p>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3 mb-3">
        <div class="card rounded shadow-sm h-100">
          <div class="card-body text-center">
            <p class="h3 is-700 mb-1"><i class="fas fa-bullseye text-info"></i>&nbsp;{{ objectivesTotal }}</p>
            <p class="mb-0">Objetivos generales</p>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3 mb-3">
        <div class="card rounded shadow-sm h-100">
          <div class="card-body text-center">
            <p class="h3 is-700 mb-1"><i class="fas fa-medal text-reached"></i>&nbsp;{{ goalsTotal }}</p>
            <p class="mb-0">Metas</p>
            <p class="text-smaller text-muted mb-0">{{ goalsReached }} alcanzadas</p>
          </div>
        </div>
      </div>
    </div>
    <div class="card rounded shadow-sm mb-4">
      <div class="card-body">
        <p class="mb-3"><b>Estado de las metas</b></p>
        <p class="text-smaller mb-1">
          <b>Estado declarado</b>&nbsp;
          <span class="text-nowrap"><i class="fas fa-circle fa-fw text-reached"></i>{{ goalsReached }} Alcanzadas</span>&nbsp;
          <span class="text-nowrap"><i class="fas fa-circle fa-fw text-ongoing"></i>{{ goalsOngoing }} En progreso</span>&nbsp;
          <span class="text-nowrap"><i class="fas fa-circle fa-fw text-delayed"></i>{{ goalsDelayed }} No cumplidas</span>&nbsp;
          <span class="text-nowrap"><i class="fas fa-circle fa-fw text-inactive"></i>{{ goalsInactive }} Inactivas</span>
        </p>
        <div class="progress mb-3">
          <div class="progress-bar bg-reached" role="progressbar" :style="`width: ${percent(goalsReached, goalsTotal)}%`">{{ percent(goalsReached, goalsTotal) }}%</div>
          <div class="progress-bar bg-ongoing" role="progressbar" :style="`width: ${percent(goalsOngoing, goalsTotal)}%`">{{ percent(goalsOngoing, goalsTotal) }}%</div>
          <div class="progress-bar bg-delayed" role="progressbar" :style="`width: ${percent(goalsDelayed, goalsTotal)}%`">{{ percent(goalsDelayed, goalsTotal) }}%</div>
          <div class="progress-bar bg-inactive" role="progressbar" :style="`width: ${percent(goalsInactive, goalsTotal)}%`">{{ percent(goalsInactive, goalsTotal) }}%</div>
        </div>
        <p class="text-smaller mb-1">
          <b>Cumplimiento medido</b>&nbsp;
          <span class="text-nowrap"><i class="fas fa-circle fa-fw text-success"></i>{{ trafficLights.green }} Cumplido</span>&nbsp;
          <span class="text-nowrap"><i class="fas fa-circle fa-fw text-warning"></i>{{ trafficLights.yellow }} Levemente por debajo</span>&nbsp;
          <span class="text-nowrap"><i class="fas fa-circle fa-fw text-danger"></i>{{ trafficLights.red }} Incumplido</span>&nbsp;
          <span class="text-nowrap"><i class="fas fa-circle fa-fw text-secondary"></i>{{ trafficLights.unmeasured }} Sin medición</span>
        </p>
        <div class="progress">
          <div class="progress-bar bg-success" role="progressbar" :style="`width: ${percent(trafficLights.green, activeGoals)}%`">{{ percent(trafficLights.green, activeGoals) }}%</div>
          <div class="progress-bar bg-warning" role="progressbar" :style="`width: ${percent(trafficLights.yellow, activeGoals)}%`">{{ percent(trafficLights.yellow, activeGoals) }}%</div>
          <div class="progress-bar bg-danger" role="progressbar" :style="`width: ${percent(trafficLights.red, activeGoals)}%`">{{ percent(trafficLights.red, activeGoals) }}%</div>
          <div class="progress-bar bg-secondary" role="progressbar" :style="`width: ${percent(trafficLights.unmeasured, activeGoals)}%`">{{ percent(trafficLights.unmeasured, activeGoals) }}%</div>
        </div>
      </div>
    </div>
    <div class="card rounded shadow-sm mb-4" v-if="categories.length > 0">
      <div class="card-body">
        <p><b>Avance por eje</b></p>
        <a v-for="category in categories" :key="`category-progress-${category.id}`" :href="`/objetivos?category=${category.id}`" class="d-block text-reset text-decoration-none py-2 border-top">
          <div class="d-flex flex-wrap justify-content-between align-items-baseline mb-1">
            <span :style="`color: ${category.color}`"><i :class="`${category.icon} fa-fw`"></i>&nbsp;<b>Eje #{{ category.order }}</b>&nbsp;{{ category.title }}</span>
            <span class="text-smaller text-muted" v-if="category.goals_total > 0">
              {{ category.goals_reached }}/{{ category.goals_total }} alcanzadas
              <template v-if="category.measured > 0">&middot; {{ percent(category.green, category.measured) }}% en verde (de {{ category.measured }} medidas)</template>
              &nbsp;<i class="fas fa-arrow-right"></i>
            </span>
            <span class="text-smaller text-muted" v-else>Sin metas publicadas</span>
          </div>
          <div class="progress" style="height: 8px">
            <div class="progress-bar" role="progressbar" :style="`width: ${percent(category.goals_reached, category.goals_total)}%; background-color: ${category.color}`"></div>
          </div>
        </a>
      </div>
    </div>
  </section>
  <div class="card rounded shadow-sm mb-4" v-else>
    <div class="card-body">
      <slot></slot>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    fetchUrl: {
      type: String,
      required: true
    }
  },
  data() {
    return {
      isLoading: true,
      categoriesTotal: 0,
      strategicObjectivesTotal: 0,
      objectivesTotal: 0,
      goalsTotal: 0,
      goalsReached: 0,
      goalsOngoing: 0,
      goalsDelayed: 0,
      goalsInactive: 0,
      trafficLights: {
        green: 0,
        yellow: 0,
        red: 0,
        measured: 0,
        unmeasured: 0
      },
      categories: []
    }
  },
  beforeMount: function(){
    this.fetchStats();
  },
  methods: {
    fetchStats: function(){
      this.isLoading = true
      this.$http.get(this.fetchUrl)
      .then( response => {
        const stats = response.data.data
        this.categoriesTotal = stats.categories_total
        this.strategicObjectivesTotal = stats.strategic_objectives_total
        this.objectivesTotal = stats.objectives_total
        this.goalsTotal = stats.goals_total
        this.goalsReached = stats.goals_reached
        this.goalsOngoing = stats.goals_ongoing
        this.goalsDelayed = stats.goals_delayed
        this.goalsInactive = stats.goals_inactive
        this.trafficLights = stats.traffic_lights
        this.categories = stats.categories
      })
      .catch( error => {
        this.$toasted.show('Hubo un error cargando las estadisticas', {icon: 'exclamation-triangle'})
        console.error(error)
      })
      .finally( () => {
        this.isLoading = false
      })
    },
    percent: function(part, total){
      if(total == 0) return 0;
      return Math.round((part / total) * 100)
    }
  },
  computed: {
    activeGoals: function(){
      return this.trafficLights.measured + this.trafficLights.unmeasured
    }
  }
}
</script>

<style>

</style>
