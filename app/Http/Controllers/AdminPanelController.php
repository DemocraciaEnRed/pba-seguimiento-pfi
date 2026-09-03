<?php

namespace App\Http\Controllers;

use Cache;
use Storage;
use Image;
use Log;
use Notification;
use App\Category;
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
use App\Faq;
use App\Setting;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ObjectivesExport;
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

    public function index(Request $request){
        $countUsers = User::count();
        $countUsersNotVerified = User::where('email_verified_at')->count();
        $countObjectives = Objective::count();
        $countGoals = Goal::count();
        $countReports = Report::count();
        $logs = ActionLog::where('context->type','!=','notifications')->orWhereNull('context->type')->orderBy('record_datetime','DESC')->take(15)->get();
        return view('admin.index', [
            'users_registered_count' => $countUsers,
            'users_unverified_count' => $countUsersNotVerified,
            'objectives_count' => $countObjectives,
            'goals_count' => $countGoals,
            'reports_count' => $countReports,
            'logs' => $logs
        ]);
    }


    // ====================================
    // Admin - Categories
    // ====================================

    public function viewListCategories(Request $request){
      $categories = Category::orderBy('order')->get();
      return view('admin.categories.list',['categories' => $categories]);
    }
    public function viewCreateCategory(Request $request){
        return view('admin.categories.create');
    }
    public function formCreateCategory(Request $request){
        $rules = [
            'title' => 'required|string|max:255' ,
            'icon' => 'required|string|max:100',
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

        return redirect()->route('admin.categories')->with('success','La categoria ha sido creada correctamente');
    }
    public function viewEditCategory(Request $request, $categoryId){
        $category = Category::with('strategicObjectives')->findOrFail($categoryId);
        return view('admin.categories.edit',['category' => $category]);
    }
    public function formEditCategory(Request $request, $categoryId){
        $rules = [
            'title' => 'required|string|max:255' ,
            'icon' => 'required|string|max:100',
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
                                'strategic_objectives' => "No se puede eliminar el objetivo estratégico '{$strategicObjective->title}' porque tiene objetivos específicos asociados.",
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

        return redirect()->route('admin.categories')->with('success','La categoria ha sido eliminada correctamente y los objetivos estratégicos han sido migrados a otra categoria');
    }

    // ====================================
    // Admin - Strategic Objectives
    // ====================================

    public function viewListStrategicObjectives(Request $request){
      $strategicObjectives = StrategicObjective::with('category')->get();
      return view('admin.strategic-objectives.list',['strategicObjectives' => $strategicObjectives]);
    }
    public function viewDeleteStrategicObjective(Request $request, $strategicObjectiveId){
        $strategicObjective = StrategicObjective::findorfail($strategicObjectiveId);
        $strategicObjectives = StrategicObjective::where('id','!=',$strategicObjectiveId)->get();
        if(count($strategicObjectives) == 0){
            return redirect()->route('admin.strategic-objectives')->with('warning','No puede eliminar el objetivo estratégico porque se requiere migrar los objetivos específicos vinculados a otro objetivo estratégico. Cree un nuevo objetivo estratégico para poder migrarlos');
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

        return redirect()->route('admin.strategic-objectives')->with('success','El objetivo estratégico ha sido eliminado correctamente y los objetivos específicos han sido migrados a otro objetivo estratégico');
    }

    // ====================================
    // Admin Organizations
    // ====================================

    public function viewListOrganizations(Request $request){
      $organizations = Organization::paginate(5);
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
    // Admin Administrators
    // ====================================

    public function viewListAdministrators(Request $request){
      $administrators = User::whereHas('roles', function ($q) {
            $q->where('name','admin');
          })->get();
      return view('admin.administrators.list',['administrators' => $administrators]);
    }
    public function viewAddAdministrator(Request $request){
        return view('admin.administrators.add');
    }
    public function formAddAdministrator(Request $request){
        $user = User::findOrFail($request->input('userId'));
        $user->roles()->attach(Role::where('name', 'admin')->first());

        Log::channel('mysql')->info("[{$request->user()->fullname}] le ha ortorgado el rol de administrador al usuario  [{$user->fullname}]", [
            'admin_id' => $user->id,
            'admin_fullname' => $user->fullname,
            'admin_email' => $user->email,
            'user_id' => $request->user()->id,
            'user_fullname' => $request->user()->fullname,
            'user_email' => $request->user()->email
            ]);

        return redirect()->route('admin.administrators')->with('success','¡Nuevo administrador creado!');
    }
    public function formDeleteAdministrator(Request $request, $id){
        $user = User::findOrFail($id);
        $user->roles()->detach(Role::where('name', 'admin')->first());

        Log::channel('mysql')->info("[{$request->user()->fullname}] ha quitado el rol de administrador al usuario  [{$user->fullname}]", [
            'admin_id' => $user->id,
            'admin_fullname' => $user->fullname,
            'admin_email' => $user->email,
            'user_id' => $request->user()->id,
            'user_fullname' => $request->user()->fullname,
            'user_email' => $request->user()->email
            ]);

        return redirect()->route('admin.administrators')->with('success','Administrador eliminado');
    }
    // ====================================
    // Admin Objectives
    // ====================================

    public function viewListObjectives(Request $request){
            $objectives = Objective::with('strategicObjective.category')->paginate(10);
      return view('admin.objectives.list',['objectives' => $objectives]);
    }

    public function downloadListObjectives(Request $request){
      return Excel::download(new ObjectivesExport, Carbon::now()->format('Ymd').'-objetivos.xlsx');
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

        Log::channel('mysql')->info("[{$request->user()->fullname}] ha creado el evento [{$event->title}]", [
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

        return redirect()->route('admin.events')->with('success','¡Nuevo evento creado!');

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

        Log::channel('mysql')->info("[{$request->user()->fullname}] ha editado el evento [{$event->title}]", [
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

        return redirect()->route('admin.events')->with('success','El evento ha sido editado correctamente');
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

        Log::channel('mysql')->info("[{$request->user()->fullname}] ha eliminado el evento [{$event->title}]", [
            'event_id' => $event->id,
            'event_title' => $event->title,
            'user_id' => $request->user()->id,
            'user_fullname' => $request->user()->fullname,
            'user_email' => $request->user()->email
            ]);


        return redirect()->route('admin.events')->with('success','El evento ha sido eliminado correctamente');
    }

    public function viewEditSettings(Request $request)
    {
        $settings = Setting::all()->keyBy('name');
        return view('admin.settings.edit',['settings' => $settings]);
    }
    public function formEditSetting(Request $request)
    {
        $rules = [
            'name' =>  'required|string',
            'type' =>  'required|string',
            'value' => 'nullable|string',
        ];

        $request->validate($rules);

        $setting = Setting::where('name', $request->input('name'))->first();
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

        // return redirect()->route('admin.settings')->with('success','Configuración guardada');
        // Redirect::back()->with('message','Operation Successful !');
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
        return redirect()->route('admin.settings')->with('success','Configuración guardada');

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
