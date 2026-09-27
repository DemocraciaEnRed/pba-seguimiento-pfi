<?php

namespace App\Http\Controllers;
use App\Category;
use App\Faq;
use App\Services\Indicators\HomeStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Forces to be authenticated.
        // $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $categories = Category::with([
            'strategicObjectives.objectives' => function ($query) {
                $query->where('hidden', false)->withCount('goals');
            },
        ])->orderBy('order')->get();
        return view('portal.home',[
            'categories' => $categories,
        ]);
    }

    public function viewAboutGeneral()
    {
        $faqs = Faq::select(['section','id','title'])->orderBy('order','ASC')->get()->groupBy('section')->toArray();
        $questions = Faq::where('section','general')->orderBy('order','ASC')->get();
        return view('portal.about.general', ['faqs' => $faqs, 'questions' => $questions]);
    }
    public function viewAboutQuestions()
    {
        $faqs = Faq::select(['section','id','title'])->orderBy('order','ASC')->get()->groupBy('section')->toArray();
        $questions = Faq::where('section','faq')->orderBy('order','ASC')->get();
        return view('portal.about.faq', ['faqs' => $faqs, 'questions' => $questions]);
    }
    public function viewAboutLegals()
    {
        $faqs = Faq::select(['section','id','title'])->orderBy('order','ASC')->get()->groupBy('section')->toArray();
        $questions = Faq::where('section','legal')->orderBy('order','ASC')->get();
        return view('portal.about.legal', ['faqs' => $faqs, 'questions' => $questions]);
    }
    // --------------------------------

    public function fetchStats(HomeStats $homeStats): JsonResponse
    {
        return response()->json([
            'message' => 'Ok',
            'data' => Cache::remember('home.stats', 600, fn (): array => $homeStats->compute()),
        ], 200);
    }
}
