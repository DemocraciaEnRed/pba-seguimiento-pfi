---
paths:
  - 'app/**'
---

# App

## PFI membership and categories never grant permissions
Being a PFI member (`pfi_memberships`) or having a PFI category only makes a person selectable for assignments. Never derive access from them. Permissions come from roles and assignments only.
PFI categories are deactivated, never deleted. Members keep inactive categories they already have, but those can't be newly selected. Use `PfiMemberships::selectableCategories()`.
Search assignment candidates through `PfiMemberships::candidates()`, not `UserController::fetch`. The legacy «Agregar miembro» keeps `fetch` until Phase 4.

## Phase two assignments before authorization cutover
`goal_user` stores per-goal `responsible` / `reporter` assignments, but adding rows does not change current access checks. Keep `objective_user` intact until the coordinated Phase 4 cutover. Only PFI members are selectable; Admin assigns both roles, while a Responsible can assign/remove Reporters only on their own assigned goals.

## Scope data through GoalAudience
For goal/report listings, direct lookups, aggregates, exports, and public surfaces, constrain the query through `GoalAudience::publicQuery()`, `managementQuery($user)`, or `workspaceQuery($user)` according to the audience before applying filters or counts. Do not infer public visibility from the authenticated session; keep ID-based access consistent with the same audience.

## Phase two assignments before authorization cutover
Authorization now reads goal_user only: Responsible/Reporter act on their assigned goals; PFI membership/categories never grant access. objective_user is historical admin-only reference, never a permission source or assignment target. Only Admin changes structure or assigns Responsibles; Admin or a goal Responsible manages its Reporters.

## PFI membership and categories never grant permissions
Being a PFI member (pfi_memberships) or having a PFI category only makes a person selectable for assignments; neither grants access. Categories are deactivated, never deleted, and inactive categories already held can be kept but not newly selected (PfiMemberships::selectableCategories). Search assignment candidates through PfiMemberships::candidates(), never the legacy general user search.

## Per-goal cutover supersedes preparatory rules
This rule supersedes the earlier 'Phase two assignments before authorization cutover' restriction and the legacy-team-search exception: permissions use goal_user, and objective_user is admin-only historical reference. Assignment candidates must be PFI members. For membership/assignment removal and report writes, lock all participating users (actor, departing member and destinations) by ID before locking goals by ID, then reports; recheck permissions and exact pending destinations inside that transaction. Transfer attendant_id, never author_id. A departing Responsible needs continuity only when losing their last ability to attend; do not require a replacement Responsible.

## Per-objective team and organizations are retired
Phase 9 archived objective_user and objective_organization to the private `archive` disk (storage/app/private/archive) and dropped both tables; Objective::members() and User::objectives() no longer exist. This supersedes every earlier mention of objective_user as a historical reference. Teams exist only as per-goal assignments in goal_user, and organizations only per goal (goals.responsible_organization_id + goal_organization). Never reintroduce per-objective teams or organizations.
