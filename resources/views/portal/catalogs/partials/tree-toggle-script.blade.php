{{-- Botones [data-catalog-toggle] expanden/contraen los <details> del árbol indicado en data-catalog-target (por defecto, .catalog-tree). --}}
<script>
  document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-catalog-toggle]');
    if (!button) {
      return;
    }

    const shouldExpand = button.getAttribute('aria-expanded') !== 'true';
    const target = button.getAttribute('data-catalog-target') || '.catalog-tree';
    document.querySelectorAll(target).forEach(function (tree) {
      tree.querySelectorAll('details').forEach(function (branch) {
        branch.open = shouldExpand;
      });
    });

    button.setAttribute('aria-expanded', shouldExpand ? 'true' : 'false');
    button.querySelector('i').className = 'fas fa-fw mr-1 ' + (shouldExpand ? 'fa-compress-alt' : 'fa-expand-alt');
    button.querySelector('span').textContent = shouldExpand ? 'Contraer todo' : 'Expandir todo';
  });
</script>
