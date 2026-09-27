<?php

namespace App\Http\Controllers;
use Notification;
use Image;
use Storage;
use Str;
use Log;
use Excel;
use Carbon\Carbon;
use App\Category;
use App\Organization;
use App\Role;
use App\File;
use App\ImageFile;
use App\User;
use App\Objective;
use App\Goal;
use App\GoalPeriod;
use App\Milestone;
use App\Report;
use App\Exports\GoalReportsExport;
use App\Notifications\NewReport;
use App\Notifications\CompletedGoal;
use App\Notifications\NewGoal;
use App\Notifications\EditGoal;
use App\Notifications\DeleteGoal;
use App\Rules\MatchOldPassword;
use App\Services\Indicators\GoalIndicatorConfigurator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GoalPanelController extends Controller
{
    public const PERIOD_ALREADY_REPORTED_MESSAGE = 'Este período ya tiene un reporte de avance. Editá el reporte existente en lugar de crear uno nuevo.';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(private GoalIndicatorConfigurator $indicatorConfigurator)
    {
        // Forces to be authenticated.
        $this->middleware('auth');
        $this->middleware('verified');
        $this->middleware('fetch_objective');
        $this->middleware('goal_belongs_objective');
        $this->middleware('fetch_goal');
        $this->middleware('can_manage_objective');
    }

    private function hasManagerPrivileges(Request $request){
      if(!$request->user()->hasRole('admin') && !$request->user()->isManagerObjective($request->objective->id)){
        abort(403, 'No autorizado');
      }
      return;
    }

    private function hasAdminPrivileges(Request $request): void
    {
      if(!$request->user()->hasRole('admin')){
        abort(403, 'No autorizado');
      }
    }

    private function findReportablePeriod(Goal $goal, int $periodId): GoalPeriod
    {
      /** @var GoalPeriod|null $period */
      $period = $goal->periods()->with('progressReport')->find($periodId);

      if(is_null($period)){
        throw ValidationException::withMessages(['goal_period_id' => 'El período seleccionado no pertenece a esta meta.']);
      }
      if(!is_null($period->progressReport)){
        throw ValidationException::withMessages(['goal_period_id' => self::PERIOD_ALREADY_REPORTED_MESSAGE]);
      }
      if(!$period->state()->isReportable()){
        throw ValidationException::withMessages(['goal_period_id' => "El período no admite reportes de avance ({$period->state()->label()})."]);
      }

      return $period;
    }

    public function viewGoal(Request $request, $objectiveId, $goalId){
      return view('objective.manage.goals.view',['objective' => $request->objective, 'goal' => $request->goal]);
    }

    public function viewEditGoal(Request $request, $objectiveId, $goalId){
      $this->hasManagerPrivileges($request);
      return view('objective.manage.goals.edit',[
        'objective' => $request->objective,
        'goal' => $request->goal,
        'indicatorForm' => $this->indicatorConfigurator->formState($request->goal),
        'indicatorOptions' => $this->indicatorConfigurator->formOptions(),
      ]);
    }

    public function formEditGoal(Request $request, $objectiveId, $goalId){
      $this->hasManagerPrivileges($request);

      $rules = [
        'title' => 'required|string|max:550',
        'status' => 'required|string|in:ongoing,delayed,inactive,reached',
        'source' => 'nullable|string|max:550',
        'notify' => 'nullable|string|in:true',
      ];

      $goal = $request->goal;
      $validated = $request->validate(array_merge($rules, $this->indicatorConfigurator->rules($request->all(), $goal)));
      $goal->title = $request->input('title');
      $goal->status = $request->input('status');
      $goal->source = $request->input('source');
      $this->indicatorConfigurator->apply($goal, $validated);
      $request->objective->touch();

      Log::channel('mysql')->info("[{$request->user()->fullname}] ha editado la meta [{$goal->title}] del objetivo [{$request->objective->title}]", [
        'objective_id' => $request->objective->id,
        'objective_title' => $request->objective->title,
        'goal_id' => $goal->id,
        'goal_title' => $goal->title,
        'user_id' => $request->user()->id,
        'user_fullname' => $request->user()->fullname,
        'user_email' => $request->user()->email
        ]);


      $notifySubscribers = $request->boolean('notify');
      if(!$request->objective->hidden && $notifySubscribers){
        Notification::send($request->objective->subscribers, new EditGoal($request->objective, $goal));
      }

      return redirect()->route('objectives.manage.goals.index', ['objectiveId' => $request->objective->id, 'goalId' => $goal->id])->with('success','La meta ha sido editada correctamente');
    }

    public function viewListGoalMilestones(Request $request, $objectiveId, $goalId){
      return view('objective.manage.goals.milestones.list',['objective' => $request->objective, 'goal' => $request->goal]);
    }

    public function viewAddGoalMilestone(Request $request, $objectiveId, $goalId){
      $this->hasManagerPrivileges($request);
      return view('objective.manage.goals.milestones.add',['objective' => $request->objective, 'goal' => $request->goal]);
    }

    public function formAddGoalMilestone(Request $request, $objectiveId, $goalId){
      $this->hasManagerPrivileges($request);

      $rules = [
        'title' => 'required|string|max:550',
      ];

      $request->validate($rules);

      $goal = $request->goal;
      $lastMilestone = Milestone::where('goal_id', $goalId)->orderBy('order', 'desc')->first();
      $milestone = new Milestone();
      if(!is_null($lastMilestone)){
        // If not null..
        $milestone->order = $lastMilestone->order + 1;
      } else {
        $milestone->order = 1;
      }
      $milestone->title = $request->input('title');
      $milestone->goal()->associate($goal);
      $milestone->save();

      return redirect()->route('objectives.manage.goals.milestones', ['objectiveId' => $request->objective->id, 'goalId' => $goal->id])->with('success','El hito ha sido creado');
    }

    public function viewEditGoalMilestone(Request $request, $objectiveId, $goalId, $milestoneId){
      $this->hasManagerPrivileges($request);
      $milestone = Milestone::findorfail($milestoneId);

      return view('objective.manage.goals.milestones.edit',['objective' => $request->objective, 'goal' => $request->goal, 'milestone' => $milestone]);
    }

    public function formEditGoalMilestone(Request $request, $objectiveId, $goalId, $milestoneId){
      $this->hasManagerPrivileges($request);

      $rules = [
        'title' => 'required|string|max:550',
        'order' => 'required|numeric|min:1'
      ];

      $request->validate($rules);

      $milestone = Milestone::findorfail($milestoneId);
      $milestone->title = $request->input('title');
      $milestone->order = $request->input('order');
      $milestone->save();

      return redirect()->route('objectives.manage.goals.milestones', ['objectiveId' => $request->objective->id, 'goalId' => $request->goal->id])->with('success','El hito ha sido actualizado');
    }

    public function viewDeleteGoalMilestone(Request $request, $objectiveId, $goalId, $milestoneId){
      $this->hasManagerPrivileges($request);
      $milestone = Milestone::findorfail($milestoneId);

      if(!is_null($milestone->completed)){
        return redirect()->route('objectives.manage.goals.milestones', ['objectiveId' => $request->objective->id, 'goalId' => $request->goal->id])->with('warning','No puede eliminar un objetivo sin antes eliminar el reporte que informa que se ha completado el hito');
      }
      return view('objective.manage.goals.milestones.delete',['objective' => $request->objective, 'goal' => $request->goal, 'milestone' => $milestone]);
    }

    public function formDeleteGoalMilestone(Request $request, $objectiveId, $goalId, $milestoneId){
      $this->hasManagerPrivileges($request);

      $rules = [
        'password' =>  ['required', new MatchOldPassword],
      ];

      $request->validate($rules);
      $milestone = Milestone::findorfail($milestoneId);

      Log::channel('mysql')->info("[{$request->user()->fullname}] ha eliminado el hito [{$milestone->title}] de la meta [{$request->goal->title}] del objetivo [{$request->objective->title}]", [
        'objective_id' => $request->objective->id,
        'objective_title' => $request->objective->title,
        'goal_id' => $request->goal->id,
        'goal_title' => $request->goal->title,
        'milestone' => $milestone->id,
        'milestone_title' => $milestone->title,
        'user_id' => $request->user()->id,
        'user_fullname' => $request->user()->fullname,
        'user_email' => $request->user()->email
        ]);

      $milestone->delete();

      return redirect()->route('objectives.manage.goals.milestones', ['objectiveId' => $request->objective->id, 'goalId' => $request->goal->id])->with('success','El hito ha sido eliminado');
    }

    public function viewListGoalReports(Request $request, $objectiveId, $goalId){
      $goal = $request->goal;
      $reports = Report::where('goal_id',$goalId)->orderBy('date','DESC')->paginate(10);
      return view('objective.manage.goals.reports.list',['objective' => $request->objective, 'goal' => $request->goal, 'reports' => $reports]);
    }

    public function downloadListGoalReports(Request $request, $objectiveId, $goalId){
      $this->hasManagerPrivileges($request);
      return Excel::download(new GoalReportsExport($goalId), Carbon::now()->format('Ymd').'-reportes-meta-'.$goalId.'-objetivo-'.$objectiveId.'.xlsx');
    }

    public function viewNewGoalReport(Request $request, $objectiveId, $goalId){
      $goal = $request->goal;
      $periods = [];
      if($goal->isPeriodic()){
        $periods = $goal->periods()->with('progressReport')->get()->map(fn (GoalPeriod $period): array => [
          'id' => $period->id,
          'label' => $period->label(),
          'range' => $period->rangeLabel(),
          'target' => $period->target_value,
          'state_label' => $period->state()->label(),
          'reportable' => $period->state()->isReportable(),
          'edit_url' => $period->progressReport
            ? route('objectives.manage.goals.reports.edit', ['objectiveId' => $request->objective->id, 'goalId' => $goal->id, 'reportId' => $period->progressReport->id])
            : null,
        ])->values()->all();
      }
      return view('objective.manage.goals.reports.add',['objective' => $request->objective, 'goal' => $request->goal, 'periods' => $periods]);
    }

    public function formNewGoalReport(Request $request, $objectiveId, $goalId){
      $goal = $request->goal;
      $allowedTypes = $goal->acceptsProgressReports() ? 'post,progress,milestone' : 'post,milestone';

      $rules = [
        'title' => 'required|string|max:550',
        'type' => 'required|string|in:'.$allowedTypes,
        'content' => 'required|string',
        'date' => 'required|date',
        'status' => 'nullable|string|max:550',
        'lat' => 'nullable|string',
        'long' => 'nullable|string',
        'progress' => 'numeric|gt:0',
        'goal_period_id' => [Rule::requiredIf($goal->isPeriodic() && $request->input('type') === 'progress'), 'nullable', 'integer'],
        'measured_value' => [Rule::requiredIf($goal->isPeriodic() && $request->input('type') === 'progress'), 'nullable', 'numeric', 'min:0'],
        'milestone_date' => 'nullable|date',
        'milestone' => 'integer',
        'tags' => 'array' ,
        'tags.*' => 'required|string|max:100',
        'photos' => 'array',
        'photos.*' => 'required|file|max:102400',
        'files' => 'array',
        'files.*' => 'required|file|max:102400',
        'notify' => 'nullable|string|in:true',
      ];

      $request->validate($rules);

      $period = null;
      if($goal->isPeriodic() && $request->input('type') === 'progress'){
        $period = $this->findReportablePeriod($goal, (int) $request->input('goal_period_id'));
      }

      $goalDirty = false;
      $milestoneDirty = false;
      $milestone = null;

      $report = new Report();
      $report->title = $request->input('title');
      $report->type = $request->input('type');
      $report->content = $request->input('content');
      $report->date = $request->input('date');
      $report->tags = $request->input('tags');
      if(!empty($request->input('status'))){
        $report->previous_status = $goal->status;
        $report->status = $request->input('status');
        $goal->status = $request->input('status');
        $goalDirty = true;
      }
      switch($request->input('type')){
        case 'post':
          break;
        case 'progress':
          if($goal->isPeriodic()){
            $report->period()->associate($period);
            $report->measured_value = $request->input('measured_value');
            break;
          }
          $report->previous_progress = $goal->indicator_progress;
          $report->progress = $request->input('progress');
          $goal->indicator_progress += $request->input('progress');
          $goalDirty = true;
          break;
        case 'milestone':
          $milestone = Milestone::findorfail($request->input('milestone'));

          if(!empty($request->input('milestone_date'))){
            $milestone->completed = $request->input('milestone_date');
          } else {
            $milestone->completed = $request->input('date');
          }
          $milestoneDirty = true;
          $report->milestone()->associate($milestone);
          break;
      }
      try {
        DB::transaction(function () use ($goal, $goalDirty, $milestoneDirty, $milestone, $report, $request): void {
          if($goalDirty){
            $goal->save();
          }
          if($milestoneDirty){
            $milestone->save();
          }
          $report->author()->associate($request->user());
          $report->goal()->associate($goal);
          $report->save();
        });
      } catch (UniqueConstraintViolationException) {
        throw ValidationException::withMessages(['goal_period_id' => self::PERIOD_ALREADY_REPORTED_MESSAGE]);
      }

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
          // dd( strtolower($photoFile->getClientOriginalExtension()) );
          // Get Extension
          // $fileExtension = explode('/',$mimeType)[1];
          $fileExtension = strtolower($photoFile->getClientOriginalExtension());
          $uniqueHash = substr(uniqid(),-5);
          $photoName = 'photo-'.$report->id.'-'.$uniqueHash.'.'.$fileExtension;
          $photoNameThumbnail = 'photo-'.$report->id.'-'.$uniqueHash.'-thumbnail.'.$fileExtension;
          // Make the File path
          $photoPath = '/storage/reports/photos/'.$photoName;
          $photoPathThumbnail = '/storage/reports/photos/'.$photoNameThumbnail;
          Storage::disk('reports')->put("photos/".$photoName, (string) $photo->encode($fileExtension));
          Storage::disk('reports')->put("photos/".$photoNameThumbnail, (string) $photoThumbnail->encode($fileExtension,80));
          $imageFile = new ImageFile();
          $imageFile->name = $photoName;
          $imageFile->size = Storage::disk('reports')->size("photos/".$photoName);
          $imageFile->mime = $mimeType;
          $imageFile->path = $photoPath;
          $imageFile->thumbnail_name = $photoNameThumbnail;
          $imageFile->thumbnail_size = Storage::disk('reports')->size("photos/".$photoNameThumbnail);
          $imageFile->thumbnail_mime = $mimeType;
          $imageFile->thumbnail_path = $photoPathThumbnail;
          $report->photos()->save($imageFile);
        }
      }
      if($request->hasFile('files')){
        foreach($request->file('files') as $file){
          $fileName = 'report-'.$report->id.'-'.$file->getClientOriginalName();
          $filePath = $file->storeAs('files',$fileName, 'reports');
          $saveFile = new File();
          $saveFile->name = $file->getClientOriginalName();
          $saveFile->size = $file->getSize();
          $saveFile->mime = $file->getMimeType();
          $saveFile->path = '/storage/reports/'.$filePath;
          $report->files()->save($saveFile);
        }
      }

      Log::channel('mysql')->info("[{$request->user()->fullname}] ha creado un reporte [{$report->title}] de la meta [{$request->goal->title}] del objetivo [{$request->objective->title}]", [
        'objective_id' => $request->objective->id,
        'objective_title' => $request->objective->title,
        'goal_id' => $request->goal->id,
        'goal_title' => $request->goal->title,
        'report_id' => $report->id,
        'report_title' => $report->title,
        'user_id' => $request->user()->id,
        'user_fullname' => $request->user()->fullname,
        'user_email' => $request->user()->email
        ]);


      // Notify
      $notifySubscribers = $request->boolean('notify');
      if(!$request->objective->hidden && $notifySubscribers){
        // Goal at 100%?
        if($request->input('type') == 'progress' && $goal->isSimple() && ($goal->indicator_progress >= $goal->indicator_goal)){
          Notification::send($request->objective->subscribers, new CompletedGoal($request->objective, $goal, $report));
        } else {
        // Send normal notification
          Notification::send($request->objective->subscribers, new NewReport($request->objective, $goal, $report));
        }
      }

      return redirect()->route('objectives.manage.goals.reports.index', ['objectiveId' => $request->objective->id, 'goalId' => $goal->id,'reportId' => $report->id])->with('success','El reporte fue creado con exito');
    }

    public function viewGoalConfiguration(Request $request){
      return view('objective.manage.goals.configuration',['objective' => $request->objective, 'goal' => $request->goal]);
    }

    public function formSkipGoalPeriod(Request $request, $objectiveId, $goalId, $periodId){
      $this->hasAdminPrivileges($request);
      $request->validate(['skip_reason' => 'required|string|max:1000']);

      $period = $request->goal->periods()->with('progressReport')->findOrFail($periodId);
      $redirect = redirect()->route('objectives.manage.goals.index', ['objectiveId' => $request->objective->id, 'goalId' => $request->goal->id]);

      if(!is_null($period->progressReport)){
        return $redirect->with('error', 'No se puede omitir un período que ya tiene un reporte de avance.');
      }
      if($period->isSkipped()){
        return $redirect->with('warning', 'El período ya estaba omitido.');
      }

      $period->skipped_at = now();
      $period->skippedBy()->associate($request->user());
      $period->skip_reason = $request->input('skip_reason');
      $period->save();

      $this->logPeriodAction($request, $period, 'ha omitido');

      return $redirect->with('success', "Se omitió el {$period->label()}");
    }

    public function formUnskipGoalPeriod(Request $request, $objectiveId, $goalId, $periodId){
      $this->hasAdminPrivileges($request);

      $period = $request->goal->periods()->findOrFail($periodId);
      $period->skipped_at = null;
      $period->skippedBy()->dissociate();
      $period->skip_reason = null;
      $period->save();

      $this->logPeriodAction($request, $period, 'ha revertido la omisión de');

      return redirect()->route('objectives.manage.goals.index', ['objectiveId' => $request->objective->id, 'goalId' => $request->goal->id])->with('success', "Se revirtió la omisión del {$period->label()}");
    }

    private function logPeriodAction(Request $request, GoalPeriod $period, string $action): void
    {
      Log::channel('mysql')->info("[{$request->user()->fullname}] {$action} el [{$period->label()}] de la meta [{$request->goal->title}] del objetivo [{$request->objective->title}]", [
        'objective_id' => $request->objective->id,
        'objective_title' => $request->objective->title,
        'goal_id' => $request->goal->id,
        'goal_title' => $request->goal->title,
        'goal_period_id' => $period->id,
        'skip_reason' => $period->skip_reason,
        'user_id' => $request->user()->id,
        'user_fullname' => $request->user()->fullname,
        'user_email' => $request->user()->email
        ]);
    }

    public function formDeleteGoal(Request $request){
      $this->hasManagerPrivileges($request);

      $rules = [
        'password' =>  ['required', new MatchOldPassword],
        'notify' => 'nullable|string|in:true',
      ];

      $request->validate($rules);

      Log::channel('mysql')->info("[{$request->user()->fullname}] ha eliminado la meta [{$request->goal->title}] del objetivo [{$request->objective->title}]", [
        'objective_id' => $request->objective->id,
        'objective_title' => $request->objective->title,
        'goal_title' => $request->goal->id,
        'goal_title' => $request->goal->title,
        'user_id' => $request->user()->id,
        'user_fullname' => $request->user()->fullname,
        'user_email' => $request->user()->email
        ]);

      $reports = $request->goal->reports;
      foreach ($reports as $report) {
        $report->delete();
      }
      $request->goal->delete();

      //Notify
      $notifySubscribers = $request->boolean('notify');
      if(!$request->objective->hidden && $notifySubscribers){
        Notification::send($request->objective->subscribers, new DeleteGoal($request->objective, $request->goal));
      }


      return redirect()->route('objectives.manage.index', ['objectiveId' => $request->objective->id])->with('success','Meta eliminada correctamente');

    }

}
