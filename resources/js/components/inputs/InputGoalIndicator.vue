<template>
  <section>
    <div class="form-group">
      <label><b>¿Cómo se mide esta meta?</b><span class="text-danger">*</span></label>
      <div class="custom-control custom-radio" v-for="mode in options.modes" :key="`mode-${mode.value}`">
        <input type="radio" :id="`mode-${mode.value}`" name="measurement_mode" :value="mode.value" v-model="form.measurement_mode" class="custom-control-input" :disabled="locked">
        <label class="custom-control-label is-clickable" :for="`mode-${mode.value}`">{{ mode.label }} <small class="text-muted">— {{ modeHelp[mode.value] }}</small></label>
      </div>
      <div class="alert alert-info mt-2 mb-0" v-if="locked">
        <i class="fas fa-lock fa-fw"></i>&nbsp;La meta ya tiene reportes de avance: el modo de medición, la dirección y la estructura de períodos no se pueden cambiar. Los valores objetivo de cada período sí.
      </div>
    </div>

    <template v-if="form.measurement_mode !== 'none'">
      <div class="form-group">
        <label><b>Indicador</b><span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="indicator" v-model="form.indicator" placeholder="Ej: Días promedio de emisión de certificados">
        <small class="form-text text-muted">Solo puede haber un indicador por meta. Tiene que ser mensurable, específico y asociado a un plazo.</small>
      </div>
      <div class="form-group">
        <label><b>Unidad del indicador</b><span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="indicator_unit" v-model="form.indicator_unit" placeholder="Ej: días, personas, %">
      </div>
    </template>

    <template v-if="form.measurement_mode === 'simple'">
      <div class="form-row">
        <div class="col">
          <div class="form-group">
            <label><b>Valor de meta (100%) del indicador</b><span class="text-danger">*</span></label>
            <input type="number" step="any" class="form-control" min="0" name="indicator_goal" v-model="form.indicator_goal" placeholder="Ej: 100">
            <small class="form-text text-muted">Valor que representa haber completado la meta al 100%.</small>
          </div>
        </div>
        <div class="col">
          <div class="form-group">
            <label><b>Valor actual del indicador</b></label>
            <input type="number" step="any" class="form-control" min="0" name="indicator_progress" v-model="form.indicator_progress" placeholder="Ej: 0">
            <small class="form-text text-muted">Los reportes de avance van sumando sobre este valor.</small>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label><b>Frecuencia de monitoreo</b> <small class="text-info">Opcional</small></label>
        <input type="text" class="form-control" name="indicator_frequency" v-model="form.indicator_frequency" placeholder="Ej: mensual, semestral, anual">
      </div>
    </template>

    <template v-if="isPeriodic">
      <div class="form-group" v-if="!locked">
        <label><b>Tipo de indicador</b></label>
        <div class="d-flex flex-wrap" style="gap: .5rem">
          <button type="button" class="btn btn-sm" v-for="preset in presets" :key="`preset-${preset.key}`" :class="activePreset === preset.key ? 'btn-primary' : 'btn-outline-primary'" @click="applyPreset(preset)">
            <i class="fas fa-fw" :class="preset.icon"></i>&nbsp;{{ preset.label }}
          </button>
        </div>
        <small class="form-text text-muted">Atajos que completan las preguntas de abajo. Podés ajustarlas después.</small>
      </div>

      <div class="form-group">
        <label><b>Para esta meta, ¿qué es un mejor desempeño?</b><span class="text-danger">*</span></label>
        <div class="custom-control custom-radio" v-for="direction in options.directions" :key="`direction-${direction.value}`">
          <input type="radio" :id="`direction-${direction.value}`" name="indicator_direction" :value="direction.value" v-model="form.indicator_direction" class="custom-control-input" :disabled="locked">
          <label class="custom-control-label is-clickable" :for="`direction-${direction.value}`">{{ direction.label }}</label>
        </div>
      </div>

      <div class="form-group">
        <label><b>¿Cómo se combinan los períodos?</b><span class="text-danger">*</span></label>
        <div class="custom-control custom-radio" v-for="nature in options.natures" :key="`nature-${nature.value}`">
          <input type="radio" :id="`nature-${nature.value}`" name="indicator_nature" :value="nature.value" v-model="form.indicator_nature" class="custom-control-input" :disabled="locked">
          <label class="custom-control-label is-clickable" :for="`nature-${nature.value}`">{{ nature.label }}</label>
        </div>
      </div>

      <div class="form-group" v-if="form.indicator_nature === 'extensive'">
        <label><b>El objetivo de cada período es…</b></label>
        <div class="custom-control custom-radio" v-for="semantic in options.semantics" :key="`semantic-${semantic.value}`">
          <input type="radio" :id="`semantic-${semantic.value}`" name="target_semantics" :value="semantic.value" v-model="form.target_semantics" class="custom-control-input" :disabled="locked">
          <label class="custom-control-label is-clickable" :for="`semantic-${semantic.value}`">{{ semantic.label }}</label>
        </div>
      </div>

      <div class="form-group">
        <label><b>Fórmula de cálculo</b> <small class="text-info">Opcional</small></label>
        <textarea class="form-control" name="indicator_formula" rows="2" v-model="form.indicator_formula" placeholder="Ej: ∑ (Fecha de emisión − Fecha de ingreso) / Cantidad de trámites del período"></textarea>
        <small class="form-text text-muted">Texto descriptivo de cómo el área obtiene el valor. La plataforma no la calcula: recibe el valor ya medido.</small>
      </div>

      <div class="form-row">
        <div class="col-md-4">
          <div class="form-group">
            <label><b>Periodicidad</b><span class="text-danger">*</span></label>
            <select class="custom-select" name="period_type" v-model="form.period_type" :disabled="locked">
              <option v-for="type in options.periodTypes" :key="`type-${type.value}`" :value="type.value">{{ type.label }}</option>
            </select>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label><b>Mes de inicio</b><span class="text-danger">*</span></label>
            <input type="month" class="form-control" name="period_start" v-model="form.period_start" :disabled="locked">
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label><b>Cantidad de períodos</b><span class="text-danger">*</span></label>
            <input type="number" class="form-control" name="period_count" min="1" :max="maxPeriods" v-model.number="form.period_count" :disabled="locked">
          </div>
        </div>
      </div>

      <div class="form-group">
        <label><b>Valor objetivo de cada período</b><span class="text-danger">*</span></label>
        <small class="form-text text-muted mb-2">Dejá vacío un período si no tiene objetivo: queda fuera del cálculo.</small>
        <table class="table table-sm table-bordered mb-0">
          <thead class="thead-light">
            <tr><th>Período</th><th>Rango</th><th style="width: 40%">Objetivo ({{ form.indicator_unit || 'unidad' }})</th></tr>
          </thead>
          <tbody>
            <tr v-for="period in periodsPreview" :key="`target-${period.number}`">
              <td class="align-middle">Período {{ period.number }}</td>
              <td class="align-middle text-muted">{{ period.range }}</td>
              <td><input type="number" step="any" min="0" class="form-control form-control-sm" :name="`period_targets[${period.number}]`" v-model="form.period_targets[period.number]"></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="alert" :class="`alert-${exampleColor}`" v-if="form.indicator_direction">
        <h6 class="is-700 mb-1"><i class="fas fa-circle-question fa-fw"></i>&nbsp;Verificá la dirección con un ejemplo</h6>
        Si el objetivo de un período es <b>{{ formatNumber(exampleTarget) }} {{ form.indicator_unit }}</b> y se informa
        <b>{{ formatNumber(exampleMeasured) }} {{ form.indicator_unit }}</b>, la meta va a mostrar
        <b>{{ examplePercentage }}% de cumplimiento</b> ({{ exampleColorLabel }}).
        <div class="custom-control custom-checkbox mt-2" v-if="!locked">
          <input type="checkbox" class="custom-control-input" id="direction-confirmation" required>
          <label class="custom-control-label is-clickable" for="direction-confirmation">Confirmo que así debe leerse esta meta</label>
        </div>
      </div>
    </template>
  </section>
</template>

<script>
const TOLERATED_DEVIATION = -0.10

export default {
  props: {
    initial: {
      type: Object,
      required: true
    },
    old: {
      type: [Object, Array],
      default: () => ({})
    },
    options: {
      type: Object,
      required: true
    }
  },
  data() {
    const oldInput = Array.isArray(this.old) ? {} : this.old
    const form = { ...this.initial }
    Object.keys(form).forEach(key => {
      if (key !== 'locked' && oldInput[key] !== undefined) {
        form[key] = oldInput[key]
      }
    })
    form.period_targets = Array.isArray(form.period_targets) ? { ...form.period_targets } : { ...(form.period_targets || {}) }

    return {
      form,
      selectedPreset: null,
      locked: Boolean(this.initial.locked),
      maxPeriods: 60,
      modeHelp: {
        none: 'se sigue solo con novedades e hitos',
        simple: 'un valor final al que se llega sumando avances',
        periodic: 'un objetivo por período que se contrasta con el valor medido'
      },
      presets: [
        { key: 'term', label: 'Plazo', icon: 'fa-hourglass-half', direction: 'lower_is_better', nature: 'intensive', semantics: 'incremental', unit: 'días' },
        { key: 'quantity', label: 'Cantidad', icon: 'fa-hashtag', direction: 'higher_is_better', nature: 'extensive', semantics: 'incremental', unit: '' },
        { key: 'progress', label: 'Grado de avance', icon: 'fa-percent', direction: 'higher_is_better', nature: 'extensive', semantics: 'incremental', unit: '%' }
      ]
    }
  },
  computed: {
    isPeriodic() {
      return this.form.measurement_mode === 'periodic'
    },
    activePreset() {
      const matching = this.presets.filter(candidate => candidate.direction === this.form.indicator_direction
        && candidate.nature === this.form.indicator_nature
        && (candidate.nature === 'intensive' || candidate.semantics === this.form.target_semantics))
      if (matching.length === 0) return null
      // Some presets share the same configuration, so the clicked one wins and the unit breaks ties.
      const preset = matching.find(candidate => candidate.key === this.selectedPreset)
        || matching.find(candidate => candidate.unit && candidate.unit === this.form.indicator_unit)
        || matching[0]
      return preset.key
    },
    periodMonths() {
      const type = this.options.periodTypes.find(candidate => candidate.value === this.form.period_type)
      return type ? type.months : 3
    },
    periodsPreview() {
      if (!this.form.period_start) return []
      const [year, month] = this.form.period_start.split('-').map(Number)
      const count = Math.min(Math.max(parseInt(this.form.period_count, 10) || 0, 0), this.maxPeriods)
      const formatter = new Intl.DateTimeFormat('es-AR', { month: 'short', year: 'numeric' })
      const periods = []
      for (let index = 0; index < count; index++) {
        const startsOn = new Date(year, month - 1 + index * this.periodMonths, 1)
        const endsOn = new Date(year, month - 1 + (index + 1) * this.periodMonths, 0)
        periods.push({ number: index + 1, range: `${formatter.format(startsOn)} – ${formatter.format(endsOn)}` })
      }
      return periods
    },
    exampleTarget() {
      const target = this.periodsPreview
        .map(period => parseFloat(this.form.period_targets[period.number]))
        .find(value => !isNaN(value) && value > 0)
      return target || 100
    },
    exampleMeasured() {
      return Math.round(this.exampleTarget * 0.9 * 100) / 100
    },
    exampleCompliance() {
      const target = this.exampleTarget
      const measured = this.exampleMeasured
      switch (this.form.indicator_direction) {
        case 'lower_is_better':
          return target / measured
        case 'target_is_better':
          return Math.max(0, 1 - Math.abs(measured - target) / target)
        default:
          return measured / target
      }
    },
    examplePercentage() {
      return Math.round(this.exampleCompliance * 100)
    },
    exampleColor() {
      const deviation = this.exampleCompliance - 1
      if (deviation >= 0) return 'success'
      if (deviation >= TOLERATED_DEVIATION) return 'warning'
      return 'danger'
    },
    exampleColorLabel() {
      return { success: 'verde, cumplido', warning: 'amarillo, levemente por debajo', danger: 'rojo, incumplido' }[this.exampleColor]
    }
  },
  methods: {
    applyPreset(preset) {
      this.selectedPreset = preset.key
      this.form.indicator_direction = preset.direction
      this.form.indicator_nature = preset.nature
      this.form.target_semantics = preset.semantics
      if (preset.unit && !this.form.indicator_unit) {
        this.form.indicator_unit = preset.unit
      }
    },
    formatNumber(value) {
      return new Intl.NumberFormat('es-AR', { maximumFractionDigits: 2 }).format(value)
    }
  },
  watch: {
    periodsPreview(periods) {
      periods.forEach(period => {
        if (this.form.period_targets[period.number] === undefined) {
          this.$set(this.form.period_targets, period.number, null)
        }
      })
    }
  },
  created() {
    this.periodsPreview.forEach(period => {
      if (this.form.period_targets[period.number] === undefined) {
        this.$set(this.form.period_targets, period.number, null)
      }
    })
  }
}
</script>
