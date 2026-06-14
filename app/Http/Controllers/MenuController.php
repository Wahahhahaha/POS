<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $menus = QueryBuilder::for(Menu::class)
            ->with(['category', 'availableBranches'])
            ->allowedFilters([
                'name', 
                'categoryid',
                AllowedFilter::callback('branchid', function ($query, $value) {
                    $query->whereHas('availableBranches', function ($q) use ($value) {
                        $q->where('branches.branchid', $value);
                    });
                }),
            ])
            ->defaultSort('menuid')
            ->paginate(20)
            ->withQueryString()
            ->through(function ($menu) {
                return array_merge($menu->toArray(), [
                    'image_url' => $menu->getFirstMediaUrl('menu_images') ?: ($menu->picture ?: null),
                    'category' => $menu->category,
                    'available_branches' => $menu->availableBranches->pluck('branchid')->toArray(),
                    'impact' => $menu->getDeletionImpact(),
                ]);
            });

        $categories = Category::all();
        $branches = Branch::all(['branchid', 'branchname']);

        return Inertia::render('Menu/Index', [
            'menus' => $menus,
            'categories' => $categories,
            'branches' => $branches,
            'filters' => [
                'search' => $request->input('filter.name'),
                'category' => $request->input('filter.categoryid'),
                'branch' => $request->input('filter.branchid'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'categoryid' => 'required|exists:categories,categoryid',
            'description' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:2048',
            'available_branches' => 'nullable|array',
            'available_branches.*' => 'exists:branches,branchid',
        ]);

        $menu = Menu::create(array_merge(
            $request->only(['name', 'price', 'categoryid']),
            [
                'picture' => '',
                'description' => $request->description ?? '',
            ]
        ));

        // Sync branches
        if ($request->has('available_branches')) {
            $menu->availableBranches()->sync($request->available_branches);
        }

        if ($request->hasFile('image')) {
            $menu->addMediaFromRequest('image')
                ->toMediaCollection('menu_images', 'menu_uploads');
            $menu->update(['picture' => $menu->getFirstMediaUrl('menu_images')]);
        }

        return Redirect::back()->with('success', 'Menu item created successfully.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'categoryid' => 'required|exists:categories,categoryid',
            'description' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:2048',
            'available_branches' => 'nullable|array',
            'available_branches.*' => 'exists:branches,branchid',
        ]);

        $menu = Menu::findOrFail($id);
        $menu->update($request->only(['name', 'price', 'categoryid', 'description']));

        // Sync branches
        if ($request->has('available_branches')) {
            $menu->availableBranches()->sync($request->available_branches);
        }

        if ($request->hasFile('image')) {
            $menu->clearMediaCollection('menu_images');
            $menu->addMediaFromRequest('image')
                ->toMediaCollection('menu_images', 'menu_uploads');
            $menu->update(['picture' => $menu->getFirstMediaUrl('menu_images')]);
        }

        return Redirect::back()->with('success', 'Menu item updated successfully.');
    }

    public function destroy($id)
    {
        $menu = Menu::findOrFail($id);
        $menu->delete();

        return Redirect::back()->with('success', 'Menu item deleted successfully.');
    }
}