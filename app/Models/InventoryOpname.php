<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryOpname extends Model
{
    protected $table = 'inventory_opname';

    protected $primaryKey = 'opnameid';

    public $timestamps = false; // Only has created_at based on schema, but let's manage it manually or just disable typical Laravel timestamps if no updated_at

    protected $fillable = [
        'inventory_log_id',
        'qty',
        'unit',
        'reason',
        'evidence',
        'status',
        'created_by',
        'approved_by',
        'created_at'
    ];

    // Optional: cast created_at to datetime if you need it
    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function log()
    {
        return $this->belongsTo(InventoryLog::class, 'inventory_log_id', 'inventory_log_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
