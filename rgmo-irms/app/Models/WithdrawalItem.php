<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawalItem extends Model
{
    protected $guarded = ['id'];

    public function withdrawal()
    {
        return $this->belongsTo(Withdrawal::class);
    }

    public function requestItem()
    {
        return $this->belongsTo(RequestItem::class);
    }

    public function transaction()
    {
        return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id');
    }
}
