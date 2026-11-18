<?php

namespace App\Http\Controllers;

use Cache;
use Storage;
use Image;
use Log;
use Notification;
use App\Category;
use App\Initiative;
use App\StrategicObjective;
use App\Organization;
use App\Role;
use App\File;
use App\ImageFile;
use App\User;
use App\Event;
use App\Objective;
use App\Goal;
use App\Report;
use App\ActionLog;
use App\Comment;
use App\Faq;
use App\Setting;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Exports\ObjectivesExport;
use App\Exports\CommentsExport;
use App\Exports\UsersExport;
use App\Exports\ReportsExport;
use App\Notifications\RemoveObjectiveTeam;
use App\Services\Admin\UserDirectory;
use App\Services\Pfi\PfiMemberships;
use App\PfiCategory;
use App\Services\Admin\ReportDirectory;
use App\Services\Admin\ObjectiveDirectory;
use Illuminate\Support\Facades\Password;
use App\Services\Comments\CommentModeration;
use App\Services\Admin\ActivityRange;
use App\Services\Admin\AdminDashboardStats;
use App\Services\Csv\CsvDownload;
use App\Rules\MatchOldPassword;
use App\Notifications\NewEvent;
use App\Notifications\EditEvent;
use App\Notifications\DeleteEvent;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class AdminPanelController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Forces to be authenticated.
        $this->middleware('auth');
        $this->middleware('check_role:admin');
    }

    public function index(Request $request, AdminDashboardStats $dashboardStats, CommentModeration $commentModeration){
        $range = ActivityRange::fromRequest($request->query('rango'));
        $logs = ActionLog::where('context->type','!=','notifications')->orWhereNull('context->type')->orderBy('record_datetime','DESC')->take(3)->get();

        return view('admin.index', [
            'stats' => $dashboardStats->compute($range),
            'range' => $range,
            'ranges' => ActivityRange::cases(),
            'logs' => $logs,
            'latestComments' => $commentModeration->search(user: $request->user())->take(5)->get(),
        ]);
    }

    // ====================================
    // Admin - Reports
    // ====================================

    public function viewListReports(Request $request, ReportDirectory $reportDirectory){
        $filters = $reportDirectory->normalize($this->reportFilters($request));

        return view('admin.reports.list', [
            'pendingCount' => Report::where('review_status', 'pending')->whereHas('goal.objective')->count(),
            'validatedCount' => Report::where('review_status', 'validated')->whereNull('closed_at')->whereHas('goal.objective')->count(),
            'reports' => $reportDirectory->search($filters)->paginate(20)->withQueryString(),
            'filters' => $filters,
            ...$reportDirectory->hierarchyOptions($filters),
        ]);
    }

    public function downloadReports(Request $request, CsvDownload $csvDownload){
        return $csvDownload->download(
            new ReportsExport($this->reportFilters($request)),
            Carbon::now()->format('Ymd').'-reportes.csv',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function reportFilters(Request $request): array
    {
        return $request->only(['q', 'eje', 'objetivo', 'meta', 'desde', 'hasta', 'autor', 'comentarios', 'orden', 'revision']);
    }

    // ====================================
    // Admin - Comments
    // ====================================

    public function viewListComments(Request $request, CommentModeration $commentModeration){
        $search = $request->query('q');
        $type = $request->query('tipo');

        return view('admin.comments.list', [
            'comments' => $commentModeration->search($search, $type, user: $request->user())->paginate(25)->withQueryString(),
            'search' => $search,
            'type' => $type,
        ]);
    }

    public function downloadComments(Request $request, CsvDownload $csvDownload){
        return $csvDownload->download(
            new CommentsExport($request->query('q'), $request->query('tipo'), user: $request->user()),
            Carbon::now()->format('Ymd').'-comentarios.csv',
        );
    }

    public function formDeleteComment(Request $request, CommentModeration $commentModeration, $commentId){
        $comment = Comment::with(['user', 'commentable', 'parent.commentable'])->findOrFail($commentId);
        $commentModeration->delete($comment, $request->user(), 'Administrador');

        return redirect()->back()->with('success', CommentModeration::deletedMessage($comment));
    }


    // ====================================
    // Admin - Categories
    // ====================================

    public function viewListCategories(Request $request){
      $categories = Category::query()
          ->withCount(['strategicObjectives', 'objectives'])
          ->orderBy('order')
          ->get();
      return view('admin.categories.list',['categories' => $categories]);
    }
    public function viewCreateCategory(Request $request){
        return view('admin.categories.create');
    }
    public function formCreateCategory(Request $request){
        $rules = [
            'title' => 'required|string|max:255' ,
            'description' => 'nullable|string|max:2000',
            'icon' => ['required', 'string', Rule::in(array_keys(Category::AVAILABLE_ICONS))],
            'color' => 'required|string|max:100' ,
            'order' => 'required|integer|min:0',
            'strategic_objectives' => 'nullable|array',
            'strategic_objectives.*.codigo' => 'required|string|max:225',
            'strategic_objectives.*.title' => 'required|string|max:550',
        ];

        $validated = $request->validate($rules);

        DB::transaction(function () use ($validated): void {
            $category = new Category();
            $category->title = $validated['title'];
            $category->description = $validated['description'] ?? null;
            $category->icon = $validated['icon'];
            $category->color = $validated['color'];
            $category->order = $validated['order'];
            $category->save();

            foreach ($validated['strategic_objectives'] ?? [] as $strategicObjectiveData) {
                $strategicObjective = new StrategicObjective();
                $strategicObjective->codigo = $strategicObjectiveData['codigo'];
                $strategicObjective->title = $strategicObjectiveData['title'];
                $strategicObjective->category()->associate($category);
                $strategicObjective->save();
            }
        });

        Initiative::flushPublicCache();
        return redirect()->route('admin.categories')->with('success','La categoria ha sido creada correctamente');
    }
    public function viewEditCategory(Request $request, $categoryId){
        $category = Category::with('strategicObjectives')->findOrFail($categoryId);
        return view('admin.categories.edit',['category' => $category]);
    }
    public function formEditCategory(Request $request, $categoryId){
        $rules = [
            'title' => 'required|string|max:255' ,
            'description' => 'nullable|string|max:2000',
            'icon' => ['required', 'string', Rule::in(array_keys(Category::AVAILABLE_ICONS))],
            'color' => 'required|string|max:100' ,
            'order' => 'required|integer|min:0',
            'strategic_objectives' => 'nullable|array',
            'strategic_objectives.*.id' => 'nullable|integer|exists:strategic_objectives,id',
            'strategic_objectives.*.codigo' => 'required|string|max:225',
            'strategic_objectives.*.title' => 'required|string|max:550',
            'strategic_objectives.*.delete' => 'nullable|boolean',
        ];

        $validated = $request->validate($rules);

        DB::transaction(function () use ($validated, $categoryId): void {
            $category = Category::findOrFail($categoryId);
            $category->title = $validated['title'];
            $category->description = $validated['description'] ?? null;
            $category->icon = $validated['icon'];
            $category->color = $validated['color'];
            $category->order = $validated['order'];
            $category->save();

            foreach ($validated['strategic_objectives'] ?? [] as $strategicObjectiveData) {
                if (isset($strategicObjectiveData['id'])) {
                    $strategicObjective = StrategicObjective::where('category_id', $category->id)
                        ->findOrFail($strategicObjectiveData['id']);

                    if (!empty($strategicObjectiveData['delete'])) {
                        if ($strategicObjective->objectives()->exists()) {
                            throw ValidationException::withMessages([
                                'strategic_objectives' => "No se puede eliminar el objetivo estratégico '{$strategicObjective->title}' porque tiene objetivos generales asociados.",
                            ]);
                        }

                        $strategicObjective->delete();
                        continue;
                    }
                } else {
                    $strategicObjective = new StrategicObjective();
                    $strategicObjective->category()->associate($category);
                }

                $strategicObjective->codigo = $strategicObjectiveData['codigo'];
                $strategicObjective->title = $strategicObjectiveData['title'];
                $strategicObjective->save();
            }
        });

        Initiative::flushPublicCache();
        return redirect()->route('admin.categories')->with('success','La categoria ha sido editada correctamente');
    }
    public function viewDeleteCategory(Request $request, $categoryId){
        $category = Category::findorfail($categoryId);
        $categories = Category::orderBy('order')->get();
        if(count($categories) == 1){
            return redirect()->route('admin.categories')->with('warning','No puede eliminar la categoria porque se requiere migrar los objetivos de la categoria que eliminara a otra categoria. Cree una nueva categoria para poder migrarlos');
        }
        return view('admin.categories.delete',['category' => $category, 'categories' => $categories]);
    }
    public function formDeleteCategory(Request $request, $categoryId){
        $rules = [
            'password' =>  ['required', new MatchOldPassword],
            'category' => 'required|numeric|exists:categories,id|not_in:'.$categoryId,
        ];
        $request->validate($rules);

        $category = Category::findorfail($categoryId);
        $newCategory = Category::findorfail($request->input('category'));
        foreach ($category->strategicObjectives as $strategicObjective) {
            $strategicObjective->category()->associate($newCategory);
            $strategicObjective->save();
        }
        $category->delete();

        Initiative::flushPublicCache();
        return redirect()->route('admin.categories')->with('success','La categoria ha sido eliminada correctamente y los objetivos estratégicos han sido migrados a otra categoria');
    }

    // ====================================
    // Admin - Strategic Objectives
    // ====================================

    public function viewListStrategicObjectives(Request $request){
        $search = trim((string) $request->query('q', ''));
        $categoryId = filter_var($request->query('eje'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;

        $strategicObjectives = StrategicObjective::query()
            ->with('category')
            ->withCount('objectives')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('codigo', 'like', '%'.$search.'%')
                ->orWhere('title', 'like', '%'.$search.'%')))
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->orderBy('codigo')
            ->get();

        return view('admin.strategic-objectives.list', [
            'strategicObjectivesByCategory' => $strategicObjectives->groupBy('category_id')->sortBy(fn ($group) => $group->first()->category?->order),
            'categories' => Category::orderBy('order')->get(['id', 'title']),
            'filters' => ['q' => $search === '' ? null : $search, 'eje' => $categoryId],
        ]);
    }
    public function viewDeleteStrategicObjective(Request $request, $strategicObjectiveId){
        $strategicObjective = StrategicObjective::findorfail($strategicObjectiveId);
        $strategicObjectives = StrategicObjective::where('id','!=',$strategicObjectiveId)->get();
        if(count($strategicObjectives) == 0){
            return redirect()->route('admin.strategic-objectives')->with('warning','No puede eliminar el objetivo estratégico porque se requiere migrar los objetivos generales vinculados a otro objetivo estratégico. Cree un nuevo objetivo estratégico para poder migrarlos');
        }
        return view('admin.strategic-objectives.delete',['strategicObjective' => $strategicObjective, 'strategicObjectives' => $strategicObjectives]);
    }
    public function formDeleteStrategicObjective(Request $request, $strategicObjectiveId){
        $rules = [
            'password' =>  ['required', new MatchOldPassword],
            'strategic_objective' => 'required|numeric|exists:strategic_objectives,id|not_in:'.$strategicObjectiveId,
        ];
        $request->validate($rules);

        $strategicObjective = StrategicObjective::findorfail($strategicObjectiveId);
        $newStrategicObjective = StrategicObjective::findorfail($request->input('strategic_objective'));
        foreach ($strategicObjective->objectives as $objective) {
            $objective->strategicObjective()->associate($newStrategicObjective);
            $objective->save();
        }
        $strategicObjective->delete();

        return redirect()->route('admin.strategic-objectives')->with('success','El objetivo estratégico ha sido eliminado correctamente y los objetivos generales han sido migrados a otro objetivo estratégico');
    }

    // ====================================
    // Admin Organizations
    // ====================================

    public function viewListOrganizations(Request $request){
      $organizations = Organization::query()
          ->with('logo')
          ->withCount('objectives')
          ->orderBy('name')
          ->paginate(15);
      return view('admin.organizations.list',['organizations' => $organizations]);
    }
    public function viewCreateOrganization(Request $request){
        return view('admin.organizations.create');
    }
    public function formCreateOrganization(Request $request){
        $rules = [
            'name' => 'required|string|max:225',
            'description' => 'required|string|max:550',
            'logo' => 'image|nullable|max:1999'
        ];
        $request->validate($rules);

        // Handle data
        $newOrganization = new Organization();
        $newOrganization->name = $request->input('name');
        $newOrganization->description = $request->input('description');
        $newOrganization->save();
        //Handle Logo
        if($request->hasFile('logo')){
            $orgLogo = Image::make($request->file('logo'));
            $orgLogo->resize(300, 300, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $orgLogoThumbnail = Image::make($request->file('logo'));
            $orgLogoThumbnail->resize(96, 96, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            // Get mimeType
            $mimeType = $orgLogo->mime();
            // Get Extension
            $fileExtension = explode('/',$mimeType)[1];
            // Create New Name
            $fileName = 'org-'.$newOrganization->id.'-'.substr(uniqid(),-5).'.'.$fileExtension;
            $fileNameThumbnail = 'org-'.$newOrganization->id.'-'.substr(uniqid(),-5).'-thumbnail.'.$fileExtension;
            // Make the File path
            $filePath = '/storage/organizations/'.$fileName;
            $filePathThumbnail = '/storage/organizations/'.$fileNameThumbnail;
            // Save Logo
            Storage::disk('organizations')->put($fileName, (string) $orgLogo->encode());
            Storage::disk('organizations')->put($fileNameThumbnail, (string) $orgLogoThumbnail->encode());
            $imageFile = new ImageFile();
            $imageFile->name = $fileName;
            $imageFile->size = Storage::disk('organizations')->size($fileName);
            $imageFile->mime = $mimeType;
            $imageFile->path = $filePath;
            $imageFile->thumbnail_name = $fileNameThumbnail;
            $imageFile->thumbnail_size = Storage::disk('organizations')->size($fileNameThumbnail);
            $imageFile->thumbnail_mime = $mimeType;
            $imageFile->thumbnail_path = $filePathThumbnail;
            $newOrganization->logo()->save($imageFile);
        }

        return redirect()->route('admin.organizations')->with('success','La organizacion ha sido creada correctamente');
    }
    public function viewEditOrganization(Request $request, $organizationId){
        $organization = Organization::findOrFail($organizationId);
        return view('admin.organizations.edit',['organization' => $organization]);
    }
    public function formEditOrganization(Request $request, $organizationId){
        $rules = [
            'name' => 'required|string|max:225',
            'description' => 'required|string|max:550',
            'logo' => 'image|nullable|max:1999'
        ];
        $request->validate($rules);

        // Handle data
        $organization = Organization::findOrFail($organizationId);
        $organization->name = $request->input('name');
        $organization->description = $request->input('description');
        $organization->save();

        if($request->hasFile('logo')){
            //Has logo?
            if(!is_null($organization->logo)){
                Storage::disk('organizations')->delete($organization->logo->name);
                Storage::disk('organizations')->delete($organization->logo->thumbnail_name);
                $organization->logo->delete();
            }

            $orgLogo = Image::make($request->file('logo'));
            $orgLogo->resize(300, 300, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $orgLogoThumbnail = Image::make($request->file('logo'));
            $orgLogoThumbnail->resize(96, 96, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            // Get mimeType
            $mimeType = $orgLogo->mime();
            // Get Extension
            $fileExtension = explode('/',$mimeType)[1];
            // Create New Name
            $fileName = 'org-'.$organization->id.'-'.substr(uniqid(),-5).'.'.$fileExtension;
            $fileNameThumbnail = 'org-'.$organization->id.'-'.substr(uniqid(),-5).'-thumbnail.'.$fileExtension;
            // Make the File path
            $filePath = '/storage/organizations/'.$fileName;
            $filePathThumbnail = '/storage/organizations/'.$fileNameThumbnail;
            // Save Logo
            Storage::disk('organizations')->put($fileName, (string) $orgLogo->encode());
            Storage::disk('organizations')->put($fileNameThumbnail, (string) $orgLogoThumbnail->encode());
            $imageFile = new ImageFile();
            $imageFile->name = $fileName;
            $imageFile->size = Storage::disk('organizations')->size($fileName);
            $imageFile->mime = $mimeType;
            $imageFile->path = $filePath;
            $imageFile->thumbnail_name = $fileNameThumbnail;
            $imageFile->thumbnail_size = Storage::disk('organizations')->size($fileNameThumbnail);
            $imageFile->thumbnail_mime = $mimeType;
            $imageFile->thumbnail_path = $filePathThumbnail;
            $organization->logo()->save($imageFile);
        }
        return redirect()->route('admin.organizations')->with('success','La organizacion ha sido editada correctamente');
    }
    public function viewDeleteOrganization(Request $request, $organizationId){
        $organization = Organization::findOrFail($organizationId);
        return view('admin.organizations.delete',['organization' => $organization]);
    }
    public function formDeleteOrganization(Request $request, $organizationId){
        $rules = [
            'password' =>  ['required', new MatchOldPassword],
        ];
        $request->validate($rules);
        $organization = Organization::findOrFail($organizationId);
        $organization->delete();
        return redirect()->route('admin.organizations')->with('success','La organizacion ha sido eliminada correctamente');
    }
    // ====================================
    // Admin FAQS
    // ====================================

    public function viewListFaqs(Request $request){
      $faqs = Faq::orderBy('section','ASC')->orderBy('order','ASC')->paginate(10);
      return view('admin.faqs.list',['faqs' => $faqs]);
    }
    public function viewCreateFaq(Request $request){
        return view('admin.faqs.create');
    }
    public function formCreateFaq(Request $request){
        $rules = [
            'section' => 'required|string|max:225',
            'title' => 'required|string|max:550',
            'order' => 'required|integer|min:0',
            'content' => 'required|string',
        ];
        $request->validate($rules);

        // Handle data
        $faq = new Faq();
        $faq->title = $request->input('title');
        $faq->order = $request->input('order');
        $faq->section = $request->input('section');
        $faq->content = $request->input('content');
        $faq->save();

        return redirect()->route('admin.faqs')->with('success','La pregunta frecuente ha sido creada correctamente');
    }
    public function viewEditFaq(Request $request, $faqId){
        $faq = Faq::findOrFail($faqId);
        return view('admin.faqs.edit',['faq' => $faq]);
    }
    public function formEditFaq(Request $request, $faqId){
        $rules = [
            'section' => 'required|string|max:225',
            'title' => 'required|string|max:225',
            'order' => 'required|integer|min:0',
            'content' => 'required|string',
        ];

        $request->validate($rules);

        $faq = Faq::findOrFail($faqId);
        $faq->title = $request->input('title');
        $faq->order = $request->input('order');
        $faq->section = $request->input('section');
        $faq->content = $request->input('content');
        $faq->save();

        return redirect()->route('admin.faqs')->with('success','La pregunta frecuente ha sido editada correctamente');
    }
    public function viewDeleteFaq(Request $request, $faqId){
        $faq = Faq::findOrFail($faqId);
        return view('admin.faqs.delete',['faq' => $faq]);
    }

    public function formDeleteFaq(Request $request, $faqId){

        $faq = Faq::findOrFail($faqId);
        $faq->delete();

        return redirect()->route('admin.faqs')->with('success','La pregunta frecuente ha sido eliminada correctamente');
    }
    // ====================================
    // Admin Users
    // ====================================

    public function viewListUsers(Request $request, UserDirectory $userDirectory){
        $filters = $this->userFilters($request);

        return view('admin.users.list', [
            'users' => $userDirectory->search($filters)->paginate(25)->withQueryString(),
            'filters' => $filters,
            'pfiCategories' => PfiCategory::query()->ordered()->get(),
        ]);
    }

    public function downloadUsers(Request $request, CsvDownload $csvDownload){
        return $csvDownload->download(
            new UsersExport($this->userFilters($request)),
            Carbon::now()->format('Ymd').'-usuarios.csv',
        );
    }

    public function viewUser(Request $request, UserDirectory $userDirectory, PfiMemberships $pfiMemberships, $userId){
        $user = User::with(['avatar', 'roles', 'pfiMembership.addedBy', 'pfiCategories'])->findOrFail($userId);

        return view('admin.users.show', [
            'user' => $user,
            'counts' => $userDirectory->profileCounts($user),
            'latestActions' => $userDirectory->latestActions($user),
            'selectablePfiCategories' => $pfiMemberships->selectableCategories($user),
        ]);
    }

    public function formUpdateUser(Request $request, $userId){
        $user = User::findOrFail($userId);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'surname' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'organization' => 'nullable|string|max:550',
            'mailable' => 'nullable|string|in:true',
        ]);

        $user->name = $validated['name'];
        $user->surname = $validated['surname'];
        $user->email = $validated['email'];
        $user->organization = $validated['organization'] ?? null;
        $user->notification_preferences = ($validated['mailable'] ?? null) === 'true' ? 'database,mail' : 'database';
        $changes = array_keys($user->getDirty());
        $user->save();

        $this->logUserAction($request, $user, "ha editado los datos del usuario [{$user->fullname}]", ['changes' => $changes]);

        return redirect()->route('admin.users.show', ['userId' => $user->id])->with('success', 'Se actualizaron los datos del usuario');
    }

    public function formVerifyUser(Request $request, $userId){
        $user = User::findOrFail($userId);
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            $this->logUserAction($request, $user, "ha verificado manualmente la cuenta de [{$user->fullname}]");
        }

        return redirect()->route('admin.users.show', ['userId' => $user->id])->with('success', 'La cuenta del usuario está verificada');
    }

    public function formResendUserVerification(Request $request, $userId){
        $user = User::findOrFail($userId);
        if ($user->hasVerifiedEmail()) {
            return redirect()->route('admin.users.show', ['userId' => $user->id])->with('error', 'La cuenta del usuario ya está verificada');
        }
        $user->sendEmailVerificationNotification();
        $this->logUserAction($request, $user, "ha reenviado el email de verificación a [{$user->fullname}]");

        return redirect()->route('admin.users.show', ['userId' => $user->id])->with('success', 'Se reenvió el email de verificación a '.$user->email);
    }

    public function formSendUserPasswordReset(Request $request, $userId){
        $user = User::findOrFail($userId);
        $status = Password::broker()->sendResetLink(['email' => $user->email]);
        if ($status !== Password::RESET_LINK_SENT) {
            return redirect()->route('admin.users.show', ['userId' => $user->id])->with('error', __($status));
        }
        $this->logUserAction($request, $user, "ha enviado un link para restablecer la contraseña a [{$user->fullname}]");

        return redirect()->route('admin.users.show', ['userId' => $user->id])->with('success', 'Se envió el link para restablecer la contraseña a '.$user->email);
    }

    public function formGrantUserAdmin(Request $request, $userId){
        $user = User::findOrFail($userId);
        $user->roles()->syncWithoutDetaching([Role::where('name', 'admin')->firstOrFail()->id]);
        $this->logUserAction($request, $user, "le ha otorgado el rol de administrador al usuario [{$user->fullname}]");

        return redirect()->route('admin.users.show', ['userId' => $user->id])->with('success', 'El usuario ahora es administrador de la plataforma');
    }

    public function formRevokeUserAdmin(Request $request, $userId){
        $user = User::findOrFail($userId);
        if ($user->is($request->user())) {
            return redirect()->route('admin.users.show', ['userId' => $user->id])->with('error', 'No podés quitarte el rol de administrador a vos mismo');
        }
        $user->roles()->detach(Role::where('name', 'admin')->firstOrFail());
        $this->logUserAction($request, $user, "ha quitado el rol de administrador al usuario [{$user->fullname}]");

        return redirect()->route('admin.users.show', ['userId' => $user->id])->with('success', 'Se quitó el rol de administrador al usuario');
    }

    public function viewUserObjectives(Request $request, $userId){
        $user = User::with(['avatar', 'roles', 'pfiMembership', 'pfiCategories'])->findOrFail($userId);

        return view('admin.users.objectives', [
            'user' => $user,
            'goals' => $user->assignedGoals()->withPivot('role')->with('objective.strategicObjective.category')->orderBy('title')->get(),
            'subscriptions' => $user->subscriptions()->with('strategicObjective.category')->orderBy('title')->get(),
        ]);
    }

    /**
     * @return array{q: ?string, verificado: ?string, rol: ?string, equipo: ?string, pfi: ?string, categoria_pfi: ?string, orden: ?string}
     */
    private function userFilters(Request $request): array
    {
        return [
            'q' => $request->query('q'),
            'verificado' => $request->query('verificado'),
            'rol' => $request->query('rol'),
            'equipo' => $request->query('equipo'),
            'pfi' => $request->query('pfi'),
            'categoria_pfi' => $request->query('categoria_pfi'),
            'orden' => $request->query('orden'),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logUserAction(Request $request, User $user, string $message, array $context = []): void
    {
        Log::channel('mysql')->info("[{$request->user()->fullname}] {$message}", $context + [
            'member_id' => $user->id,
            'member_fullname' => $user->fullname,
            'member_email' => $user->email,
            'user_id' => $request->user()->id,
            'user_fullname' => $request->user()->fullname,
            'user_email' => $request->user()->email,
        ]);
    }

    // ====================================
    // Admin Objectives
    // ====================================

    public function viewListObjectives(Request $request, ObjectiveDirectory $objectiveDirectory){
        $filters = $objectiveDirectory->normalize($request->only(['q', 'eje', 'estrategico', 'visibilidad', 'metas', 'orden']));

        return view('admin.objectives.list', [
            'objectives' => $objectiveDirectory->search($filters)->paginate(10)->withQueryString(),
            'filters' => $filters,
            ...$objectiveDirectory->hierarchyOptions($filters),
        ]);
    }

    public function downloadListObjectives(Request $request, CsvDownload $csvDownload){
      return $csvDownload->download(new ObjectivesExport, Carbon::now()->format('Ymd').'-objetivos.csv');
    }

    public function viewCreateObjective(Request $request){
        $ejes = Category::with('strategicObjectives')->orderBy('order')->get();
        $organizations = Organization::all();
        return view('admin.objectives.create',['ejes' => $ejes, 'organizations' => $organizations]);
    }
    public function formCreateObjective(Request $request){

        $rules = [
            'title' => 'required|string|max:550' ,
            'content' => 'required|string|max:2000',
            'strategic_objective' => 'required|numeric|exists:strategic_objectives,id',
            'tags' => 'array' ,
            'tags.*' => 'required|string|max:100' ,
            'organizations' => 'array' ,
            'organizations.*' => 'required|numeric' ,
        ];
        $request->validate($rules);

        $objective = new Objective();
        $objective->title = $request->input('title');
        $objective->content = $request->input('content');
        $objective->tags = $request->input('tags');
        $objective->strategicObjective()->associate(StrategicObjective::findOrFail($request->input('strategic_objective')));
        $objective->author()->associate($request->user());
        $objective->hidden = true;
        $objective->save();
        $objective->organizations()->attach($request->input('organizations'));

        Log::channel('mysql')->info("[{$request->user()->fullname}] ha creado el objetivo [{$objective->title}]", [
            'objective_id' => $objective->id,
            'objective_title' => $objective->title,
            'user_id' => $request->user()->id,
            'user_fullname' => $request->user()->fullname,
            'user_email' => $request->user()->email
            ]);

        return redirect()->route('objectives.manage.index',['objectiveId' => $objective->id])->with('success','¡Nuevo objetivo creado! Ahora le toca configurar el objetivo');
    }

    public function viewLogs(Request $request)
    {
      $logs = ActionLog::where('context->type','!=','notifications')->orWhereNull('context->type')->orderBy('record_datetime','DESC')->paginate(25);
      return view('admin.logs.list',['logs' => $logs]);
    }

    public function viewUpcomingEvents(Request $request)
    {
        $events = Event::where('date', '>=', Carbon::today())->orderBy('date','DESC')->paginate(10);
        return view('admin.events.upcoming',['events' => $events]);
    }
    public function viewPastEvents(Request $request)
    {
        $events = Event::where('date', '<', Carbon::today())->orderBy('date','DESC')->paginate(10);
        return view('admin.events.past',['events' => $events]);
    }

    public function viewCreateEvent(Request $request)
    {
        $objectives = Objective::select(['id','title','hidden'])->get();
        return view('admin.events.create',['objectives'=>$objectives]);
    }

    public function formCreateEvent(Request $request)
    {
        $rules = [
            'title' => 'required|string|max:550',
            'content' => 'required|string',
            'date' => 'required|date',
            'hour' => 'required|numeric|between:0,23',
            'minute' => 'required|numeric|between:0,55',
            'address' => 'required|string|max:550',
            'urls' => 'array' ,
            'urls.*' => 'required|string' ,
            'objectives' => 'array' ,
            'objectives.*' => 'required|numeric' ,
            'notify' => 'nullable|string|in:true',
        ];

        $request->validate($rules);

        $event = new Event();
        $event->title = $request->input('title');
        $event->content = $request->input('content');
        $event->date = "{$request->input('date')} {$request->input('hour','00')}:{$request->input('minute','00')}:00";
        $event->address = $request->input('address');
        $event->urls = $request->input('urls');
        $event->author()->associate($request->user());
        $event->save();
        $event->objectives()->attach($request->input('objectives'));

        if($request->hasFile('photos')){
            foreach($request->file('photos') as $photoFile){
            $photo = Image::make($photoFile);
            $photo->resize(1366, 910, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $photoThumbnail = Image::make($photoFile);
            $photoThumbnail->resize(400, 266, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            // Get mimeType
            $mimeType = $photo->mime();
            // Get Extension
            $fileExtension = strtolower($photoFile->getClientOriginalExtension());
            $uniqueHash = substr(uniqid(),-5);
            $photoName = 'photo-'.$event->id.'-'.$uniqueHash.'.'.$fileExtension;
            $photoNameThumbnail = 'photo-'.$event->id.'-'.$uniqueHash.'-thumbnail.'.$fileExtension;
            // Make the File path
            $photoPath = '/storage/events/photos/'.$photoName;
            $photoPathThumbnail = '/storage/events/photos/'.$photoNameThumbnail;
            Storage::disk('events')->put("photos/".$photoName, (string) $photo->encode($fileExtension));
            Storage::disk('events')->put("photos/".$photoNameThumbnail, (string) $photoThumbnail->encode($fileExtension,80));
            $imageFile = new ImageFile();
            $imageFile->name = $photoName;
            $imageFile->size = Storage::disk('events')->size("photos/".$photoName);
            $imageFile->mime = $mimeType;
            $imageFile->path = $photoPath;
            $imageFile->thumbnail_name = $photoNameThumbnail;
            $imageFile->thumbnail_size = Storage::disk('events')->size("photos/".$photoNameThumbnail);
            $imageFile->thumbnail_mime = $mimeType;
            $imageFile->thumbnail_path = $photoPathThumbnail;
            $event->photos()->save($imageFile);
            }
        }

        Log::channel('mysql')->info("[{$request->user()->fullname}] ha creado la actividad [{$event->title}]", [
            'event_id' => $event->id,
            'event_title' => $event->title,
            'user_id' => $request->user()->id,
            'user_fullname' => $request->user()->fullname,
            'user_email' => $request->user()->email
            ]);

        $notifySubscribers = $request->boolean('notify');
        if($notifySubscribers){
            $usersToNotify = new EloquentCollection();
            foreach($event->objectives as $objective) {
                if(!$objective->hidden){
                    $usersToNotify = $usersToNotify->merge($objective->subscribers);
                }
            }
            if(!$usersToNotify->isEmpty()){
            Notification::send($usersToNotify, new NewEvent($event));
            }
        }

        return redirect()->route('admin.events')->with('success','¡Nueva actividad creada!');

    }

    public function viewEditEvent(Request $request, $eventId)
    {
        $event = Event::findorfail($eventId);
        $objectives = Objective::select(['id','title','hidden'])->get();
        return view('admin.events.edit',['event' => $event, 'objectives' => $objectives]);
    }

    public function formEditEvent(Request $request, $eventId)
    {
        $rules = [
            'title' => 'required|string|max:550',
            'content' => 'required|string',
            'date' => 'required|date',
            'hour' => 'required|numeric|between:0,23',
            'minute' => 'required|numeric|between:0,55',
            'address' => 'required|string|max:550',
            'urls' => 'array' ,
            'urls.*' => 'required|string',
            'objectives' => 'array' ,
            'objectives.*' => 'required|numeric' ,
            'notify' => 'nullable|string|in:true',
        ];
        $request->validate($rules);

        $event = Event::findorfail($eventId);

        $event->title = $request->input('title');
        $event->content = $request->input('content');
        $event->date = "{$request->input('date')} {$request->input('hour','00')}:{$request->input('minute','00')}:00";
        $event->address = $request->input('address');
        $event->urls = $request->input('urls');
        $event->author()->associate($request->user());
        $event->objectives()->sync($request->input('objectives'));
        $event->save();

        Log::channel('mysql')->info("[{$request->user()->fullname}] ha editado la actividad [{$event->title}]", [
            'event_id' => $event->id,
            'event_title' => $event->title,
            'user_id' => $request->user()->id,
            'user_fullname' => $request->user()->fullname,
            'user_email' => $request->user()->email
            ]);

        $notifySubscribers = $request->boolean('notify');
        if($notifySubscribers){
            $usersToNotify = new EloquentCollection();
            foreach($event->objectives as $objective) {
                if(!$objective->hidden){
                    $usersToNotify = $usersToNotify->merge($objective->subscribers);
                }
            }
            if(!$usersToNotify->isEmpty()){
                Notification::send($usersToNotify, new EditEvent($event));
            }
        }

        return redirect()->route('admin.events')->with('success','La actividad ha sido editada correctamente');
    }

    public function formAddPictureEvent(Request $request, $eventId)
    {
        $rules = [
            'photo' => 'required|file|max:102400',
        ];

        $request->validate($rules);

        $event = Event::findorfail($eventId);

        if($request->hasFile('photo')){
            $photoFile = $request->file('photo');
            $photo = Image::make($photoFile);
            $photo->resize(1366, 910, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $photoThumbnail = Image::make($photoFile);
            $photoThumbnail->resize(400, 266, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            // Get mimeType
            $mimeType = $photo->mime();
            // Get Extension
            $fileExtension = strtolower($photoFile->getClientOriginalExtension());
            $uniqueHash = substr(uniqid(),-5);
            $photoName = 'photo-'.$event->id.'-'.$uniqueHash.'.'.$fileExtension;
            $photoNameThumbnail = 'photo-'.$event->id.'-'.$uniqueHash.'-thumbnail.'.$fileExtension;
            // Make the File path
            $photoPath = '/storage/events/photos/'.$photoName;
            $photoPathThumbnail = '/storage/events/photos/'.$photoNameThumbnail;
            Storage::disk('events')->put("photos/".$photoName, (string) $photo->encode($fileExtension));
            Storage::disk('events')->put("photos/".$photoNameThumbnail, (string) $photoThumbnail->encode($fileExtension,80));
            $imageFile = new ImageFile();
            $imageFile->name = $photoName;
            $imageFile->size = Storage::disk('events')->size("photos/".$photoName);
            $imageFile->mime = $mimeType;
            $imageFile->path = $photoPath;
            $imageFile->thumbnail_name = $photoNameThumbnail;
            $imageFile->thumbnail_size = Storage::disk('events')->size("photos/".$photoNameThumbnail);
            $imageFile->thumbnail_mime = $mimeType;
            $imageFile->thumbnail_path = $photoPathThumbnail;
            $event->photos()->save($imageFile);
        }

        return redirect()->route('admin.events.edit',['eventId' => $eventId])->with('success','Se ha agregado la imagen correctamente');
    }

    public function formDeletePictureEvent(Request $request, $eventId, $pictureId)
    {
        $event = Event::findorfail($eventId);
        $picture = ImageFile::findorfail($pictureId);
        Storage::disk('events')->delete("photos/".$picture->name);
        Storage::disk('events')->delete("photos/".$picture->thumbnail_name);
        $picture->delete();
        return redirect()->route('admin.events.edit',['eventId' => $eventId])->with('success','La imagen ha sido eliminada correctamente');
    }

    public function viewDeleteEvent(Request $request, $eventId)
    {
        $event = Event::findorfail($eventId);
        return view('admin.events.delete',['event' => $event]);
    }

    public function formDeleteEvent(Request $request, $eventId)
    {
        $rules = [
            'password' =>  ['required', new MatchOldPassword],
            'notify' => 'nullable|string|in:true',
        ];

        $request->validate($rules);
        $event = Event::findorfail($eventId);
        if(!$event->photos->isEmpty()){
            foreach ($event->photos as $photo) {
                Storage::disk('events')->delete("photos/".$photo->name);
                Storage::disk('events')->delete("photos/".$photo->thumbnail_name);
                $photo->delete();
            }
        }

        $notifySubscribers = $request->boolean('notify');
        if($notifySubscribers){
            $usersToNotify = new EloquentCollection();
            foreach($event->objectives as $objective) {
                if(!$objective->hidden){
                    $usersToNotify = $usersToNotify->merge($objective->subscribers);
                }
            }
            if(!$usersToNotify->isEmpty()){
                Notification::send($usersToNotify, new DeleteEvent($event));
            }
        }

        $event->objectives()->detach();
        $event->delete();

        Log::channel('mysql')->info("[{$request->user()->fullname}] ha eliminado la actividad [{$event->title}]", [
            'event_id' => $event->id,
            'event_title' => $event->title,
            'user_id' => $request->user()->id,
            'user_fullname' => $request->user()->fullname,
            'user_email' => $request->user()->email
            ]);


        return redirect()->route('admin.events')->with('success','La actividad ha sido eliminada correctamente');
    }

    public function formEditSetting(Request $request)
    {
        $rules = [
            'name' =>  'required|string',
            'type' =>  'required|string',
            'value' => 'nullable|string',
        ];

        if ($request->input('name') === 'app_search_indexing_enabled') {
            $rules['type'] = 'required|in:boolean';
            $rules['value'] = 'nullable|in:true,false,1,0';
        }

        $request->validate($rules);

        $setting = Setting::where('name', $request->input('name'))->firstOrFail();
        if($setting->type == 'bool' || $setting->type == 'boolean') {
            // to string
            $setting->value = $request->boolean('value');
        } else {
            $setting->value = $request->input('value');
        }
        $setting->name = $request->input('name');
        $setting->type = $request->input('type');
        $setting->cached = $request->boolean('cached');
        $setting->save();
        return redirect()->back()->with('success','Configuración guardada');
    }

    public function viewEditMapSettings(Request $request)
    {
        $settings = Setting::all()->keyBy('name');

        return view('admin.settings.map.edit',['settings' => $settings]);
    }
    public function viewEditHomepageSettings(Request $request)
    {
        $settings = Setting::all()->keyBy('name');

        return view('admin.settings.homepage.edit',['settings' => $settings]);
    }
    public function viewEditSeoSettings(Request $request)
    {
        $settings = Setting::all()->keyBy('name');

        return view('admin.settings.seo.edit',['settings' => $settings]);
    }

    public function formEditMapSetting(Request $request)
    {
        $rules = [
            'map_lat' => 'nullable|numeric',
            'map_long' => 'nullable|numeric',
            'map_zoom' => 'nullable|numeric',
        ];
        $request->validate($rules);

        $settingLat = Setting::where('name', 'app_map_lat_default')->first();
        $settingLong = Setting::where('name', 'app_map_long_default')->first();
        $settingZoom = Setting::where('name', 'app_map_zoom_default')->first();

        if ($request->has(['map_lat', 'map_long', 'map_zoom'])) {
            $settingLat->value = $request->input('map_lat');
            $settingLong->value = $request->input('map_long');
            $settingZoom->value = $request->input('map_zoom');
        }
        $settingLat->save();
        $settingLong->save();
        $settingZoom->save();

        return redirect()->back()->with('success','Configuración guardada');

    }

    public function formEditFileSetting(Request $request)
    {
        $rules = [
            'name' =>  'required|string',
            'type' =>  'required|string',
            'file' => 'required|file|max:102400',
        ];

        $request->validate($rules);

        $setting = Setting::where('name', $request->input('name'))->first();
        $setting->name = $request->input('name');
        $setting->type = $request->input('type');
        $setting->cached = $request->boolean('cached');
        if($request->hasFile('file')){
            $file = $request->file('file');
            // Get Extension
            $fileExtension = strtolower($file->getClientOriginalExtension());
            $uniqueHash = substr(uniqid(),-5);
            $fileName = $setting->name.'-'.$uniqueHash.'.'.$fileExtension;
            $filePath = '/storage/settings/'.$fileName;
            $file->storeAs('/',$fileName,'settings');
            $setting->value = $filePath;
        }
        $setting->save();
        return redirect()->back()->with('success','Configuración guardada');

    }
    public function clearCacheSettings(Request $request)
    {
        $settings = Setting::all();
        foreach ($settings as $setting) {
            Cache::put($setting->name, $setting->casted_value);
        }
        return redirect()->back()->with('success','Cache reiniciada');
    }

}
