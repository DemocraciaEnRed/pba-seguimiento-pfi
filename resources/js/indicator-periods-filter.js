function filterPeriods(container) {
  const year = container.querySelector('[data-period-year]').value;
  container.querySelectorAll('[data-period-start-year]').forEach((row) => {
    row.hidden = year !== '' && (
      Number(year) < Number(row.dataset.periodStartYear)
      || Number(year) > Number(row.dataset.periodEndYear)
    );
  });
}

function initializePeriodFilters() {
  document.querySelectorAll('[data-indicator-periods]').forEach((container) => {
    const controls = container.querySelector('[data-period-year-controls]');
    if (controls) {
      filterPeriods(container);
      controls.hidden = false;
    }
  });
}

document.addEventListener('change', (event) => {
  if (event.target instanceof Element && event.target.matches('[data-period-year]')) {
    filterPeriods(event.target.closest('[data-indicator-periods]'));
  }
});

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializePeriodFilters);
} else {
  initializePeriodFilters();
}
