<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryLog;
use App\Models\Branch;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryStockController extends Controller
{
    public function stockIndex(Request $request)
    {
        $search = $request->input('search');
        $branchId = $request->input('branch');
        $type = $request->input('type'); // Added type filter
        $user = auth()->user();

        if ($user->roleid != 1) {
            $branchId = $user->branchid;
        }

        // Aggregate stock from logs: SUM(IN) - SUM(OUT)
        $stocks = InventoryLog::query()
            ->select('itemid', 'branchid', \DB::raw("SUM(CASE WHEN type = 'IN' THEN qty ELSE -qty END) as total_qty"))
            ->with(['item', 'branch'])
            ->groupBy('itemid', 'branchid')
            ->when($search, function($query) use ($search) {
                $query->whereHas('item', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            })
            ->when($branchId, function($query) use ($branchId) {
                $query->where('branchid', $branchId);
            })
            ->when($type, function($query) use ($type) { // Filter by item type
                $query->whereHas('item', function($q) use ($type) {
                    $q->where('type', $type);
                });
            })
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Inventory/Stock/Index', [
            'stocks' => $stocks,
            'branches' => $user->roleid == 1 ? Branch::all(['branchid', 'branchname']) : [],
            'filters' => $request->only(['search', 'branch', 'type']),
        ]);
    }

    public function stockIn(Request $request)
    {
        $search = $request->input('search');
        $branchId = $request->input('branch');
        $type = $request->input('type'); // Added type filter
        $user = auth()->user();

        if ($user->roleid != 1) {
            $branchId = $user->branchid;
        }

        $query = InventoryLog::query()
            ->with(['item', 'branch'])
            ->where('type', 'IN')
            ->when($branchId, function($q) use ($branchId) {
                $q->where('branchid', $branchId);
            });

        if ($search) {
            $query->whereHas('item', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if ($type) {
            $query->whereHas('item', function($q) use ($type) {
                $q->where('type', $type);
            });
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        return Inertia::render('Inventory/StockIn/Index', [
            'logs' => $logs,
            'items' => InventoryItem::all(['itemid', 'name', 'unit', 'type']), // Pass type for frontend filtering
            'branches' => Branch::all(['branchid', 'branchname']),
            'availableBranches' => $user->roleid == 1 ? Branch::all(['branchid', 'branchname']) : [],
            'filters' => $request->only(['search', 'branch', 'type']),
        ]);
    }

    public function storeStockIn(Request $request)
    {
        $validated = $request->validate([
            'itemid' => 'required|exists:inventory_item,itemid',
            'branchid' => 'required|exists:branches,branchid',
            'qty' => 'required|numeric|min:0.01',
            'unit' => 'required|string',
            'price' => 'required|numeric|min:0',
        ]);

        $item = InventoryItem::findOrFail($request->itemid);
        $baseUnit = strtolower(trim($item->unit));
        $inputUnit = strtolower(trim($request->unit));

        if (($baseUnit === 'kg' && ($inputUnit === 'gr' || $inputUnit === 'g' || $inputUnit === 'gram')) || 
            ($baseUnit === 'l' && ($inputUnit === 'ml' || $inputUnit === 'milliliter'))) {
            $validated['qty'] = floatval($request->qty) / 1000;
            $validated['unit'] = $item->unit;
        }

        $validated['type'] = 'IN';
        $validated['created_at'] = now();
        InventoryLog::create($validated);

        return back()->with('success', 'Stock recorded successfully.');
    }

    public function stockOut(Request $request)
    {
        $search = $request->input('search');
        $branchId = $request->input('branch');
        $type = $request->input('type'); // Added type filter
        $user = auth()->user();

        if ($user->roleid != 1) {
            $branchId = $user->branchid;
        }

        $query = InventoryLog::query()
            ->with(['item', 'branch'])
            ->where('type', 'OUT')
            ->when($branchId, function($q) use ($branchId) {
                $q->where('branchid', $branchId);
            });

        if ($search) {
            $query->whereHas('item', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if ($type) {
            $query->whereHas('item', function($q) use ($type) {
                $q->where('type', $type);
            });
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $itemStocks = InventoryLog::query()
            ->select('itemid', 'branchid', \DB::raw("SUM(CASE WHEN type = 'IN' THEN qty ELSE -qty END) as total_qty"))
            ->groupBy('itemid', 'branchid')
            ->get();

        return Inertia::render('Inventory/StockOut/Index', [
            'logs' => $logs,
            'items' => InventoryItem::all(['itemid', 'name', 'unit', 'type']), // Pass type for frontend filtering
            'itemStocks' => $itemStocks,
            'branches' => Branch::all(['branchid', 'branchname']),
            'availableBranches' => $user->roleid == 1 ? Branch::all(['branchid', 'branchname']) : [],
            'filters' => $request->only(['search', 'branch', 'type']),
        ]);
    }

    public function storeStockOut(Request $request)
    {
        $validated = $request->validate([
            'itemid' => 'required|exists:inventory_item,itemid',
            'branchid' => 'required|exists:branches,branchid',
            'qty' => 'required|numeric|min:0.01',
            'unit' => 'required|string',
        ]);

        $item = InventoryItem::findOrFail($request->itemid);
        $baseUnit = strtolower(trim($item->unit));
        $inputUnit = strtolower(trim($request->unit));

        if (($baseUnit === 'kg' && ($inputUnit === 'gr' || $inputUnit === 'g' || $inputUnit === 'gram')) || 
            ($baseUnit === 'l' && ($inputUnit === 'ml' || $inputUnit === 'milliliter'))) {
            $validated['qty'] = floatval($request->qty) / 1000;
            $validated['unit'] = $item->unit;
        }

        $validated['type'] = 'OUT';
        $validated['price'] = 0;
        $validated['created_at'] = now();
        InventoryLog::create($validated);

        return back()->with('success', 'Stock out recorded successfully.');
    }
}