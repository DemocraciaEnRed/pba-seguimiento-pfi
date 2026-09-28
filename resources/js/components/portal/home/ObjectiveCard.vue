<template>
  <div class="card rounded shadow-sm objective-card" :style="{'--objective-accent': objective.category.color || null}">
    <div class="card-body">
      <div class="d-flex flex-column flex-md-row align-items-md-center">
        <div class="d-flex align-items-center w-100 mb-3 mb-md-0">
          <div class="mr-3 category-icon-container flex-shrink-0" :style="`background-color: ${objective.category.background_color}`">
            <category-icon :url="objective.category.icon_url" size="lg" :style="`color: ${objective.category.color}`"></category-icon>
          </div>
          <div class="objective-card__heading">
            <p class="my-1 text-smaller">
              <span :style="`color:${objective.category.color}`">{{objective.category.title}}</span>
              <span class="text-muted" v-if="objective.strategic_objective">&nbsp;•&nbsp;<i class="fas fa-compass fa-fw"></i> {{objective.strategic_objective.title}}</span>
            </p>
            <a :href="objective.url" class="objective-card__title text-dark h5 is-700 d-block mb-0">{{objective.title}}</a>
          </div>
        </div>
        <div class="d-flex align-items-center flex-shrink-0 ml-md-3 text-center">
          <div class="px-2">
            <span class="is-700"><i class="fas fa-medal fa-fw text-primary"></i>{{objective.goals_count}}</span><br><span class="text-smallest text-muted">metas</span>
          </div>
          <div class="px-2">
            <span class="is-700"><i class="far fa-file fa-fw text-primary"></i>{{objective.reports_count}}</span><br><span class="text-smallest text-muted">reportes</span>
          </div>
          <div class="px-2 text-smallest text-muted" v-if="objective.updated_when">
            <i class="far fa-clock fa-fw"></i><br>{{objective.updated_when}}
          </div>
        </div>
      </div>

      <div class="mt-3" v-if="objective.goals_count > 0">
        <div class="progress objective-card__status-bar">
          <div v-for="segment in statusSegments" :key="segment.status" class="progress-bar" :class="`bg-${segment.status}`" role="progressbar" :style="`width: ${segment.percentage}%`" :title="`${segment.total} ${segment.label}`" :aria-label="`${segment.total} ${segment.label}`"></div>
        </div>
        <p class="mb-0 mt-1 text-smallest text-muted">
          <span v-for="segment in statusSegments" :key="`legend-${segment.status}`" class="mr-3"><i :class="`fas fa-circle text-${segment.status}`"></i> {{segment.total}} {{segment.label}}</span>
        </p>
      </div>

      <div class="row mt-3">
        <div class="col-lg-8" v-if="latestGoals.length > 0">
          <p class="text-smaller is-700 mb-1">Últimas metas actualizadas</p>
          <div class="my-1 d-flex justify-content-between align-items-center goal-container" v-for="goal in latestGoals" :key="`goals_${goal.id}`">
            <a :href="goal.url" class="text-dark text-smaller text-truncate w-100" :title="goal.title">{{goal.title}}</a>
            <div class="progress my-0 mx-2 flex-shrink-0" style="height: 8px; width: 120px" :title="goal.status_label">
              <div class="progress-bar" :class="`bg-${goal.status}`" role="progressbar" :style="`width:${Math.min(100, goal.progress_percentage || 0)}%`" :aria-valuenow="goal.progress_percentage" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <span class="goal-percentage text-smallest is-700">{{goal.progress_percentage === null ? '—' : `${goal.progress_percentage}%`}}</span>
          </div>
        </div>
        <div class="d-flex flex-column mt-3 mt-lg-0" :class="latestGoals.length > 0 ? 'col-lg-4' : 'col-12'">
          <template v-if="objective.latest_report">
            <p class="text-smaller is-700 mb-1">Último reporte</p>
            <a :href="objective.latest_report.url" class="text-dark text-smaller d-flex align-items-start">
              <i :class="`${objective.latest_report.type_icon} fa-fw text-primary mt-1 mr-1`" :title="objective.latest_report.type_label"></i>
              <span class="objective-card__report-title">{{objective.latest_report.title}}</span>
            </a>
            <span class="text-smallest text-muted ml-4">{{objective.latest_report.type_label}} • {{objective.latest_report.when}}</span>
          </template>
          <a :href="objective.url" class="btn btn-sm btn-outline-primary mt-3 mt-lg-auto align-self-end">Ver objetivo&nbsp;<i class="fas fa-arrow-right"></i></a>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
const STATUSES = [
  {status: 'reached', label: 'alcanzadas'},
  {status: 'ongoing', label: 'en progreso'},
  {status: 'delayed', label: 'no cumplidas'},
  {status: 'inactive', label: 'inactivas'},
]

export default {
  props: ['objective'],
  computed: {
    latestGoals: function(){
      return (this.objective.latest_goals || []).slice(0, 3)
    },
    statusSegments: function(){
      const totals = this.objective.goals_status || {}
      const sum = STATUSES.reduce((carry, item) => carry + (totals[item.status] ?? 0), 0)
      return STATUSES
        .map(item => ({...item, total: totals[item.status] ?? 0}))
        .filter(item => item.total > 0)
        .map(item => ({...item, percentage: sum > 0 ? (item.total / sum) * 100 : 0}))
    },
  }
}
</script>

<style lang="scss" scoped>
.objective-card{
  border-left: 4px solid var(--objective-accent, transparent);

  .objective-card__heading {
    min-width: 0;
  }
  .objective-card__title:hover {
    text-decoration: none;
    opacity: .8;
  }
  .objective-card__status-bar {
    height: 10px;
  }
  .objective-card__report-title {
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    display: -webkit-box;
    overflow: hidden;
  }
  .goal-container{
    .goal-percentage {
       min-width: 34px;
       text-align: right;
    }
  }
  .progress:hover{
    cursor: help;
  }
}
</style>
