---
paths:
  - 'app/Services/Workspace/**'
---

# Workspace

## Mesa de trabajo derivada del estado de los reportes
WorkDesk (/panel/mesa, Gate accessWorkDesk = admin o integrante PFI) deriva tareas de goal_user y review_status/attendant_id, nunca de notificaciones, y no concede permisos: los enlaces vuelven a pasar por ReportPolicy. "Requiere mi acción" = pending en metas donde es Responsable + returned con COALESCE(attendant_id, author_id) = usuario; un pending reasignado queda en "Esperando". Las alertas de cobertura solo consideran metas status = ongoing. Los contadores (summary) ignoran filtros.
