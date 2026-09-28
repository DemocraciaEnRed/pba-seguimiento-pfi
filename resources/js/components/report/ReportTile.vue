<template>
  <a :href="report.url" class="report-tile card shadow-sm" :class="{'report-tile--featured': featured, 'report-tile--horizontal': horizontal}" :style="{'--report-accent': category.color || null}">
    <div class="report-tile__inner">
      <div class="report-tile__media" :style="mediaStyle">
        <category-icon v-if="!report.cover" :url="category.icon_url" :size="featured ? '3x' : '2x'" :style="{color: category.color}"></category-icon>
        <span class="report-tile__type badge badge-white shadow-sm"><i :class="`${report.type_icon} fa-fw text-primary`"></i> {{report.type_label}}</span>
      </div>
      <div class="card-body d-flex flex-column">
        <template v-if="context === 'hierarchy' && report.hierarchy">
          <p class="text-smallest mb-0 is-600" :style="{color: category.color}">Eje #{{category.order}} • {{category.title}}</p>
          <p class="report-tile__context text-smallest text-muted mb-1" :title="report.hierarchy.objective.title"><i class="fas fa-crosshairs fa-fw"></i> {{report.hierarchy.objective.title}}</p>
        </template>
        <p class="report-tile__context text-smallest text-muted mb-1" v-if="context === 'goal' && report.goal" :title="report.goal.title"><i :class="`far fa-circle-dot fa-fw text-${report.goal.status}`"></i> {{report.goal.title}}</p>
        <h4 class="report-tile__title is-700 text-dark mb-2" :class="featured ? 'h4' : 'h6'">{{report.title}}</h4>
        <p class="report-tile__excerpt text-muted text-smaller mb-2" v-if="report.excerpt">{{report.excerpt}}</p>
        <div class="report-tile__highlights mb-2" v-if="report.highlights">
          <span class="report-highlight" v-if="indicator && indicator.kind === 'simple'" title="Cambio del indicador">
            <i class="fas fa-chart-line fa-fw text-primary"></i> {{indicator.from}} → <b>{{indicator.to}}</b> {{indicator.unit}}
            <b class="text-primary">({{indicator.increment}})</b>
          </span>
          <span class="report-highlight" v-if="indicator && indicator.kind === 'periodic'" title="Medición del período">
            <i class="fas fa-calendar-check fa-fw text-primary"></i> {{indicator.period_label}}: <b>{{indicator.measured_value}}</b> {{indicator.unit}}
          </span>
          <span class="report-highlight" v-if="statusChange" title="Cambio de estado de la meta">
            <template v-if="statusChange.from">
              <span :class="`text-${statusChange.from}`"><i class="far fa-circle-dot"></i> {{statusChange.from_label}}</span> →
            </template>
            <b :class="`text-${statusChange.to}`"><i class="far fa-circle-dot"></i> {{statusChange.to_label}}</b>
          </span>
          <span class="report-highlight report-highlight--milestone" v-if="report.highlights.milestone">
            <i class="fas fa-medal fa-fw"></i> Hito #{{report.highlights.milestone.order}}: {{report.highlights.milestone.title}}
          </span>
        </div>
        <p class="mt-auto mb-0 text-muted text-smallest">
          <i class="far fa-clock fa-fw"></i> {{report.published_at}}
          <span class="ml-2" title="Comentarios"><i class="far fa-comment fa-fw"></i> {{report.comments_count}}</span>
          <span class="ml-2" title="Me gusta"><i class="far fa-thumbs-up fa-fw"></i> {{report.positive_testimonies_count}}</span>
        </p>
      </div>
    </div>
  </a>
</template>

<script>
export default {
  props: {
    report: {
      type: Object,
      required: true
    },
    featured: {
      type: Boolean,
      default: false
    },
    horizontal: {
      type: Boolean,
      default: false
    },
    context: {
      type: String,
      default: 'hierarchy',
      validator: value => ['hierarchy', 'goal', 'none'].includes(value)
    }
  },
  computed: {
    category: function(){
      return this.report.hierarchy ? this.report.hierarchy.category : {}
    },
    indicator: function(){
      return this.report.highlights ? this.report.highlights.indicator : null
    },
    statusChange: function(){
      return this.report.highlights ? this.report.highlights.status_change : null
    },
    mediaStyle: function(){
      if (this.report.cover) {
        return {backgroundImage: `url("${this.featured ? this.report.cover.url : this.report.cover.thumbnail_url}")`}
      }
      return {backgroundColor: this.category.background_color || null}
    },
  },
}
</script>
