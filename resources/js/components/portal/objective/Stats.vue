<template>
  <section v-if="!isLoading" class="objective-stats">
    <div class="row">
      <div class="col-4 mb-3">
        <div class="card rounded shadow-sm h-100">
          <div class="card-body text-center d-flex flex-column justify-content-around">
            <p class="h3 is-700 mb-1"><i class="fas fa-medal text-reached"></i>&nbsp;{{ goalsTotal }}</p>
            <p class="mb-0">Metas</p>
            <p class="text-smaller text-muted mb-0">{{ goalsReached }} alcanzadas</p>
          </div>
        </div>
      </div>
      <div class="col-4 mb-3">
        <div class="card rounded shadow-sm h-100">
          <div class="card-body text-center d-flex flex-column justify-content-around">
            <p class="h3 is-700 mb-1"><i class="far fa-file-lines text-primary"></i>&nbsp;{{ reportsTotal }}</p>
            <p class="mb-0">Reportes</p>
          </div>
        </div>
      </div>
      <div class="col-4 mb-3">
        <div class="card rounded shadow-sm h-100">
          <div class="card-body text-center d-flex flex-column justify-content-around">
            <p class="h5 is-700 mb-1"><i class="far fa-calendar text-info"></i>&nbsp;{{ lastReportLabel }}</p>
            <p class="mb-0">Último reporte</p>
          </div>
        </div>
      </div>
    </div>
    <div class="card rounded shadow-sm mb-3" v-if="goalsTotal > 0">
      <div class="card-body">
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
  </section>
  <div class="card rounded shadow-sm mb-3" v-else>
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
      goalsTotal: 0,
      goalsReached: 0,
      goalsOngoing: 0,
      goalsDelayed: 0,
      goalsInactive: 0,
      reportsTotal: 0,
      lastReportDate: null,
      trafficLights: {
        green: 0,
        yellow: 0,
        red: 0,
        measured: 0,
        unmeasured: 0
      }
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
        this.goalsTotal = stats.goals_total
        this.goalsReached = stats.goals_reached
        this.goalsOngoing = stats.goals_ongoing
        this.goalsDelayed = stats.goals_delayed
        this.goalsInactive = stats.goals_inactive
        this.reportsTotal = stats.reports_total
        this.lastReportDate = stats.last_report_date
        this.trafficLights = stats.traffic_lights
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
    },
    lastReportLabel: function(){
      if (!this.lastReportDate) return 'Sin reportes'
      const [year, month, day] = this.lastReportDate.split('-').map(Number)
      return new Date(year, month - 1, day).toLocaleDateString('es-AR', {day: 'numeric', month: 'short', year: 'numeric'})
    }
  }
}
</script>

<style>

</style>
