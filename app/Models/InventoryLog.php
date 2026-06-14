<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    protected $table = 'inventory_logs';

    protected $primaryKey = 'inventory_log_id';

    public $timestamps = false; // Based on common pattern for log tables without updated_at

    protected $fillable = [
        'itemid',
        'branchid',
        'type',
        'qty',
        'unit',
        'price',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'itemid', 'itemid');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branchid', 'branchid');
    }
}