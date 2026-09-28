<template>
  <section v-if="firstFetch">
    <input type="text" v-model="nameToSearch" class="form-control form-control-lg shadow-sm" placeholder="Buscar por titulo o tags">
    <small class="form-text text-muted">{{status}}</small>

    <section class="my-2">
      <label class="text-smaller is-700 mb-1">Por tipo</label>
      <div class="d-flex flex-column flex-md-row">
        <div class="bg-white py-2 px-4 my-1 border rounded shadow-sm mr-md-2 is-clickable" :class="{'type-active': typeSelected == null}" @click="typeSelected = null">
          <i class="fas fa-star"></i>&nbsp;Cualquier tipo
        </div>
        <div class="bg-white py-2 px-4 my-1 border rounded shadow-sm mr-md-2 is-clickable" :class="{'type-active': typeSelected == type.id}" v-for="type in types" :key="`type-${type.id}`" @click="typeSelected = type.id">
          <i :class="`${type.icon} text-primary`"></i>&nbsp;{{type.title}}
        </div>
      </div>
    </section>

    <div class="form-row mt-3">
      <div class="col-md-6 col-lg-3 mb-2" v-if="fixedObjective == null">
        <label class="text-smaller is-700 mb-1" for="filter-category">Eje</label>
        <select id="filter-category" class="custom-select shadow-sm" v-model="categorySelected">
          <option :value="null">Todos los ejes</option>
          <option v-for="category in categories" :key="`category-${category.id}`" :value="category.id">{{category.title}}</option>
        </select>
      </div>
      <div class="col-md-6 col-lg-3 mb-2" v-if="fixedObjective == null">
        <label class="text-smaller is-700 mb-1" for="filter-objective">Objetivo</label>
        <select id="filter-objective" class="custom-select shadow-sm" v-model="objectiveSelected">
          <option :value="null">Todos los objetivos</option>
          <template v-if="selectedCategory">
            <option v-for="objective in selectedCategory.objectives" :key="`objective-${objective.id}`" :value="objective.id">{{objective.title}}</option>
          </template>
          <template v-else>
            <optgroup v-for="category in categoriesWithObjectives" :key="`group-${category.id}`" :label="category.title">
              <option v-for="objective in category.objectives" :key="`objective-${objective.id}`" :value="objective.id">{{objective.title}}</option>
            </optgroup>
          </template>
        </select>
      </div>
      <div class="mb-2" :class="filterColumnClass">
        <label class="text-smaller is-700 mb-1" for="filter-date">Fecha del reporte</label>
        <select id="filter-date" class="custom-select shadow-sm" v-model="dateRange">
          <option :value="null">Cualquier fecha</option>
          <option v-for="range in dateRanges" :key="`range-${range.id}`" :value="range.id">{{range.title}}</option>
        </select>
      </div>
      <div class="mb-2" :class="filterColumnClass">
        <label class="text-smaller is-700 mb-1" for="filter-sort">Ordenar por</label>
        <select id="filter-sort" class="custom-select shadow-sm" v-model="sort">
          <option v-for="option in sorts" :key="`sort-${option.id}`" :value="option.id">{{option.title}}</option>
        </select>
      </div>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-1">
      <span class="text-smaller text-muted">{{resultsLabel}}</span>
      <button type="button" class="btn btn-link btn-sm p-0" v-if="hasActiveFilters" @click="clearFilters"><i class="fas fa-xmark fa-fw"></i>Limpiar filtros</button>
    </div>
    <hr>
    <div class="reports-grid reports-grid--uniform mb-3" :class="{'reports-grid--loading': isLoading}" v-if="reports.length > 0">
      <report-tile v-for="report in reports" :key="`report-${report.id}`" :report="report" :context="fixedObjective == null ? 'hierarchy' : 'goal'"></report-tile>
    </div>
    <div class="card shadow-sm" v-else>
      <div class="card-body p-5 text-center">
            <h6 class="card-title mb-2"><i class="far fa-face-surprise"></i>&nbsp;¡No se encontraron reportes con esos criterios de busqueda!</h6>
            <p class="text-smaller mb-0">Intente de nuevo o cambie los criterios de busqueda</p>
      </div>
    </div>
    <hr>
    <paginator v-if="paginatorData.links && !isLoading" :paginatorData="paginatorData" @updateData="updateData" />

  </section>
  <section v-else>
    <slot></slot>
  </section>
</template>

<script>
import debounce from "lodash/debounce";
import ReportTile from '../../../report/ReportTile'

const DEFAULT_SORT = 'recent'

export default {
  props: {
    fetchUrl: {
      type: String,
      required: true
    },
    categories: {
      type: Array,
      default: () => []
    },
    fixedObjective: {
      type: Number,
      default: null
    }
  },
  components: {
    ReportTile
  },
  data(){
    return {
      firstFetch: false,
      isLoading: true,
      nameToSearch: "",
      searchableString: null,
      status: 'Comience escribiendo el nombre',
      typeSelected: null,
      categorySelected: null,
      objectiveSelected: this.fixedObjective,
      dateRange: null,
      sort: DEFAULT_SORT,
      reports: [],
      paginatorData: {
        links: null,
        meta: null,
      },
      types: [
        {
          id: 'post',
          title: 'Novedad',
          icon: 'fas fa-bullhorn'
        },
        {
          id: 'progress',
          title: 'Avance',
          icon: 'fas fa-forward-fast'
        },
        {
          id: 'milestone',
          title: 'Hito',
          icon: 'fas fa-medal'
        },
      ],
      dateRanges: [
        {id: 'last_30_days', title: 'Últimos 30 días'},
        {id: 'last_3_months', title: 'Últimos 3 meses'},
        {id: 'last_year', title: 'Último año'},
      ],
      sorts: [
        {id: 'recent', title: 'Más recientes'},
        {id: 'oldest', title: 'Más antiguos'},
        {id: 'most_commented', title: 'Más comentados'},
        {id: 'most_liked', title: 'Más "me gusta"'},
      ],
    }
  },
  created: function(){
    if (this.fixedObjective == null) {
      this.readFiltersFromUrl()
    }
    this.fetchReports()
  },
  methods: {
    readFiltersFromUrl: function(){
      const params = new URLSearchParams(window.location.search)
      const pickFrom = (options, value) => options.some(option => option.id === value) ? value : null
      const toId = value => /^\d+$/.test(value || '') ? Number(value) : null

      this.nameToSearch = params.get('s') || ''
      this.typeSelected = pickFrom(this.types, params.get('type'))
      this.dateRange = pickFrom(this.dateRanges, params.get('date_range'))
      this.sort = pickFrom(this.sorts, params.get('sort')) || DEFAULT_SORT
      this.categorySelected = this.categories.some(category => category.id === toId(params.get('category'))) ? toId(params.get('category')) : null
      this.objectiveSelected = this.findObjectiveCategory(toId(params.get('objective'))) ? toId(params.get('objective')) : null
    },
    writeFiltersToUrl: function(){
      if (this.fixedObjective != null) return
      const params = new URLSearchParams()
      if (this.searchableString != null) params.set('s', this.searchableString)
      if (this.typeSelected != null) params.set('type', this.typeSelected)
      if (this.categorySelected != null) params.set('category', this.categorySelected)
      if (this.objectiveSelected != null) params.set('objective', this.objectiveSelected)
      if (this.dateRange != null) params.set('date_range', this.dateRange)
      if (this.sort !== DEFAULT_SORT) params.set('sort', this.sort)
      const query = params.toString()
      window.history.replaceState(null, '', window.location.pathname + (query ? `?${query}` : ''))
    },
    findObjectiveCategory: function(objectiveId){
      if (objectiveId == null) return null
      return this.categories.find(category => category.objectives.some(objective => objective.id === objectiveId)) || null
    },
    clearFilters: function(){
      this.nameToSearch = ''
      this.typeSelected = null
      this.categorySelected = null
      this.objectiveSelected = this.fixedObjective
      this.dateRange = null
      this.sort = DEFAULT_SORT
    },
    fetchReports:  debounce(
      function(){
        this.isLoading = true
        this.$http.get(this.urlGet)
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
      }, 600),
    updateData: function(data){
      this.reports = data.data
      this.paginatorData = {
        links: data.links,
        meta: data.meta
      }
    }
  },
  computed: {
    selectedCategory: function(){
      return this.categories.find(category => category.id === this.categorySelected) || null
    },
    categoriesWithObjectives: function(){
      return this.categories.filter(category => category.objectives.length > 0)
    },
    filterColumnClass: function(){
      return this.fixedObjective == null ? 'col-md-6 col-lg-3' : 'col-md-6'
    },
    hasActiveFilters: function(){
      return this.nameToSearch !== ''
        || this.typeSelected != null
        || this.categorySelected != null
        || this.objectiveSelected != this.fixedObjective
        || this.dateRange != null
        || this.sort !== DEFAULT_SORT
    },
    resultsLabel: function(){
      if (this.isLoading || !this.paginatorData.meta) return 'Buscando reportes...'
      const total = this.paginatorData.meta.total
      return total === 1 ? '1 reporte encontrado' : `${total} reportes encontrados`
    },
    urlGet: function() {
      const params = new URLSearchParams({
        with: 'report_goal,report_hierarchy,report_cover,report_excerpt,report_highlights',
        sort: this.sort,
        size: 12,
      })
      if (this.searchableString != null) params.set('s', this.searchableString)
      if (this.typeSelected != null) params.set('type', this.typeSelected)
      if (this.categorySelected != null) params.set('category', this.categorySelected)
      if (this.objectiveSelected != null) params.set('objective', this.objectiveSelected)
      if (this.dateRange != null) params.set('date_range', this.dateRange)
      return `${this.fetchUrl}?${params.toString()}`
    }
  },
  watch: {
    nameToSearch: function(newNameToSearch) {
      this.status = "Tipeando...";
      if (newNameToSearch.length >= 3) {
        this.searchableString = newNameToSearch
      }
      else {
        this.searchableString = null
        this.status = "Por favor, escriba más caracteres para la busqueda";
      }
    },
    categorySelected: function(categoryId){
      const objectiveCategory = this.findObjectiveCategory(this.objectiveSelected)
      if (categoryId != null && objectiveCategory && objectiveCategory.id !== categoryId) {
        this.objectiveSelected = null
      }
    },
    objectiveSelected: function(objectiveId){
      const objectiveCategory = this.findObjectiveCategory(objectiveId)
      if (objectiveCategory && this.categorySelected == null) {
        this.categorySelected = objectiveCategory.id
      }
    },
    urlGet: function(){
      this.writeFiltersToUrl()
      this.fetchReports()
    }
  }
}
</script>

<style lang="scss" scoped>
.type-active{
  background-color: #2c59fb !important;
  color: #FFF !important;
  i{
  color: #FFF !important;
  }
}
.reports-grid--loading{
  opacity: .5;
  transition: opacity .15s ease-in-out;
}
</style>
