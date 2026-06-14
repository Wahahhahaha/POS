<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Branch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class AddonController extends Controller
{
    public function index(Request $request)
    {
        $addons = QueryBuilder::for(Addon::class)
            ->with(['category', 'menu', 'availableBranches'])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%")
                          ->orWhere('addonid', 'like', "%{$value}%");
                    });
                }),
                'categoryid',
                'menuid',
                AllowedFilter::callback('branchid', function ($query, $value) {
                    $query->whereHas('availableBranches', function ($q) use ($value) {
                        $q->where('branches.branchid', $value);
                    });
                }),
            ])
            ->defaultSort('-addonid')
            ->paginate($request->per_page ?? 10)
            ->withQueryString()
            ->through(fn ($a) => array_merge($a->toArray(), [
                'available_branches' => $a->availableBranches->pluck('branchid')->toArray(),
                'impact' => $a->getDeletionImpact(),
            ]));

        $categories = Category::all();
        $menus = Menu::all();
        $branches = Branch::all(['branchid', 'branchname']);

        return Inertia::render('Master/Addons', [
            'addons' => $addons,
            'categories' => $categories,
            'menus' => $menus,
            'branches' => $branches,
            'filters' => $request->only(['filter']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'categoryid' => 'nullable|exists:categories,categoryid',
            'menuid' => 'nullable|exists:menus,menuid',
            'available_branches' => 'nullable|array',
            'available_branches.*' => 'exists:branches,branchid',
        ]);

        $addon = Addon::create($request->only(['name', 'price', 'categoryid', 'menuid']));

        if ($request->has('available_branches')) {
            $addon->availableBranches()->sync($request->available_branches);
        }

        return back()->with('success', 'Add-on created successfully.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'categoryid' => 'nullable|exists:categories,categoryid',
            'menuid' => 'nullable|exists:menus,menuid',
            'available_branches' => 'nullable|array',
            'available_branches.*' => 'exists:branches,branchid',
        ]);

        $addon = Addon::findOrFail($id);
        $addon->update($request->only(['name', 'price', 'categoryid', 'menuid']));

        if ($request->has('available_branches')) {
            $addon->availableBranches()->sync($request->available_branches);
        }

        return back()->with('success', 'Add-on updated successfully.');
    }

    public function destroy($id)
    {
        $addon = Addon::findOrFail($id);
        $addon->delete();

        return back()->with('success', 'Add-on deleted successfully.');
    }
}