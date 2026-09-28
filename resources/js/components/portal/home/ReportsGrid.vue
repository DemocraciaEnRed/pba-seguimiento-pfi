<template>
  <div class="section" v-if="!isLoading">
    <div class="reports-grid mb-3" v-if="reports.length > 0">
      <report-tile v-for="(report, index) in reports" :key="`report-${report.id}`" :report="report" :featured="index === 0" :horizontal="index === 0"></report-tile>
    </div>
    <section class="p-5 text-center" v-else>
      <i class="fas fa-circle-info"></i>&nbsp; No hay reportes cargados en la plataforma
    </section>
  </div>
  <section class="p-5 text-center" v-else>
    <i class="fas fa-arrows-rotate fa-spin"></i> Cargando...
  </section>
</template>

<script>
import ReportTile from '../../report/ReportTile'

export default {
  props: {
    fetchUrl: {
      type: String,
      required: true
    }
  },
  components: {
    ReportTile
  },
  data() {
    return {
      isLoading: true,
      reports: [],
    }
  },
  beforeMount: function(){
    this.fetchReports();
  },
  methods: {
    fetchReports: function(){
      this.isLoading = true
      this.$http.get(this.fetchUrl)
      .then( response => {
        this.reports = response.data.data
      })
      .catch( error => {
        console.error(error)
      })
      .finally( () => {
        this.isLoading = false
      })
    },
  },
}
</script>
