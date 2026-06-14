<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryOpname;
use App\Models\InventoryLog;
use App\Models\InventoryItem;
use App\Models\Branch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class InventoryOpnameController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $branchId = $request->input('branch');
        $type = $request->input('type');
        $user = auth()->user();

        if ($user->roleid != 1) {
            $branchId = $user->branchid;
        }

        $query = InventoryOpname::query()
            ->with(['log.item', 'log.branch'])
            ->when($branchId, function($q) use ($branchId) {
                $q->whereHas('log', function($logQuery) use ($branchId) {
                    $logQuery->where('branchid', $branchId);
                });
            })
            ->when($type, function($q) use ($type) {
                $q->whereHas('log.item', function($itemQuery) use ($type) {
                    $itemQuery->where('type', $type);
                });
            });

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                  ->orWhereHas('log.item', function($itemQuery) use ($search) {
                      $itemQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $opnames = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        return Inertia::render('Inventory/Opname/Index', [
            'opnames' => $opnames,
            'items' => InventoryItem::all(['itemid', 'name', 'unit', 'type']),
            'branches' => Branch::all(['branchid', 'branchname']),
            'availableBranches' => $user->roleid == 1 ? Branch::all(['branchid', 'branchname']) : [],
            'itemStocks' => InventoryLog::query()
                ->select('itemid', 'branchid', \DB::raw("SUM(CASE WHEN type = 'IN' THEN qty ELSE -qty END) as total_qty"))
                ->groupBy('itemid', 'branchid')
                ->get(),
            'filters' => $request->only(['search', 'branch', 'type']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'itemid' => 'required|exists:inventory_item,itemid',
            'branchid' => 'required|exists:branches,branchid',
            'qty' => 'required|numeric|min:0.01',
            'unit' => 'required|string',
            'reason' => 'required|string|max:1000',
            'evidence' => 'required|image|max:2048',
        ]);

        $item = InventoryItem::findOrFail($request->itemid);
        $qty = floatval($request->qty);
        $unit = $request->unit;

        $baseUnit = strtolower(trim($item->unit));
        $inputUnit = strtolower(trim($unit));

        if (($baseUnit === 'kg' && ($inputUnit === 'gr' || $inputUnit === 'g')) || 
            ($baseUnit === 'l' && ($inputUnit === 'ml'))) {
            $qty = $qty / 1000;
            $unit = $item->unit;
        }

        DB::beginTransaction();
        try {
            // 1. Create Inventory Log (OUT) - Status is usually implicit by existence
            // but we need to track if it's "real" stock deduction or just reported.
            // For reported damage, we record it but maybe stock checker should ignore status 0?
            // Actually, best to record log ONLY when approved. 
            // BUT Order details need log ID. 
            // Let's stick to current structure: Log exists, Opname status controls approval.
            
            $log = InventoryLog::create([
                'itemid' => $request->itemid,
                'branchid' => $request->branchid,
                'qty' => $qty,
                'unit' => $unit,
                'price' => 0,
                'type' => 'OUT',
                'created_at' => now(),
            ]);

            // 2. Handle Evidence Upload
            $evidenceUrl = null;
            if ($request->hasFile('evidence')) {
                $file = $request->file('evidence');
                $filename = time() . '_' . $file->getClientOriginalName();
                $targetDir = base_path('../uploads/damage');
                if (!file_exists($targetDir)) { @mkdir($targetDir, 0777, true); }
                $file->move($targetDir, $filename);
                $evidenceUrl = url('uploads/damage/' . $filename);
            }

            // 3. Create Opname Record - Initial Status: 0 (Pending)
            InventoryOpname::create([
                'inventory_log_id' => $log->inventory_log_id,
                'qty' => $qty,
                'unit' => $unit,
                'reason' => $request->reason,
                'evidence' => $evidenceUrl,
                'status' => 0, // PENDING
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);

            DB::commit();
            return back()->with('success', 'Damage report submitted and is pending approval.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function approve($id)
    {
        // Only Admin (2) or Superadmin (1)
        if (!in_array(auth()->user()->roleid, [1, 2])) {
            return back()->with('error', 'Unauthorized.');
        }

        $opname = InventoryOpname::findOrFail($id);
        $opname->update(['status' => 1]); // APPROVED

        return back()->with('success', 'Damage report approved.');
    }

    public function reject($id)
    {
        if (!in_array(auth()->user()->roleid, [1, 2])) {
            return back()->with('error', 'Unauthorized.');
        }

        $opname = InventoryOpname::findOrFail($id);
        
        DB::beginTransaction();
        try {
            $opname->update(['status' => 2]); // REJECTED
            
            // If rejected, the stock deduction (OUT log) should be reversed or deleted
            if ($opname->log) {
                $opname->log->delete();
            }
            
            DB::commit();
            return back()->with('success', 'Damage report rejected and stock log removed.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }
}