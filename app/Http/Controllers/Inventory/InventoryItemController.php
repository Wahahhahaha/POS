<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryItemController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = InventoryItem::query();

        if ($search) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
        }

        $items = $query->orderBy('name', 'asc')->paginate(15)->withQueryString();

        return Inertia::render('Inventory/Item/Index', [
            'items' => $items,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'unit' => 'required|in:Pcs,Pack,Kg,L',
            'type' => 'required|in:Items,Products,Ingredients,Merchandise',
        ]);

        InventoryItem::create($validated);

        return back()->with('success', 'Item created successfully.');
    }

    public function update(Request $request, $id)
    {
        $item = InventoryItem::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'unit' => 'required|in:Pcs,Pack,Kg,L',
            'type' => 'required|in:Items,Products,Ingredients,Merchandise',
        ]);

        $item->update($validated);

        return back()->with('success', 'Item updated successfully.');
    }

    public function destroy($id)
    {
        $item = InventoryItem::findOrFail($id);
        $item->delete();

        return back()->with('success', 'Item deleted successfully.');
    }
}