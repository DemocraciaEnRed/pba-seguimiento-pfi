---
paths:
  - 'tests/**'
---

# Tests

## Objectives are hidden by default
`objectives.hidden` defaults to true in the schema. Portal queries filter `hidden = false`, so test objectives meant to be public must set `$objective->hidden = false` explicitly or they will be silently excluded.
