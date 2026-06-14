<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Branch;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class KitchenController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->input('limit', 10);
        $branchId = $request->input('branch');
        $user = auth()->user();

        if ($user->roleid != 1) {
            $branchId = $user->branchid;
        }

        $pendingOrdersQuery = Order::with('tableSeat')
            ->where('status', 'Cooking')
            ->whereNull('completiontime')
            ->when($branchId, function($q) use ($branchId) {
                $q->where('branchid', $branchId);
            })
            ->orderBy('ordertime', 'asc');

        $totalPending = $pendingOrdersQuery->count();
        $pendingOrders = $pendingOrdersQuery->limit($limit)->get()
            ->map(function ($order) {
                $details = DB::table('order_details')
                    ->leftJoin('menus', 'order_details.menuid', '=', 'menus.menuid')
                    ->leftJoin('inventory_item', 'order_details.productid', '=', 'inventory_item.itemid') // Refactored to inventory_item
                    ->where('order_details.orderid', $order->orderid)
                    ->select(
                        'order_details.detailorderid',
                        'order_details.quantity',
                        'order_details.note',
                        'order_details.packagename_id',
                        'menus.name as menu_name',
                        'inventory_item.name as item_name' // Refactored
                    )
                    ->get();

                $processedItems = [];
                $packageGroups = [];

                foreach ($details as $detail) {
                    $name = $detail->menu_name ?? $detail->item_name ?? 'Unknown Item';

                    $addons = DB::table('order_addons')
                        ->join('addons', 'order_addons.addonid', '=', 'addons.addonid')
                        ->where('order_addons.detailorderid', $detail->detailorderid)
                        ->select('addons.name')
                        ->get();

                    if ($detail->packagename_id) {
                        if (!isset($packageGroups[$detail->packagename_id])) {
                            $packageGroups[$detail->packagename_id] = [
                                'id' => 'pkg-' . $detail->packagename_id,
                                'name' => 'PROMO PACKAGE',
                                'quantity' => 1,
                                'note' => $detail->note,
                                'is_package' => true,
                                'sub_items' => []
                            ];
                        }
                        if (empty($packageGroups[$detail->packagename_id]['note']) && !empty($detail->note)) {  
                            $packageGroups[$detail->packagename_id]['note'] = $detail->note;
                        }
                        $packageGroups[$detail->packagename_id]['sub_items'][] = [
                            'name' => $name,
                            'quantity' => $detail->quantity,
                            'addons' => $addons->pluck('name')->toArray(),
                        ];
                    } else {
                        $processedItems[] = [
                            'id' => $detail->detailorderid,
                            'name' => $name,
                            'quantity' => $detail->quantity,
                            'note' => $detail->note,
                            'is_package' => false,
                            'addons' => $addons->pluck('name')->toArray(),
                        ];
                    }
                }

                foreach ($packageGroups as $pkg) {
                    $processedItems[] = $pkg;
                }

                return [
                    'id' => $order->orderid,
                    'name' => $order->name,
                    'tableName' => $order->tableSeat ? $order->tableSeat->name : 'Walk-in / Cashier',
                    'orderTime' => $order->ordertime,
                    'orderType' => $order->ordertype,
                    'status' => $order->status,
                    'note' => $order->note,
                    'items' => $processedItems,
                ];
            });

        $completedOrders = Order::with('tableSeat')
            ->where('status', 'Served')
            ->when($branchId, function($q) use ($branchId) {
                $q->where('branchid', $branchId);
            })
            ->where(function($query) {
                $query->whereDate('ordertime', today())
                      ->orWhere(function($q) {
                          $q->whereNotNull('completiontime')
                            ->where('completiontime', '>=', now()->startOfDay()->timestamp);
                      });
            })
            ->orderBy('completiontime', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->orderid,
                    'name' => $order->name,
                    'tableName' => $order->tableSeat ? $order->tableSeat->name : 'Walk-in / Cashier',
                    'orderTime' => $order->ordertime,
                    'completionTime' => $order->completiontime ? date('Y-m-d H:i:s', $order->completiontime) : $order->ordertime,
                    'orderType' => $order->ordertype,
                    'status' => $order->status,
                ];
            });

        return Inertia::render('Kitchen/Index', [
            'pendingOrders' => $pendingOrders,
            'completedOrders' => $completedOrders,
            'totalPending' => $totalPending,
            'currentLimit' => (int) $limit,
            'branches' => $user->roleid == 1 ? Branch::all(['branchid', 'branchname']) : [],
            'filters' => $request->only(['branch', 'limit']),
        ]);
    }

    public function complete(Request $request, $orderid)
    {
        $order = Order::findOrFail($orderid);

        if ($order->status === 'Cooking' && is_null($order->completiontime)) {
            DB::beginTransaction();
            try {
                $this->deductInventory($order->orderid);

                $order->update([
                    'status' => 'Served',
                    'completiontime' => now()->timestamp,
                ]);

                DB::commit();
                return back()->with('success', 'Order marked as Served!');
            } catch (\Exception $e) {
                DB::rollBack();
                return back()->with('error', 'Error reducing stock: '.$e->getMessage());
            }
        }

        return back()->with('error', 'Cannot complete this order.');
    }

    private function deductInventory($orderId)
    {
        \Log::info("Inventory deduction triggered for Order #{$orderId}");
    }
}