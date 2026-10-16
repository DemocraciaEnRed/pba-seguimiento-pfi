<template>
  <a :href="objective.url" class="list-group-item list-group-item-action d-flex align-items-start gap-3 py-3">
    <div class="category-icon-container d-none d-md-flex" :style="`background-color: ${objective.category.background_color}`">
      <category-icon :url="objective.category.icon_url" :style="`color: ${objective.category.color}`"></category-icon>
    </div>
    <div class="flex-grow-1 min-w-0">
      <p class="mb-1 text-smaller text-truncate">
        <span :style="`color:${objective.category.color}`"><category-icon class="d-inline-block d-md-none" :url="objective.category.icon_url"></category-icon> {{objective.category.title}}</span>
        <span class="text-muted" v-if="objective.strategic_objective" :title="objective.strategic_objective.title">&nbsp;•&nbsp;<span class="is-700">{{objective.strategic_objective.codigo}}</span> {{objective.strategic_objective.title}}</span>
      </p>
      <h6 class="mb-2 text-dark is-700 objective-title">{{objective.title}}</h6>
      <div class="progress objective-status-bar mb-1" v-if="objective.goals_count > 0" :title="statusSummary">
        <div v-for="segment in visibleSegments" :key="segment.key" class="progress-bar" :class="`bg-${segment.key}`" :style="`width: ${segment.percent}%`"></div>
      </div>
      <p class="mb-0 text-smallest text-muted d-flex flex-wrap gap-3" v-if="objective.goals_count > 0">
        <span v-for="segment in visibleSegments" :key="`label-${segment.key}`"><i :class="`fas fa-circle text-${segment.key}`"></i> {{segment.count}} {{segment.count === 1 ? segment.singular : segment.label}}</span>
      </p>
      <p class="mb-0 text-smallest text-muted" v-else>Sin metas cargadas</p>
    </div>
    <div class="text-right text-smaller text-nowrap flex-shrink-0">
      <div><span class="is-700">{{objective.goals_count}}</span> {{objective.goals_count === 1 ? 'meta' : 'metas'}}<i class="fas fa-medal fa-fw text-primary ml-1"></i></div>
      <div><span class="is-700">{{objective.reports_count}}</span> {{objective.reports_count === 1 ? 'reporte' : 'reportes'}}<i class="far fa-file fa-fw text-primary ml-1"></i></div>
      <div class="text-muted" v-if="objective.last_report_when">Último reporte {{objective.last_report_when}}<i class="far fa-clock fa-fw ml-1"></i></div>
    </div>
  </a>
</template>

<script>
const SEGMENTS = [
  {key: 'reached', singular: 'alcanzada', label: 'alcanzadas', color: '#2eda54'},
  {key: 'ongoing', singular: 'en progreso', label: 'en progreso', color: '#ffa51e'},
  {key: 'delayed', singular: 'no cumplida', label: 'no cumplidas', color: '#f15454'},
  {key: 'inactive', singular: 'inactiva', label: 'inactivas', color: '#7e7e7e'},
]

export default {
  props: ['objective'],
  computed: {
    visibleSegments: function(){
      const status = this.objective.goals_status || {}
      const total = this.objective.goals_count || 0
      return SEGMENTS
        .map(segment => ({...segment, count: Number(status[segment.key] ?? 0)}))
        .filter(segment => segment.count > 0)
        .map(segment => ({...segment, percent: total > 0 ? (segment.count / total) * 100 : 0}))
    },
    statusSummary: function(){
      return this.visibleSegments.map(segment => `${segment.count} ${segment.count === 1 ? segment.singular : segment.label}`).join(' · ')
    }
  }
}
</script>

<style scoped>
.objective-title{
  font-size: 1.05rem;
}
.objective-status-bar{
  height: 8px;
  max-width: 28rem;
}
</style>
