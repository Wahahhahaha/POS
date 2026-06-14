<?php

namespace App\Http\Controllers;

use App\Helpers\StockChecker;
use App\Models\Addon;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Order;
use App\Models\InventoryItem;
use App\Models\InventoryLog;
use App\Models\Promo;
use App\Models\Promotion;
use App\Models\TableSeat;
use App\Models\Branch;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $branchId = (int) ($request->input('branch') ?: ($user->branchid ?: 0));

        if ($user->roleid != 1) {
            $branchId = (int) ($user->branchid ?: 0);
        }

        $categories = Category::with(['menus' => function($q) {
            $q->with('media')->orderBy('name', 'asc');
        }, 'menus.addons'])->orderBy('categoryname', 'asc')->get()->map(function ($category) use ($branchId) {  
            return [
                'id' => $category->categoryid,
                'name' => $category->categoryname,
                'menus' => $category->menus->map(function ($menu) use ($branchId) {
                    $categoryAddons = Addon::where('categoryid', $menu->categoryid)->whereNull('menuid')->get();
                    $menuAddons = $menu->addons;
                    $allAddons = $categoryAddons->concat($menuAddons)->unique('addonid');

                    $imageUrl = $menu->getFirstMediaUrl('menu_images');
                    if (!$imageUrl && $menu->picture) {
                        $imageUrl = str_starts_with($menu->picture, 'http')
                            ? $menu->picture
                            : asset('uploads/' . ltrim($menu->picture, '/'));
                    }

                    return [
                        'id' => $menu->menuid,
                        'name' => $menu->name,
                        'price' => $menu->price,
                        'image' => $imageUrl,
                        'is_available' => StockChecker::isMenuAvailable($menu->menuid, $branchId),
                        'max_quantity' => StockChecker::getMaxQuantity($menu->menuid, $branchId),
                        'addons' => $allAddons->map(function ($addon) use ($branchId) {
                            return [
                                'addonid' => $addon->addonid,
                                'name' => $addon->name,
                                'price' => $addon->price,
                                'is_available' => StockChecker::isAddonAvailable($addon->addonid, $branchId),   
                            ];
                        }),
                    ];
                }),
            ];
        });

        $now = now();
        $currentDay = $now->format('l');
        $currentTime = $now->format('H:i:s');

        $activePromos = Promo::with(['package', 'menu', 'addons', 'promoFreebie'])
            ->whereHas('package')
            ->where(function ($q) use ($branchId) {
                $q->where('branchid', $branchId)
                    ->orWhere('branchid', 0)
                    ->orWhereNull('branchid');
            })
            ->where(function ($q) use ($now, $currentDay, $currentTime) {
                $q->where('status', 'Available')
                    ->orWhere(function ($sub) use ($now, $currentDay, $currentTime) {
                        $sub->where('status', 'Certain Period')
                            ->where(function ($date) use ($now) {
                                $date->where(function ($d) use ($now) {
                                    $d->whereNull('datefrom')->orWhereDate('datefrom', '<=', $now);
                                })->where(function ($d) use ($now) {
                                    $d->whereNull('dateto')->orWhereDate('dateto', '>=', $now);
                                });
                            })
                            ->where(function ($day) use ($currentDay) {
                                $day->whereNull('days')
                                    ->orWhere('days', '[]')
                                    ->orWhereJsonContains('days', $currentDay);
                            })
                            ->where(function ($time) use ($currentTime) {
                                $time->where(function ($t) use ($currentTime) {
                                    $t->whereNull('timefrom')->orWhere('timefrom', '<=', $currentTime);
                                })->where(function ($t) use ($currentTime) {
                                    $t->whereNull('timeto')->orWhere('timeto', '>=', $currentTime);
                                });
                            });
                    });
            })
            ->get();

        $promotions = $activePromos->groupBy(function ($item) {
            return $item->packagename_id.'-'.$item->branchid.'-'.$item->price;
        })->map(function ($group) {
            $first = $group->first();
            $freebies = $group->filter(fn($i) => !empty($i->freeid))
                ->map(fn($i) => [
                    'id' => $i->freeid,
                    'name' => $i->promoFreebie->name ?? 'Free Item',
                    'qty' => $i->qty ?? 1,
                ])->values()->toArray();

            return [
                'packageid' => $first->packagename_id,
                'name' => $first->package->packagename ?? 'Promo Package',
                'price' => (float) $first->price,
                'is_package' => true,
                'menus' => $group->map(function ($item) {
                    return [
                        'id' => $item->menuid,
                        'name' => $item->menu->name ?? 'Unknown',
                        'qty' => $item->qty,
                        'price' => $item->price,
                        'addonname' => $item->addons->pluck('name')->join(', '),
                    ];
                })->values()->toArray(),
                'freebies' => $freebies,
                'all_promoids' => $group->pluck('packageid')->toArray(),
                'is_available' => true,
            ];
        })->values()->toArray();

        $activePromotions = Promotion::with(['buyMenu', 'getMenu'])
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branchids')
                    ->orWhereJsonLength('branchids', 0)
                    ->orWhereJsonContains('branchids', (string) $branchId)
                    ->orWhereJsonContains('branchids', (int) $branchId)
                    ->orWhereJsonContains('branchids', '0')
                    ->orWhereJsonContains('branchids', 0);
            })
            ->get();

        $unpaidOrders = Order::with(['tableSeat', 'details.menu', 'details.product', 'details.addons.addon'])   
            ->where('status', 'Pending')
            ->where('branchid', $branchId) 
            ->orderBy('ordertime', 'asc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->orderid,
                    'tableName' => $order->tableSeat->name ?? 'N/A',
                    'name' => $order->name,
                    'orderTime' => $order->ordertime,
                    'orderType' => $order->ordertype,
                    'totalPrice' => $order->totalprice,
                    'items' => $order->details->map(function ($detail) {
                        return [
                            'id' => $detail->menuid ?? $detail->productid,
                            'name' => $detail->menu->name ?? $detail->product->name ?? 'Unknown',
                            'price' => $detail->menu->price ?? 0,
                            'quantity' => $detail->quantity,
                            'itemTotalPrice' => $detail->menu->price ?? 0,
                            'note' => $detail->note,
                            'packagename_id' => $detail->packagename_id,
                            'is_package' => (bool) $detail->packagename_id,
                            'is_product' => (bool) $detail->productid,
                            'addons' => $detail->addons->map(function ($oa) {
                                return [
                                    'addonid' => $oa->addonid,
                                    'name' => $oa->addon->name ?? 'Unknown',
                                    'price' => $oa->addon->price ?? 0,
                                ];
                            }),
                        ];
                    }),
                ];
            });

        $vacantTables = TableSeat::where('branchid', $branchId)->orderBy('name', 'asc')->get();

        $products = InventoryItem::where('type', 'Products')
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($item) use ($branchId) {
                $latestLog = InventoryLog::where('itemid', $item->itemid)
                    ->where('branchid', $branchId)
                    ->where('type', 'IN')
                    ->latest('created_at')
                    ->first();
                $price = $latestLog ? (float) $latestLog->price : 0;
                $stock = InventoryLog::where('itemid', $item->itemid)
                    ->where('branchid', $branchId)
                    ->select(\DB::raw("SUM(CASE WHEN type = 'IN' THEN qty ELSE -qty END) as total_qty"))
                    ->first()
                    ->total_qty ?? 0;

                return [
                    'id' => $item->itemid,
                    'name' => $item->name,
                    'price' => $price,
                    'image' => null,
                    'barcode' => $item->barcode,
                    'is_product' => true,
                    'is_available' => $stock > 0,
                    'max_quantity' => (int) $stock,
                ];
            })->values()->toArray();

        return Inertia::render('Payments/Index', [
            'categories' => $categories,
            'packages' => $promotions,
            'products' => $products,
            'unpaidOrders' => $unpaidOrders,
            'vacantTables' => $vacantTables,
            'activePromotions' => $activePromotions,
            'branches' => $user->roleid == 1 ? Branch::all(['branchid', 'branchname']) : [],
            'activeBranchId' => $branchId,
            'midtransClientKey' => config('services.midtrans.client_key'),
            'midtransIsProduction' => config('services.midtrans.is_production'),
        ]);
    }
}