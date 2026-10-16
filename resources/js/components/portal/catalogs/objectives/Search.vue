<template>
  <section v-if="firstFetch">
    <input type="text" v-model="nameToSearch" class="form-control form-control-lg shadow-sm" placeholder="Buscar por titulo o tags">
    <small class="form-text text-muted">{{status}}</small>

    <section class="my-2 d-flex flex-wrap gap-2">
      <div class="bg-white py-2 px-3 border rounded shadow-sm is-clickable" :class="{'category-active': categorySelected == null}" @click="categorySelected = null">
        <i class="fas fa-star"></i>&nbsp;Todos
      </div>
      <div class="bg-white py-2 px-3 border rounded shadow-sm is-clickable" :class="{'category-active': categorySelected == category.id}" v-for="category in categories" :key="`category${category.id}`" @click="categorySelected = category.id">
        <category-icon :url="category.icon_url" :style="`color:${category.color}`"></category-icon>&nbsp;{{category.title}}
      </div>
    </section>

    <div class="d-grid grid-cols-1 grid-cols-md-2 gap-2 mt-3">
      <div>
        <label class="text-smaller is-700 mb-1" for="filter-strategic-objective">Objetivo estratégico</label>
        <select id="filter-strategic-objective" class="custom-select shadow-sm" v-model="strategicObjectiveSelected">
          <option :value="null">Todos los objetivos estratégicos</option>
          <template v-if="selectedCategory">
            <option v-for="strategicObjective in selectedCategory.strategic_objectives" :key="`oe-${strategicObjective.id}`" :value="strategicObjective.id">{{strategicObjective.codigo}} · {{strategicObjective.title}}</option>
          </template>
          <template v-else>
            <optgroup v-for="category in categoriesWithStrategicObjectives" :key="`group-${category.id}`" :label="category.title">
              <option v-for="strategicObjective in category.strategic_objectives" :key="`oe-${strategicObjective.id}`" :value="strategicObjective.id">{{strategicObjective.codigo}} · {{strategicObjective.title}}</option>
            </optgroup>
          </template>
        </select>
      </div>
      <div>
        <label class="text-smaller is-700 mb-1" for="filter-sort">Ordenar por</label>
        <select id="filter-sort" class="custom-select shadow-sm" v-model="sort">
          <option v-for="option in sorts" :key="`sort-${option.id}`" :value="option.id">{{option.title}}</option>
        </select>
      </div>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-2">
      <span class="text-smaller text-muted">{{resultsLabel}}</span>
      <button type="button" class="btn btn-link btn-sm p-0" v-if="hasActiveFilters" @click="clearFilters"><i class="fas fa-xmark fa-fw"></i>Limpiar filtros</button>
    </div>
    <hr>
    <div class="list-group shadow-sm mb-3" :class="{'objectives-list--loading': isLoading}" v-if="objectives.length > 0">
      <objective-card v-for="objective in objectives" :key="`objective${objective.id}`" :objective="objective"></objective-card>
    </div>
    <div class="card shadow-sm" v-else>
      <div class="card-body p-5 text-center">
            <h6 class="card-title mb-2"><i class="far fa-face-surprise"></i>&nbsp;¡No se encontraron objetivos con esos criterios de busqueda!</h6>
            <p class="text-smaller mb-0">Intente de nuevo o cambie los criterios de busqueda</p>
      </div>
    </div>
    <paginator v-if="paginatorData.links && !isLoading" :paginatorData="paginatorData" @updateData="updateData" />

  </section>
  <section v-else>
    <slot></slot>
  </section>
</template>

<script>
import debounce from "lodash/debounce";
import ObjectiveCard from './ObjectiveCard'

const DEFAULT_SORT = 'title'

export default {
  props: {
    fetchUrl: {
      type: String,
      required: true
    },
    categories: {
      type: Array,
      default: () => []
    }
  },
  components: {
    ObjectiveCard
  },
  data(){
    return {
      firstFetch: false,
      isLoading: true,
      nameToSearch: "",
      searchableString: null,
      status: 'Comience escribiendo el nombre',
      categorySelected: null,
      strategicObjectiveSelected: null,
      sort: DEFAULT_SORT,
      objectives: [],
      paginatorData: {
        links: null,
        meta: null,
      },
      sorts: [
        {id: 'title', title: 'Alfabético (A–Z)'},
        {id: 'last_report', title: 'Último reporte'},
        {id: 'most_reports', title: 'Más reportes'},
        {id: 'most_reached', title: 'Más metas alcanzadas'},
      ],
    }
  },
  created: function(){
    this.readFiltersFromUrl()
    this.fetchObjectives()
  },
  methods: {
    readFiltersFromUrl: function(){
      const params = new URLSearchParams(window.location.search)
      const toId = value => /^\d+$/.test(value || '') ? Number(value) : null
      const categoryId = toId(params.get('category'))
      const strategicObjectiveId = toId(params.get('strategic_objective'))

      this.nameToSearch = params.get('s') || ''
      this.searchableString = this.nameToSearch.length >= 3 ? this.nameToSearch : null
      this.sort = this.sorts.some(option => option.id === params.get('sort')) ? params.get('sort') : DEFAULT_SORT
      this.categorySelected = this.categories.some(category => category.id === categoryId) ? categoryId : null
      this.strategicObjectiveSelected = this.findStrategicObjectiveCategory(strategicObjectiveId) ? strategicObjectiveId : null
    },
    writeFiltersToUrl: function(){
      const params = new URLSearchParams()
      if (this.searchableString != null) params.set('s', this.searchableString)
      if (this.categorySelected != null) params.set('category', this.categorySelected)
      if (this.strategicObjectiveSelected != null) params.set('strategic_objective', this.strategicObjectiveSelected)
      if (this.sort !== DEFAULT_SORT) params.set('sort', this.sort)
      const query = params.toString()
      window.history.replaceState(null, '', window.location.pathname + (query ? `?${query}` : ''))
    },
    findStrategicObjectiveCategory: function(strategicObjectiveId){
      if (strategicObjectiveId == null) return null
      return this.categories.find(category => (category.strategic_objectives || []).some(strategicObjective => strategicObjective.id === strategicObjectiveId)) || null
    },
    clearFilters: function(){
      this.nameToSearch = ''
      this.categorySelected = null
      this.strategicObjectiveSelected = null
      this.sort = DEFAULT_SORT
    },
    fetchObjectives:  debounce(
      function(){
        this.isLoading = true
        this.$http.get(this.urlGet)
        .then( response => {
          this.objectives = response.data.data
          this.paginatorData = {
            links: response.data.links,
            meta: response.data.meta
          }
          this.firstFetch = true
        })
        .catch( error => {
          this.$toasted.show('Hubo un error cargando los objetivos', {icon: 'exclamation-triangle'})
          console.error(error)
        })
        .finally( () => {
          this.isLoading = false
        })
      }, 400),
    updateData: function(data){
      this.objectives = data.data
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
    categoriesWithStrategicObjectives: function(){
      return this.categories.filter(category => (category.strategic_objectives || []).length > 0)
    },
    hasActiveFilters: function(){
      return this.nameToSearch !== ''
        || this.categorySelected != null
        || this.strategicObjectiveSelected != null
        || this.sort !== DEFAULT_SORT
    },
    resultsLabel: function(){
      if (this.isLoading || !this.paginatorData.meta) return 'Buscando objetivos...'
      const total = this.paginatorData.meta.total
      return total === 1 ? '1 objetivo encontrado' : `${total} objetivos encontrados`
    },
    urlGet: function() {
      const params = new URLSearchParams({
        with: 'objective_stats,objective_strategic_objective',
        sort: this.sort,
        size: 10,
      })
      if (this.searchableString != null) params.set('s', this.searchableString)
      if (this.categorySelected != null) params.set('category', this.categorySelected)
      if (this.strategicObjectiveSelected != null) params.set('strategic_objective', this.strategicObjectiveSelected)
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
      const strategicObjectiveCategory = this.findStrategicObjectiveCategory(this.strategicObjectiveSelected)
      if (categoryId != null && strategicObjectiveCategory && strategicObjectiveCategory.id !== categoryId) {
        this.strategicObjectiveSelected = null
      }
    },
    strategicObjectiveSelected: function(strategicObjectiveId){
      const strategicObjectiveCategory = this.findStrategicObjectiveCategory(strategicObjectiveId)
      if (strategicObjectiveCategory && this.categorySelected == null) {
        this.categorySelected = strategicObjectiveCategory.id
      }
    },
    urlGet: function(){
      this.writeFiltersToUrl()
      this.fetchObjectives()
    }
  }
}
</script>

<style lang="scss" scoped>
.category-active{
  background-color: #2c59fb !important;
  color: #FFF !important;
  i{
  color: #FFF !important;
  }
}
.objectives-list--loading{
  opacity: .5;
  transition: opacity .15s ease-in-out;
}
</style>
