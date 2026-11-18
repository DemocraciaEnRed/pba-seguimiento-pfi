<?php

namespace App\Http\Controllers;

use App\Category;
use App\Http\Requests\InitiativeRequest;
use App\Initiative;
use App\Rules\MatchOldPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Intervention\Image\Facades\Image;

class InitiativeController extends Controller
{
    private const int BACKGROUND_MAX_WIDTH = 1600;

    private const int LOGO_MAX_SIZE = 400;

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('check_role:admin');
    }

    public function index(Request $request): View
    {
        $initiatives = Initiative::query()
            ->with('categories')
            ->when($request->filled('category'), fn ($query) => $query->whereHas(
                'categories',
                fn ($categories) => $categories->whereKey($request->integer('category'))
            ))
            ->when($request->boolean('featured'), fn ($query) => $query->featuredOnHome())
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('admin.initiatives.list', [
            'initiatives' => $initiatives,
            'categories' => Category::orderBy('order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.initiatives.create', [
            'initiative' => new Initiative(['order' => 0]),
            'categories' => Category::orderBy('order')->get(),
        ]);
    }

    public function store(InitiativeRequest $request): RedirectResponse
    {
        $initiative = DB::transaction(function () use ($request): Initiative {
            $initiative = Initiative::create([
                ...$request->safe()->only(['title', 'subtitle', 'featured_on_home', 'order']),
                'links' => array_values($request->validated('links')),
            ]);
            $initiative->categories()->sync($request->validated('categories'));

            return $initiative;
        });

        $this->syncImages($initiative, $request);
        Initiative::flushPublicCache();

        return redirect()->route('admin.initiatives')->with('success', 'La iniciativa ha sido creada correctamente');
    }

    public function edit(Initiative $initiative): View
    {
        return view('admin.initiatives.edit', [
            'initiative' => $initiative->load('categories'),
            'categories' => Category::orderBy('order')->get(),
        ]);
    }

    public function update(InitiativeRequest $request, Initiative $initiative): RedirectResponse
    {
        DB::transaction(function () use ($request, $initiative): void {
            $initiative->update([
                ...$request->safe()->only(['title', 'subtitle', 'featured_on_home', 'order']),
                'links' => array_values($request->validated('links')),
            ]);
            $initiative->categories()->sync($request->validated('categories'));
        });

        $this->syncImages($initiative, $request);
        Initiative::flushPublicCache();

        return redirect()->route('admin.initiatives')->with('success', 'La iniciativa ha sido editada correctamente');
    }

    public function delete(Initiative $initiative): View
    {
        return view('admin.initiatives.delete', ['initiative' => $initiative]);
    }

    public function destroy(Request $request, Initiative $initiative): RedirectResponse
    {
        $request->validate(['password' => ['required', new MatchOldPassword]]);

        $paths = array_filter([$initiative->background_path, $initiative->logo_path]);
        $initiative->delete();
        Storage::disk(Initiative::DISK)->delete($paths);
        Initiative::flushPublicCache();

        return redirect()->route('admin.initiatives')->with('success', 'La iniciativa ha sido eliminada correctamente');
    }

    private function syncImages(Initiative $initiative, InitiativeRequest $request): void
    {
        foreach (['background' => 'background_path', 'logo' => 'logo_path'] as $input => $column) {
            $shouldRemove = $request->validated('remove_'.$input) || $request->hasFile($input);

            if ($shouldRemove && $initiative->{$column}) {
                Storage::disk(Initiative::DISK)->delete($initiative->{$column});
                $initiative->{$column} = null;
            }

            if ($request->hasFile($input)) {
                $initiative->{$column} = $input === 'background'
                    ? $this->storeBackground($initiative, $request->file($input))
                    : $this->storeLogo($initiative, $request->file($input));
            }
        }

        $initiative->save();
    }

    private function storeBackground(Initiative $initiative, UploadedFile $file): string
    {
        $image = Image::make($file)->resize(self::BACKGROUND_MAX_WIDTH, null, function ($constraint): void {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $path = 'initiative-'.$initiative->id.'-bg-'.substr(uniqid(), -6).'.jpg';
        Storage::disk(Initiative::DISK)->put($path, (string) $image->encode('jpg', 82));

        return $path;
    }

    private function storeLogo(Initiative $initiative, UploadedFile $file): string
    {
        $image = Image::make($file)->resize(self::LOGO_MAX_SIZE, self::LOGO_MAX_SIZE, function ($constraint): void {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $extension = $image->mime() === 'image/png' ? 'png' : 'jpg';
        $path = 'initiative-'.$initiative->id.'-logo-'.substr(uniqid(), -6).'.'.$extension;
        Storage::disk(Initiative::DISK)->put($path, (string) $image->encode($extension, 90));

        return $path;
    }
}
