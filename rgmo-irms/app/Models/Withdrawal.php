<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['withdrawn_on' => 'date'];
    }

    public function items()
    {
        return $this->hasMany(WithdrawalItem::class);
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function request()
    {
        return $this->belongsTo(ResourceRequest::class, 'resource_request_id');
    }
}
