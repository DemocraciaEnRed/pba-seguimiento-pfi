<?php

namespace App\Http\Controllers;
use Image;
use Storage;
use Log;
use App\Event;
use App\Services\SeoMetadata;
use Illuminate\Contracts\View\View;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EventController extends Controller
{

    public function __construct()
    {
        
    }

    public function showUpcomingEvents(Request $request){
      $events = Event::where('date', '>=', Carbon::today())->orderBy('date','ASC')->paginate(6);
      
      return view('portal.events.upcoming',[
            'events' => $events,
        ]);
    }
    public function showPastEvents(Request $request){
      $events = Event::where('date', '<', Carbon::today())->orderBy('date','ASC')->paginate(6);

      return view('portal.events.past',[
            'events' => $events,
        ]);
    }

    public function index(Request $request, string $eventId, SeoMetadata $seoMetadata): View {
        $event = Event::with('photos')->findOrFail($eventId);
        return view('portal.events.view',[
            'event' => $event,
            'metadata' => $seoMetadata->event($event),
        ]);
    }

}