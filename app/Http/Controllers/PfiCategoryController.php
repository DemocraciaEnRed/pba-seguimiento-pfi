<?php

namespace App\Http\Controllers;

use App\Http\Requests\PfiCategoryRequest;
use App\PfiCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PfiCategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('check_role:admin');
    }

    public function index(): View
    {
        return view('admin.pfi-categories.list', [
            'categories' => PfiCategory::query()->withCount('users')->orderByDesc('is_active')->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.pfi-categories.create', ['category' => new PfiCategory]);
    }

    public function store(PfiCategoryRequest $request): RedirectResponse
    {
        $category = PfiCategory::create($request->validated());
        $this->log($request, $category, "ha creado la categoría PFI [{$category->name}]");

        return redirect()->route('admin.pfi-categories')->with('success', 'La categoría PFI ha sido creada correctamente');
    }

    public function edit(PfiCategory $pfiCategory): View
    {
        return view('admin.pfi-categories.edit', ['category' => $pfiCategory->loadCount('users')]);
    }

    public function update(PfiCategoryRequest $request, PfiCategory $pfiCategory): RedirectResponse
    {
        $previousName = $pfiCategory->name;
        $pfiCategory->fill($request->validated());
        $changes = array_keys($pfiCategory->getDirty());

        if ($changes !== []) {
            $pfiCategory->save();
            $action = match (true) {
                $pfiCategory->wasChanged('is_active') && ! $pfiCategory->is_active => 'desactivado',
                $pfiCategory->wasChanged('is_active') => 'reactivado',
                default => 'editado',
            };
            $this->log($request, $pfiCategory, "ha {$action} la categoría PFI [{$pfiCategory->name}]", [
                'changes' => $changes,
                'pfi_category_previous_name' => $previousName,
            ]);
        }

        return redirect()->route('admin.pfi-categories')->with('success', 'La categoría PFI ha sido editada correctamente');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(Request $request, PfiCategory $category, string $message, array $context = []): void
    {
        Log::channel('mysql')->info("[{$request->user()->fullname}] {$message}", $context + [
            'pfi_category_id' => $category->id,
            'pfi_category_name' => $category->name,
            'user_id' => $request->user()->id,
            'user_fullname' => $request->user()->fullname,
            'user_email' => $request->user()->email,
        ]);
    }
}
