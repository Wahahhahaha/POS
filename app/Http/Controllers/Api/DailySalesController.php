<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\InventoryLog;
use Illuminate\Http\Request;

class DailySalesController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->query('date', now()->format('Y-m-d'));

        $orders = Order::whereDate('ordertime', $date)
            ->where('status', '!=', 'Cancelled') // Exclude cancelled orders
            ->with(['details.menu', 'details.product', 'payment'])
            ->get();

        $reportData = [];
        $totalHargaJual = 0;
        $totalHpp = 0;
        $totalUntung = 0;

        foreach ($orders as $order) {
            $paymentMethod = $order->payment?->method ?? 'Cash';
            foreach ($order->details as $detail) {

                $name = 'Unknown';
                $hargaJualPerItem = 0;
                $cogsPerItem = 0;

                if ($detail->menu) {
                    $name = $detail->menu->name;
                    $hargaJualPerItem = (float) $detail->menu->price;
                    // TODO: Menu COGS based on recipe
                    $cogsPerItem = 0;
                } elseif ($detail->product) {
                    $name = $detail->product->name;
                    
                    // Fetch latest price from IN logs as "Selling Price" for products
                    $latestInLog = InventoryLog::where('itemid', $detail->product->itemid)
                        ->where('branchid', $order->branchid)
                        ->where('type', 'IN')
                        ->latest('created_at')
                        ->first();
                    
                    $hargaJualPerItem = $latestInLog ? (float) $latestInLog->price : 0;
                    $cogsPerItem = 0; // Default 0 for now
                }

                $jumlah = (int) ($detail->quantity ?? 0);
                $totalHargaJualRow = $hargaJualPerItem * $jumlah;
                $totalCogsForDetail = (float) $cogsPerItem * $jumlah;
                $untung = $totalHargaJualRow - $totalCogsForDetail;

                $reportData[] = [
                    'waktu' => $order->ordertime ? $order->ordertime->format('H.i') : '-',
                    'barang' => $name,
                    'jumlah' => $jumlah,
                    'harga_jual' => $totalHargaJualRow,
                    'pembayaran' => $paymentMethod,
                    'hpp' => $totalCogsForDetail,
                    'untung' => $untung,
                ];

                $totalHargaJual += $totalHargaJualRow;
                $totalHpp += $totalCogsForDetail;
                $totalUntung += $untung;
            }
        }

        return response()->json([
            'date' => $date,
            'items' => $reportData,
            'totals' => [
                'harga_jual' => $totalHargaJual,
                'hpp' => $totalHpp,
                'untung' => $totalUntung,
            ],
        ]);
    }
}