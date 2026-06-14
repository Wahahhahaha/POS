<?php

namespace App\Models;

use App\Traits\RecordsDeletion;
use App\Traits\TracksDeletionImpact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use RecordsDeletion, SoftDeletes, TracksDeletionImpact;

    protected $table = 'inventory_item';

    protected $primaryKey = 'itemid';

    protected $fillable = [
        'name',
        'barcode',
        'unit',
        'type',
        'deleted_by',
    ];

    protected function getImpactRelations(): array
    {
        return [];
    }
}
