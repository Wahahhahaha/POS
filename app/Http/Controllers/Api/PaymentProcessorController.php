<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Midtrans\Config;
use Midtrans\Snap;

class PaymentProcessorController extends Controller
{
    public function process(Request $request)
    {
        $request->validate([
            'orderId' => 'nullable|integer',
            'paymentMethod' => 'required|string|in:Cash,QRIS',
            'amountPaid' => 'required|numeric',
            'totalPrice' => 'required|numeric',
            'items' => 'required|array', 
            'customerName' => 'nullable|string',
            'orderType' => 'nullable|string|in:Take Away,Takeaway,Counter Order,Table Order',
            'tableId' => 'nullable|integer',
            'branchId' => 'nullable|integer',
            'note' => 'nullable|string',
            'appliedPromotionId' => 'nullable|integer',
            'appliedPromotionName' => 'nullable|string',
            'appliedPromotionDiscount' => 'nullable|numeric',
        ]);

        DB::beginTransaction();
        try {
            $orderId = $request->orderId;
            $user = auth()->user();
            $branchId = $user->roleid == 1 ? ($request->branchId ?: ($user->branchid ?: 1)) : ($user->branchid ?: 1);

            if (! $orderId) {
                $orderId = DB::table('orders')->insertGetId([
                    'name' => $request->customerName,
                    'tableid' => $request->tableId,
                    'ordertime' => now(),
                    'branchid' => $branchId,
                    'ordertype' => $request->orderType ?? 'Counter Order',
                    'status' => 'Cooking', 
                    'totalprice' => $request->totalPrice,
                    'note' => $request->note,
                    'applied_promotion_id' => $request->appliedPromotionId,
                    'applied_promotion_name' => $request->appliedPromotionName,
                    'applied_promotion_discount' => $request->appliedPromotionDiscount ?? 0,
                ]);

                foreach ($request->items as $item) {
                    $isPackage = ! empty($item['is_package']) || (! empty($item['menu']) && ! empty($item['menu']['is_package']));
                    $isProduct = ! empty($item['is_product']);

                    if ($isPackage) {
                        $packageMenus = $item['menus'] ?? ($item['menu']['menus'] ?? []);
                        $packageId = $item['packageid'] ?? ($item['menu']['packageid'] ?? 0);

                        foreach ($packageMenus as $pMenu) {
                            $detailId = DB::table('order_details')->insertGetId([
                                'orderid' => $orderId,
                                'menuid' => (int) $pMenu['id'],
                                'packagename_id' => (int) $packageId,
                                'productid' => 0,
                                'quantity' => $pMenu['qty'] * $item['quantity'],
                                'note' => $item['note'] ?? null,
                            ]);

                            if (! empty($pMenu['addons'])) {
                                foreach ($pMenu['addons'] as $pAddon) {
                                    DB::table('order_addons')->insert([
                                        'detailorderid' => $detailId,
                                        'addonid' => $pAddon['addonid'],
                                    ]);
                                }
                            }
                        }
                    } elseif ($isProduct) {
                        DB::table('order_details')->insert([
                            'orderid' => $orderId,
                            'menuid' => null,
                            'packagename_id' => 0,
                            'productid' => (int) $item['id'],
                            'quantity' => $item['quantity'],
                            'note' => $item['note'] ?? null,
                        ]);
                    } else {
                        $menuId = $item['id'] ?? ($item['menu']['id'] ?? null);

                        $detailId = DB::table('order_details')->insertGetId([
                            'orderid' => $orderId,
                            'menuid' => (int) $menuId,
                            'packagename_id' => 0,
                            'productid' => 0,
                            'quantity' => $item['quantity'],
                            'note' => $item['note'] ?? null,
                        ]);

                        if (! empty($item['addons'])) {
                            foreach ($item['addons'] as $addon) {
                                DB::table('order_addons')->insert([
                                    'detailorderid' => $detailId,
                                    'addonid' => $addon['addonid'],
                                ]);
                            }
                        }
                    }
                }
            } else {
                DB::table('orders')->where('orderid', $orderId)->update([
                    'status' => 'Cooking',
                ]);
            }

            // Handle Promo
            if ($request->appliedPromotionId) {
                $promo = Promotion::find($request->appliedPromotionId);
                if ($promo && $promo->type === 'BUY_X_GET_Y' && $promo->get_menuid) {
                    $exists = DB::table('order_details')->where('orderid', $orderId)->where('menuid', $promo->get_menuid)->where('is_free', true)->exists();
                    if (! $exists) {
                        DB::table('order_details')->insert([
                            'orderid' => $orderId,
                            'menuid' => (int) $promo->get_menuid,
                            'packagename_id' => 0,
                            'productid' => 0,
                            'quantity' => (int) ($promo->get_qty ?? 1),
                            'is_free' => true,
                        ]);
                    }
                }
            }

            $snapToken = null;
            if ($request->paymentMethod === 'QRIS') {
                Config::$serverKey = config('services.midtrans.server_key');
                Config::$isProduction = config('services.midtrans.is_production');
                Config::$isSanitized = true;
                Config::$is3ds = true;

                $params = [
                    'transaction_details' => [
                        'order_id' => 'POS-'.$orderId.'-'.Str::random(5),
                        'gross_amount' => (int) round($request->totalPrice),
                    ],
                ];
                $snapToken = Snap::getSnapToken($params);
                DB::table('orders')->where('orderid', $orderId)->update(['snap_token' => $snapToken]);
            }

            $paidAmount = $request->amountPaid;
            $totalPrice = $request->totalPrice;
            $changes = max(0, $paidAmount - $totalPrice);

            $paymentData = [
                'orderid' => $orderId,
                'paid' => $request->paymentMethod === 'QRIS' ? 0 : $paidAmount,
                'changes' => $request->paymentMethod === 'QRIS' ? 0 : $changes,
                'method' => $request->paymentMethod === 'Cash' ? 'Cashier Payment' : 'QRIS',
                'status' => $request->paymentMethod === 'QRIS' ? 'Pending' : 'Paid',
                'paymentdate' => now(),
            ];

            $existingPayment = DB::table('payments')->where('orderid', $orderId)->first();
            if ($existingPayment) {
                DB::table('payments')->where('paymentid', $existingPayment->paymentid)->update($paymentData);
                $paymentId = $existingPayment->paymentid;
            } else {
                $paymentId = DB::table('payments')->insertGetId($paymentData);
            }

            DB::table('orders')->where('orderid', $orderId)->update(['paymentid' => $paymentId]);

            DB::commit();

            return response()->json([
                'success' => true,
                'orderId' => $orderId,
                'snapToken' => $snapToken,
                'message' => 'Payment processed successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error processing payment: '.$e->getMessage(),
            ], 500);
        }
    }

    public function receipt(int $orderId)
    {
        $order = Order::with(['details.menu', 'details.addons.addon', 'details.package', 'details.packageDefinition', 'details.product', 'tableSeat', 'branch'])->find($orderId);
        $payment = DB::table('payments')->where('orderid', $orderId)->first();
        $system = DB::table('system')->first();

        $displayInfo = [
            'name' => $order->branch ? $order->branch->branchname : ($system->systemname ?? 'Joyi'),
            'logo' => $system->systemlogo ?? null,
            'systemname' => $system->systemname ?? 'Joyi',
            'systemlogo' => $system->systemlogo ?? null,
            'address' => $order->branch ? $order->branch->detail_address : ($system->systemaddress ?? ''),      
            'contact' => $system->systemcontact ?? '',
        ];

        return Inertia::render('Payments/Receipt', [
            'order' => $order,
            'payment' => $payment,
            'system' => $displayInfo,
        ]);
    }
}