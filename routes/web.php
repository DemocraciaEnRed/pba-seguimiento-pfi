<?php

use App\Http\Controllers\MiscController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminPanelController;
use App\Http\Controllers\InitiativeController;
use App\Http\Controllers\PfiCategoryController;
use App\Http\Controllers\PfiMembershipController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserPanelController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ObjectiveController;
use App\Http\Controllers\ObjectivePanelController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\GoalPanelController;
use App\Http\Controllers\GoalAssignmentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportPanelController;
use App\Http\Controllers\StructureImportController;
use App\Http\Controllers\SearchEngineController;
use App\Http\Middleware\EnsureMapsEnabled;

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/reportes/{reportId}/adjuntos/{kind}/{attachmentId}', [\App\Http\Controllers\ReportAttachmentController::class, 'show'])
    ->where('kind', 'file|photo|thumbnail')->name('reports.attachments');
Route::post('/reportes/{reportId}/aprobar', [\App\Http\Controllers\ReportReviewController::class, 'approve'])->name('reports.review.approve');
Route::post('/reportes/{reportId}/devolver', [\App\Http\Controllers\ReportReviewController::class, 'returnReport'])->name('reports.review.return');
Route::post('/reportes/{reportId}/reenviar', [\App\Http\Controllers\ReportReviewController::class, 'resubmit'])->name('reports.review.resubmit');
Route::get('/robots.txt', [SearchEngineController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SearchEngineController::class, 'sitemap'])->name('sitemap');
Route::get('/sitemaps/{section}/{page}.xml', [SearchEngineController::class, 'sitemapPart'])
    ->where('section', 'pages|objectives|goals|reports|events')
    ->where('page', '[1-9][0-9]{0,8}')
    ->name('sitemap.part');
Route::get('/start', [MiscController::class, 'start'])->name('start');
Route::post('/start', [MiscController::class, 'startApp'])->name('start.form');
// Route::get('/testmail', [MiscController::class, 'testEmail'])->name('misc.testEmail');
Route::get('/testing', [MiscController::class, 'testing'])->name('testing');

Route::group([
    'as' => 'about.',
    'prefix' => 'acerca',
    ],function () {
    Route::get('/general', [HomeController::class, 'viewAboutGeneral'])->name('general');
    Route::get('/faq', [HomeController::class, 'viewAboutQuestions'])->name('faq');
    Route::get('/legales', [HomeController::class, 'viewAboutLegals'])->name('legal');
});

Route::group([
    'as' => 'aboutTwo.',
    'prefix' => 'acerca2',
    ],function () {
    Route::get('/general', [HomeController::class, 'viewAboutGeneralTwo'])->name('general');
    Route::get('/faq', [HomeController::class, 'viewAboutQuestionsTwo'])->name('faq');
    Route::get('/legales', [HomeController::class, 'viewAboutLegalsTwo'])->name('legal');
});

Route::group([
    'as' => 'events.',
    'prefix' => 'eventos',
    ],function () {
    Route::get('/', [EventController::class, 'showUpcomingEvents'])->name('upcoming');
    Route::get('/pasados', [EventController::class, 'showPastEvents'])->name('past');
    Route::get('/{eventId}', [EventController::class, 'index'])->name('index');
});

Auth::routes(['verify' => true]);

Route::group([
    'as' => 'panel.',
    'prefix' => 'panel',
    ],function () {
    Route::get('/', [UserPanelController::class, 'index'])->name('index');
    // Mis objetivos
    Route::get('/objetivos', [UserPanelController::class, 'viewListObjectives'])->name('objectives');
    // Mis suscripciones
    Route::get('/suscripciones', [UserPanelController::class, 'viewListSubscriptions'])->name('subscriptions');
    Route::get('/metas-responsables', [GoalAssignmentController::class, 'showResponsibleGoals'])->name('goal-assignments.index');
    Route::get('/metas-responsables/{goalId}', [GoalAssignmentController::class, 'showResponsibleGoal'])->whereNumber('goalId')->name('goal-assignments.goal');
    Route::post('/metas-responsables/{goalId}/reporteros/{userId}', [GoalAssignmentController::class, 'storeReporter'])->whereNumber('goalId')->whereNumber('userId')->name('goal-assignments.reporters.store');
    Route::delete('/metas-responsables/{goalId}/reporteros/{userId}', [GoalAssignmentController::class, 'destroyReporter'])->whereNumber('goalId')->whereNumber('userId')->name('goal-assignments.reporters.delete');
    Route::get('/metas-responsables/{goalId}/reporteros/{userId}/quitar', [GoalAssignmentController::class, 'confirmReporterRemoval'])->whereNumber('goalId')->whereNumber('userId')->name('goal-assignments.reporters.remove');
    Route::post('/suscripciones/{objectiveId}/desuscribir', [UserPanelController::class, 'formUnsubSubscription'])->name('subscriptions.unsubscribe.form');
    // Mis notificaciones
    Route::get('/notificaciones', [UserPanelController::class, 'viewListNotifications'])->name('notifications');
    Route::get('/notificaciones/pendientes', [UserPanelController::class, 'viewListUnreadNotifications'])->name('notifications.unread');
    Route::post('/notificaciones/pendientes/marcar', [UserPanelController::class, 'formMarkAllUnreadNotifications'])->name('notifications.mark.all.form');
    Route::post('/notificaciones/eliminar', [UserPanelController::class, 'formDeleteAllNotifications'])->name('notifications.delete.all.form');
    // Mi cuenta
    Route::get('/preferencias/verificar', [UserPanelController::class, 'viewVerifyAccount'])->name('account.verify');
    Route::get('/preferencias/datos', [UserPanelController::class, 'viewAccountData'])->name('account.data');
    Route::put('/preferencias/datos', [UserPanelController::class, 'formAccountData'])->name('account.data.form');
    Route::get('/preferencias/avatar', [UserPanelController::class, 'viewAccountAvatar'])->name('account.avatar');
    Route::post('/preferencias/avatar', [UserPanelController::class, 'formAccountAvatar'])->name('account.avatar.form');
    Route::get('/preferencias/acceso', [UserPanelController::class, 'viewAccountAccess'])->name('account.access');
    Route::put('/preferencias/acceso', [UserPanelController::class, 'formAccountAccess'])->name('account.access.form');
    Route::get('/preferencias/email', [UserPanelController::class, 'viewAccountEmail'])->name('account.email');
    Route::put('/preferencias/email', [UserPanelController::class, 'formAccountEmail'])->name('account.email.form');
    Route::get('/preferencias/notificationes', [UserPanelController::class, 'viewAccountNotifications'])->name('account.notifications');
    Route::put('/preferencias/notificationes', [UserPanelController::class, 'formAccountNotifications'])->name('account.notifications.form');
});

Route::group([
    'as' => 'admin.',
    'prefix' => 'admin',
    ],function () {
    Route::get('/', [AdminPanelController::class, 'index'])->name('index');
    Route::get('/bitacora', [AdminPanelController::class, 'viewLogs'])->name('logs');
    // Asignaciones por meta (sin reemplazar todavía el equipo heredado por objetivo)
    Route::get('/asignaciones-por-meta', [GoalAssignmentController::class, 'index'])->name('goal-assignments');
    Route::get('/objetivos/{objectiveId}/asignaciones', [GoalAssignmentController::class, 'showObjective'])->whereNumber('objectiveId')->name('goal-assignments.objective');
    Route::post('/objetivos/{objectiveId}/reporteros/{userId}/metas-existentes', [GoalAssignmentController::class, 'assignReporterToObjective'])->whereNumber('objectiveId')->whereNumber('userId')->name('goal-assignments.objective.reporters.store');
    Route::get('/metas/{goalId}/asignaciones', [GoalAssignmentController::class, 'showAdminGoal'])->whereNumber('goalId')->name('goal-assignments.goal');
    Route::post('/metas/{goalId}/responsables/{userId}', [GoalAssignmentController::class, 'storeResponsible'])->whereNumber('goalId')->whereNumber('userId')->name('goal-assignments.responsibles.store');
    Route::delete('/metas/{goalId}/responsables/{userId}', [GoalAssignmentController::class, 'destroyResponsible'])->whereNumber('goalId')->whereNumber('userId')->name('goal-assignments.responsibles.delete');
    Route::get('/metas/{goalId}/responsables/{userId}/quitar', [GoalAssignmentController::class, 'confirmResponsibleRemoval'])->whereNumber('goalId')->whereNumber('userId')->name('goal-assignments.responsibles.remove');
    Route::post('/metas/{goalId}/reporteros/{userId}', [GoalAssignmentController::class, 'storeReporter'])->whereNumber('goalId')->whereNumber('userId')->name('goal-assignments.reporters.store');
    Route::delete('/metas/{goalId}/reporteros/{userId}', [GoalAssignmentController::class, 'destroyReporter'])->whereNumber('goalId')->whereNumber('userId')->name('goal-assignments.reporters.delete');
    Route::get('/metas/{goalId}/reporteros/{userId}/quitar', [GoalAssignmentController::class, 'confirmReporterRemoval'])->whereNumber('goalId')->whereNumber('userId')->name('goal-assignments.reporters.remove');
    // Reportes
    Route::get('/reportes', [AdminPanelController::class, 'viewListReports'])->name('reports');
    Route::get('/reportes/descargar', [AdminPanelController::class, 'downloadReports'])->name('reports.download');
    // Comentarios
    Route::get('/comentarios', [AdminPanelController::class, 'viewListComments'])->name('comments');
    Route::get('/comentarios/descargar', [AdminPanelController::class, 'downloadComments'])->name('comments.download');
    Route::delete('/comentarios/{commentId}/eliminar', [AdminPanelController::class, 'formDeleteComment'])->name('comments.delete.form');
    // Settings
    Route::put('/configuracion/editar', [AdminPanelController::class, 'formEditSetting'])->name('settings.form');
    Route::put('/configuracion/editar/file', [AdminPanelController::class, 'formEditFileSetting'])->name('settings.form.file');
    Route::get('/configuracion/editar/map', [AdminPanelController::class, 'viewEditMapSettings'])->name('settings.map')->middleware(EnsureMapsEnabled::class);
    Route::put('/configuracion/editar/map/default', [AdminPanelController::class, 'formEditMapSetting'])->name('settings.map.form')->middleware(EnsureMapsEnabled::class);
    Route::get('/configuracion/editar/homepage', [AdminPanelController::class, 'viewEditHomepageSettings'])->name('settings.homepage');
    Route::get('/configuracion/editar/seo', [AdminPanelController::class, 'viewEditSeoSettings'])->name('settings.seo');
    Route::post('/configuracion/cache', [AdminPanelController::class, 'clearCacheSettings'])->name('settings.cache');
    // Categorias
    Route::get('/categorias', [AdminPanelController::class, 'viewListCategories'])->name('categories');
    Route::get('/categorias/nuevo', [AdminPanelController::class, 'viewCreateCategory'])->name('categories.create');
    Route::post('/categorias/nuevo', [AdminPanelController::class, 'formCreateCategory'])->name('categories.create.form');
    Route::get('/categorias/{categoryId}/editar', [AdminPanelController::class, 'viewEditCategory'])->name('categories.edit');
    Route::put('/categorias/{categoryId}/editar', [AdminPanelController::class, 'formEditCategory'])->name('categories.edit.form');
    Route::get('/categorias/{categoryId}/eliminar', [AdminPanelController::class, 'viewDeleteCategory'])->name('categories.delete');
    Route::delete('/categorias/{categoryId}/eliminar', [AdminPanelController::class, 'formDeleteCategory'])->name('categories.delete.form');
    // Objetivos Estrategicos
    Route::get('/objetivos-estrategicos', [AdminPanelController::class, 'viewListStrategicObjectives'])->name('strategic-objectives');
    Route::get('/objetivos-estrategicos/{strategicObjectiveId}/eliminar', [AdminPanelController::class, 'viewDeleteStrategicObjective'])->name('strategic-objectives.delete');
    Route::delete('/objetivos-estrategicos/{strategicObjectiveId}/eliminar', [AdminPanelController::class, 'formDeleteStrategicObjective'])->name('strategic-objectives.delete.form');
    // Organizaciones
    Route::get('/iniciativas', [InitiativeController::class, 'index'])->name('initiatives');
    Route::get('/iniciativas/nueva', [InitiativeController::class, 'create'])->name('initiatives.create');
    Route::post('/iniciativas/nueva', [InitiativeController::class, 'store'])->name('initiatives.create.form');
    Route::get('/iniciativas/{initiative}/editar', [InitiativeController::class, 'edit'])->name('initiatives.edit');
    Route::put('/iniciativas/{initiative}/editar', [InitiativeController::class, 'update'])->name('initiatives.edit.form');
    Route::get('/iniciativas/{initiative}/eliminar', [InitiativeController::class, 'delete'])->name('initiatives.delete');
    Route::delete('/iniciativas/{initiative}/eliminar', [InitiativeController::class, 'destroy'])->name('initiatives.delete.form');
    Route::get('/organizaciones', [AdminPanelController::class, 'viewListOrganizations'])->name('organizations');
    Route::get('/organizaciones/nuevo', [AdminPanelController::class, 'viewCreateOrganization'])->name('organizations.create');
    Route::post('/organizaciones/nuevo', [AdminPanelController::class, 'formCreateOrganization'])->name('organizations.create.form');
    Route::get('/organizaciones/{organizationId}/editar', [AdminPanelController::class, 'viewEditOrganization'])->name('organizations.edit');
    Route::put('/organizaciones/{organizationId}/editar', [AdminPanelController::class, 'formEditOrganization'])->name('organizations.edit.form');
    Route::get('/organizaciones/{organizationId}/eliminar', [AdminPanelController::class, 'viewDeleteOrganization'])->name('organizations.delete');
    Route::delete('/organizaciones/{organizationId}/eliminar', [AdminPanelController::class, 'formDeleteOrganization'])->name('organizations.delete.form');
    // Usuarios
    Route::get('/usuarios', [AdminPanelController::class, 'viewListUsers'])->name('users');
    Route::get('/usuarios/descargar', [AdminPanelController::class, 'downloadUsers'])->name('users.download');
    Route::get('/usuarios/{userId}', [AdminPanelController::class, 'viewUser'])->whereNumber('userId')->name('users.show');
    Route::put('/usuarios/{userId}', [AdminPanelController::class, 'formUpdateUser'])->whereNumber('userId')->name('users.update.form');
    Route::post('/usuarios/{userId}/verificar', [AdminPanelController::class, 'formVerifyUser'])->name('users.verify.form');
    Route::post('/usuarios/{userId}/reenviar-verificacion', [AdminPanelController::class, 'formResendUserVerification'])->name('users.verification.form');
    Route::post('/usuarios/{userId}/restablecer-clave', [AdminPanelController::class, 'formSendUserPasswordReset'])->name('users.password.form');
    Route::post('/usuarios/{userId}/administrador', [AdminPanelController::class, 'formGrantUserAdmin'])->name('users.admin.grant.form');
    Route::delete('/usuarios/{userId}/administrador', [AdminPanelController::class, 'formRevokeUserAdmin'])->name('users.admin.revoke.form');
    Route::get('/usuarios/{userId}/objetivos', [AdminPanelController::class, 'viewUserObjectives'])->name('users.objectives');
    Route::post('/usuarios/{userId}/pfi', [PfiMembershipController::class, 'store'])->whereNumber('userId')->name('users.pfi.store.form');
    Route::put('/usuarios/{userId}/pfi', [PfiMembershipController::class, 'update'])->whereNumber('userId')->name('users.pfi.update.form');
    Route::get('/usuarios/{userId}/pfi/quitar', [PfiMembershipController::class, 'confirmRemoval'])->whereNumber('userId')->name('users.pfi.remove');
    Route::delete('/usuarios/{userId}/pfi', [PfiMembershipController::class, 'destroy'])->whereNumber('userId')->name('users.pfi.delete.form');
    // Categorías PFI
    Route::get('/categorias-pfi', [PfiCategoryController::class, 'index'])->name('pfi-categories');
    Route::get('/categorias-pfi/nueva', [PfiCategoryController::class, 'create'])->name('pfi-categories.create');
    Route::post('/categorias-pfi/nueva', [PfiCategoryController::class, 'store'])->name('pfi-categories.create.form');
    Route::get('/categorias-pfi/{pfiCategory}/editar', [PfiCategoryController::class, 'edit'])->name('pfi-categories.edit');
    Route::put('/categorias-pfi/{pfiCategory}/editar', [PfiCategoryController::class, 'update'])->name('pfi-categories.edit.form');
    // Objectives
    Route::get('/objetivos', [AdminPanelController::class, 'viewListObjectives'])->name('objectives');
    Route::get('/objetivos/descargar', [AdminPanelController::class, 'downloadListObjectives'])->name('objectives.download');
    Route::get('/objetivos/nuevo', [AdminPanelController::class, 'viewCreateObjective'])->name('objectives.create');
    Route::post('/objetivos/nuevo', [AdminPanelController::class, 'formCreateObjective'])->name('objectives.create.form');
    // Importar estructura
    Route::get('/importar', [StructureImportController::class, 'index'])->name('import');
    Route::post('/importar', [StructureImportController::class, 'upload'])->name('import.upload');
    Route::get('/importar/ejemplo', [StructureImportController::class, 'downloadExample'])->name('import.example');
    Route::get('/importar/plantilla', [StructureImportController::class, 'downloadTemplate'])->name('import.template');
    Route::get('/importar/vista-previa', [StructureImportController::class, 'preview'])->name('import.preview');
    Route::post('/importar/confirmar', [StructureImportController::class, 'confirm'])->name('import.confirm');
    // Events
    Route::get('/eventos', [AdminPanelController::class, 'viewUpcomingEvents'])->name('events');
    Route::get('/eventos/pasados', [AdminPanelController::class, 'viewPastEvents'])->name('events.past');
    Route::get('/eventos/nuevo', [AdminPanelController::class, 'viewCreateEvent'])->name('events.create');
    Route::post('/eventos/nuevo', [AdminPanelController::class, 'formCreateEvent'])->name('events.create.form');
    Route::get('/eventos/{eventId}/editar', [AdminPanelController::class, 'viewEditEvent'])->name('events.edit');
    Route::put('/eventos/{eventId}/editar', [AdminPanelController::class, 'formEditEvent'])->name('events.edit.form');
    Route::post('/eventos/{eventId}/fotos/agregar', [AdminPanelController::class, 'formAddPictureEvent'])->name('events.pictures.add');
    Route::delete('/eventos/{eventId}/fotos/{pictureId}/eliminar', [AdminPanelController::class, 'formDeletePictureEvent'])->name('events.pictures.delete');
    Route::get('/eventos/{eventId}/eliminar', [AdminPanelController::class, 'viewDeleteEvent'])->name('events.delete');
    Route::delete('/eventos/{eventId}/eliminar', [AdminPanelController::class, 'formDeleteEvent'])->name('events.delete.form');
    // Preguntas Frecuentes
    Route::get('/preguntas-frecuentes', [AdminPanelController::class, 'viewListFaqs'])->name('faqs');
    Route::get('/preguntas-frecuentes/nuevo', [AdminPanelController::class, 'viewCreateFaq'])->name('faqs.create');
    Route::post('/preguntas-frecuentes/nuevo', [AdminPanelController::class, 'formCreateFaq'])->name('faqs.create.form');
    Route::get('/preguntas-frecuentes/{faqId}/editar', [AdminPanelController::class, 'viewEditFaq'])->name('faqs.edit');
    Route::put('/preguntas-frecuentes/{faqId}/editar', [AdminPanelController::class, 'formEditFaq'])->name('faqs.edit.form');
    Route::get('/preguntas-frecuentes/{faqId}/eliminar', [AdminPanelController::class, 'viewDeleteFaq'])->name('faqs.delete');
    Route::delete('/preguntas-frecuentes/{faqId}/eliminar', [AdminPanelController::class, 'formDeleteFaq'])->name('faqs.delete.form');
});

Route::group([
    'as' => 'apiService.',
    'prefix' => 'api-service',
    ],function () {
    // Userss
    Route::get('/home/stats', [HomeController::class, 'fetchStats'])->name('home.stats');
    Route::get('/users', [UserController::class, 'fetch'])->name('users');
    Route::get('/users/avatar', [UserController::class, 'fetchAvatar'])->name('users.avatar');
    Route::get('/pfi/candidates', [UserController::class, 'fetchPfiCandidates'])->name('pfi.candidates');
    Route::get('/users/{id}', [UserController::class, 'fetchOne'])->name('users.fetch');
    Route::put('/notification/read', [NotificationController::class, 'markAllRead'])->name('notification.mark.all');
    Route::put('/notification/read/{id}', [NotificationController::class, 'markOneRead'])->name('notification.mark.one');
    Route::delete('/notification/clean', [NotificationController::class, 'cleanAll'])->name('notification.clean');
    Route::get('/reports', [ReportController::class, 'fetch'])->name('reports');
    Route::get('/reports/{reportId}/comments', [ReportController::class, 'fetchComments'])->name('reports.comments');
    Route::post('/reports/{reportId}/comments', [ReportController::class, 'runCreateComment'])->name('reports.comments.create');
    Route::put('/reports/{reportId}/comments/{commentId}/edit', [ReportController::class, 'runEditComment'])->name('reports.comments.edit');
    Route::post('/reports/{reportId}/comments/{commentId}/reply', [ReportController::class, 'runCreateReply'])->name('reports.comments.reply');
    Route::delete('/reports/{reportId}/comments/{commentId}/delete', [ReportController::class, 'runDeleteComment'])->name('reports.comments.delete');
    Route::put('/reports/{reportId}/comments/{commentId}/reply/{replyId}/edit', [ReportController::class, 'runEditReply'])->name('reports.comments.reply.edit');
    Route::delete('/reports/{reportId}/comments/{commentId}/reply/{replyId}/delete', [ReportController::class, 'runDeleteReply'])->name('reports.comments.reply.delete');
    Route::post('/reports/{reportId}/testimony', [ReportController::class, 'runToggleTestimony'])->name('reports.testimonies.run');
    Route::get('/objectives', [ObjectiveController::class, 'fetch'])->name('objectives');
    Route::get('/objectives/{objectiveId}', [ObjectiveController::class, 'fetchOne'])->name('objectives.fetch');
    Route::get('/objectives/{objectiveId}/reports', [ObjectiveController::class, 'fetchReports'])->name('objectives.reports');
    Route::get('/objectives/{objectiveId}/stats', [ObjectiveController::class, 'fetchStats'])->name('objectives.stats');
    Route::get('/goal/{goalId}/reports', [GoalController::class, 'fetchReports'])->name('goals.reports');
    Route::get('/goal/{goalId}/management-reports', [GoalController::class, 'fetchManagementReports'])
        ->middleware('auth')
        ->name('goals.management-reports');



});

Route::get('/objetivos', [ObjectiveController::class, 'viewList'])->name('objectives');
Route::get('/plan', [ObjectiveController::class, 'viewCatalog'])->name('catalog');
Route::permanentRedirect('/catalogo', '/plan');
Route::post('/objetivos/{objectiveId}/subscribirse', [ObjectiveController::class, 'formToggleSubscription'])->name('objectives.subscribers.form');
Route::get('/seguimiento', [ReportController::class, 'viewList'])->name('reports');
Route::permanentRedirect('/reportes', '/seguimiento');
Route::get('/reportes/{reportId}', [ReportController::class, 'index'])->name('reports.index');
Route::post('/reportes/{reportId}/testimony', [ReportController::class, 'formToggleTestimony'])->name('reports.testimonies.form');
Route::get('/metas/{goalId}', [GoalController::class, 'index'])->name('goals.index');

Route::group([
    'as' => 'objectives.',
    'prefix' => 'objetivos',
    ],function () {
    Route::get('/{objectiveId}', [ObjectiveController::class, 'index'])->name('index');
    // Manage
    Route::group([
        'as' => 'manage.',
        'prefix' => '/{objectiveId}/administrar',
        ],function () {
        Route::get('/', [ObjectivePanelController::class, 'index'])->name('index');
        Route::get('/editar', [ObjectivePanelController::class, 'viewEditObjective'])->name('edit');
        Route::put('/editar', [ObjectivePanelController::class, 'formEditObjective'])->name('edit.form');
        // Administracion
        Route::get('/logs', [ObjectivePanelController::class, 'viewObjectiveLogs'])->name('logs');
        Route::get('/configuracion', [ObjectivePanelController::class, 'viewObjectiveConfiguration'])->name('configuration');
        Route::put('/configuracion/ocultar', [ObjectivePanelController::class, 'formObjectiveConfigurationHide'])->name('configuration.hide.form');
        Route::put('/configuracion/mapa', [ObjectivePanelController::class, 'formObjectiveConfigurationMap'])->name('configuration.map.form')->middleware(EnsureMapsEnabled::class);
        Route::get('/portada', [ObjectivePanelController::class, 'viewObjectiveCover'])->name('cover');
        Route::post('/portada', [ObjectivePanelController::class, 'formObjectiveCover'])->name('cover.form');
        Route::get('/archivos', [ObjectivePanelController::class, 'viewObjectiveFiles'])->name('files');
        Route::post('/archivos', [ObjectivePanelController::class, 'formObjectiveFile'])->name('files.form');
        Route::get('/mapa', [ObjectivePanelController::class, 'viewObjectiveMap'])->name('map')->middleware(EnsureMapsEnabled::class);
        Route::delete('/eliminar', [ObjectivePanelController::class, 'formDeleteObjective'])->name('delete.form');
        // Comentarios
        Route::get('/comentarios', [ObjectivePanelController::class, 'viewListComments'])->name('comments');
        Route::get('/comentarios/descargar', [ObjectivePanelController::class, 'downloadComments'])->name('comments.download');
        Route::delete('/comentarios/{commentId}/eliminar', [ObjectivePanelController::class, 'formDeleteComment'])->name('comments.delete.form');
        // Suscriptores
        Route::get('/suscriptores', [ObjectivePanelController::class, 'viewListSubscribers'])->name('subscribers');
        Route::get('/suscriptores/descargar', [ObjectivePanelController::class, 'downloadListSubscribers'])->name('subscribers.download');
        // Equipo
        Route::get('/equipo', [ObjectivePanelController::class, 'viewListTeam'])->name('team');
        // Equipo
        Route::get('/comunidades', [ObjectivePanelController::class, 'viewListCommunities'])->name('communities');
        Route::get('/comunidades/agregar', [ObjectivePanelController::class, 'viewAddCommunities'])->name('communities.add');
        Route::post('/comunidades/agregar', [ObjectivePanelController::class, 'formAddCommunities'])->name('communities.add.form');
        Route::delete('/comunidades/{communityId}/eliminar', [ObjectivePanelController::class, 'formRemoveCommunities'])->name('communities.remove.form');
        // Metas
        Route::get('/metas', [ObjectivePanelController::class, 'viewListGoals'])->name('goals');
        Route::get('/metas/descargar', [ObjectivePanelController::class, 'downloadListGoals'])->name('goals.download');
        Route::get('/metas/indicadores/descargar', [ObjectivePanelController::class, 'downloadGoalIndicators'])->name('goals.indicators.download');
        Route::get('/metas/nuevo', [ObjectivePanelController::class, 'viewAddGoal'])->name('goals.add');
        Route::post('/metas/nuevo', [ObjectivePanelController::class, 'formAddGoal'])->name('goals.add.form');
        Route::get('/metas/{goalId}', [GoalPanelController::class, 'viewGoal'])->name('goals.index');
        Route::get('/metas/{goalId}/editar', [GoalPanelController::class, 'viewEditGoal'])->name('goals.edit');
        Route::put('/metas/{goalId}/editar', [GoalPanelController::class, 'formEditGoal'])->name('goals.edit.form');
        // Hitos
        Route::get('/metas/{goalId}/hitos', [GoalPanelController::class, 'viewListGoalMilestones'])->name('goals.milestones');
        Route::get('/metas/{goalId}/hitos/nuevo', [GoalPanelController::class, 'viewAddGoalMilestone'])->name('goals.milestones.add');
        Route::post('/metas/{goalId}/hitos/nuevo', [GoalPanelController::class, 'formAddGoalMilestone'])->name('goals.milestones.add.form');
        Route::get('/metas/{goalId}/hitos/{milestoneId}/editar', [GoalPanelController::class, 'viewEditGoalMilestone'])->name('goals.milestones.edit');
        Route::put('/metas/{goalId}/hitos/{milestoneId}/editar', [GoalPanelController::class, 'formEditGoalMilestone'])->name('goals.milestones.edit.form');
        Route::get('/metas/{goalId}/hitos/{milestoneId}/eliminar', [GoalPanelController::class, 'viewDeleteGoalMilestone'])->name('goals.milestones.delete');
        Route::delete('/metas/{goalId}/hitos/{milestoneId}/eliminar', [GoalPanelController::class, 'formDeleteGoalMilestone'])->name('goals.milestones.delete.form');
        Route::get('/metas/{goalId}/configuracion', [GoalPanelController::class, 'viewGoalConfiguration'])->name('goals.configuration');
        Route::delete('/metas/{goalId}/eliminar', [GoalPanelController::class, 'formDeleteGoal'])->name('goals.delete.form');
        // Comentarios de la meta
        Route::get('/metas/{goalId}/comentarios', [GoalPanelController::class, 'viewListGoalComments'])->name('goals.comments');
        Route::get('/metas/{goalId}/comentarios/descargar', [GoalPanelController::class, 'downloadGoalComments'])->name('goals.comments.download');
        Route::delete('/metas/{goalId}/comentarios/{commentId}/eliminar', [GoalPanelController::class, 'formDeleteGoalComment'])->name('goals.comments.delete.form');
        // Períodos
        Route::post('/metas/{goalId}/periodos/{periodId}/omitir', [GoalPanelController::class, 'formSkipGoalPeriod'])->name('goals.periods.skip.form');
        Route::delete('/metas/{goalId}/periodos/{periodId}/omitir', [GoalPanelController::class, 'formUnskipGoalPeriod'])->name('goals.periods.unskip.form');
        // Reporte
        Route::get('/metas/{goalId}/reportes', [GoalPanelController::class, 'viewListGoalReports'])->name('goals.reports');
        Route::get('/metas/{goalId}/reportes/descargar', [GoalPanelController::class, 'downloadListGoalReports'])->name('goals.reports.download');
        Route::get('/metas/{goalId}/reportes/nuevo', [GoalPanelController::class, 'viewNewGoalReport'])->name('goals.reports.add');
        Route::post('/metas/{goalId}/reportes/nuevo', [GoalPanelController::class, 'formNewGoalReport'])->name('goals.reports.add.form');
        Route::get('/metas/{goalId}/reportes/{reportId}', [ReportPanelController::class, 'viewReport'])->name('goals.reports.index');
        Route::put('/metas/{goalId}/reportes/{reportId}/atencion', [ReportPanelController::class, 'reassignAttendant'])->name('goals.reports.attendant');
        Route::get('/metas/{goalId}/reportes/{reportId}/editar', [ReportPanelController::class, 'viewEditReport'])->name('goals.reports.edit');
        Route::put('/metas/{goalId}/reportes/{reportId}/editar', [ReportPanelController::class, 'formEditReport'])->name('goals.reports.edit.form');
        Route::get('/metas/{goalId}/reportes/{reportId}/comentarios', [ReportPanelController::class, 'viewReportComments'])->name('goals.reports.comments');
        Route::get('/metas/{goalId}/reportes/{reportId}/comentarios/descargar', [ReportPanelController::class, 'downloadReportComments'])->name('goals.reports.comments.download');
        Route::get('/metas/{goalId}/reportes/{reportId}/feedbacks', [ReportPanelController::class, 'viewReportTestimonies'])->name('goals.reports.testimonies');
        Route::get('/metas/{goalId}/reportes/{reportId}/feedbacks/descargar', [ReportPanelController::class, 'downloadReportTestimonies'])->name('goals.reports.testimonies.download');
        Route::get('/metas/{goalId}/reportes/{reportId}/archivos', [ReportPanelController::class, 'viewReportFiles'])->name('goals.reports.files');
        Route::post('/metas/{goalId}/reportes/{reportId}/archivos', [ReportPanelController::class, 'formReportFile'])->name('goals.reports.files.form');
        Route::get('/metas/{goalId}/reportes/{reportId}/album', [ReportPanelController::class, 'viewReportAlbum'])->name('goals.reports.album');
        Route::post('/metas/{goalId}/reportes/{reportId}/album', [ReportPanelController::class, 'formReportAlbum'])->name('goals.reports.album.form');
        Route::delete('/metas/{goalId}/reportes/{reportId}/album/{pictureId}/eliminar', [ReportPanelController::class, 'formDeletePictureReport'])->name('goals.reports.album.delete.form');
        Route::get('/metas/{goalId}/reportes/{reportId}/mapa', [ReportPanelController::class, 'viewReportMap'])->name('goals.reports.map')->middleware(EnsureMapsEnabled::class);
        Route::put('/metas/{goalId}/reportes/{reportId}/mapa', [ReportPanelController::class, 'formReportMap'])->name('goals.reports.map.form')->middleware(EnsureMapsEnabled::class);
        Route::get('/metas/{goalId}/reportes/{reportId}/configuracion', [ReportPanelController::class, 'viewReportConfiguration'])->name('goals.reports.configuration');
        Route::delete('/metas/{goalId}/reportes/{reportId}/eliminar', [ReportPanelController::class, 'formDeleteReport'])->name('goals.reports.delete.form');
    });
});
