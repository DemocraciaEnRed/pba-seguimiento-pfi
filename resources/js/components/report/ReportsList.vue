<template>
  <section v-if="firstFetch">
    <ol class="report-timeline list-unstyled mb-3" v-if="reports.length > 0">
      <li class="report-timeline__item" v-for="(report, index) in reports" :key="report.id">
        <span class="report-timeline__marker shadow-sm" :title="report.type_label"><i :class="`${report.type_icon} fa-fw`"></i></span>
        <p class="report-timeline__date text-smaller is-700 text-muted mb-2" v-if="showDate(index)">
          {{formatDate(report.date)}}
        </p>
        <report-tile :report="report" :context="context" horizontal></report-tile>
      </li>
    </ol>
    <div class="text-center" v-if="canFetchMore">
      <button @click="fetchMore" :disabled="isLoading" class="btn btn-outline-primary">
        <span v-if="isLoading"><i class="fas fa-arrows-rotate fa-spin"></i>&nbsp;Cargando</span>
        <span v-else>Cargar más reportes</span>
      </button>
    </div>
    <p class="text-muted" v-if="reports.length == 0">No hay reportes</p>
  </section>
  <section v-else>
    <slot></slot>
  </section>
</template>

<script>
import ReportTile from './ReportTile'
export default {
  props: {
    fetchUrl: {
      type: String,
      required: true
    },
    context: {
      type: String,
      default: 'none'
    }
  },
  components: {
    ReportTile
  },
  data(){
    return {
      isLoading: true,
      firstFetch: false,
      reports: [],
      paginatorData: {
        links: null,
        meta: null,
      },
    }
  },
  created: function(){
    this.fetchReports()
  },
  methods:{
    fetchReports: function(){
      this.isLoading = true
      this.$http.get(this.fetchUrl)
      .then( response => {
        this.reports = response.data.data
        this.paginatorData = {
          links: response.data.links,
          meta: response.data.meta
        }
        this.firstFetch = true
      })
      .catch( error => {
        this.$toasted.show('Hubo un error cargando los reportes', {icon: 'exclamation-triangle'})
        console.error(error)
      })
      .finally( () => {
        this.isLoading = false
      })
    },
    fetchMore: function(){
      this.isLoading = true
      this.$http.get(this.paginatorData.links.next)
      .then( response => {
        this.reports = this.reports.concat(response.data.data)
        this.paginatorData = {
          links: response.data.links,
          meta: response.data.meta
        }
      })
      .catch( error => {
        this.$toasted.show('Hubo un error cargando los reportes', {icon: 'exclamation-triangle'})
        console.error(error)
      })
      .finally( () => {
        this.isLoading = false
      })
    },
    showDate: function(index){
      if(index == 0) return true;
      let lastDate = this.reports[index-1].date.split('T')[0]
      let nowDate = this.reports[index].date.split('T')[0]
      if(nowDate == lastDate) return false
      return true
    },
    formatDate: function(date){
      const [year, month, day] = date.split('T')[0].split('-').map(Number)
      return new Date(year, month - 1, day).toLocaleDateString('es-AR', {day: 'numeric', month: 'long', year: 'numeric'})
    },
  },
  computed:{
    canFetchMore: function(){
      if(!this.paginatorData.links) return false
      if(this.paginatorData.links.next != null) return true
      return false
    }
  }
}
</script>

<style lang="scss" scoped>
$marker-size: 34px;
$marker-gap: 16px;

.report-timeline{
  position: relative;
  padding-left: $marker-size + $marker-gap;

  &::before{
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: $marker-size * 0.5 - 1px;
    width: 2px;
    background: linear-gradient(to bottom, var(--primary), rgba(0, 0, 0, 0.08));
    border-radius: 2px;
  }
}
.report-timeline__item{
  position: relative;
  padding-bottom: 1.25rem;

  &:last-child{
    padding-bottom: 0;
  }
}
.report-timeline__marker{
  position: absolute;
  top: 0;
  left: -($marker-size + $marker-gap);
  width: $marker-size;
  height: $marker-size;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background-color: #FFF;
  border: 2px solid var(--primary);
  color: var(--primary);
  font-size: 0.85rem;
  z-index: 1;
}
.report-timeline__date{
  line-height: $marker-size;
}
</style>