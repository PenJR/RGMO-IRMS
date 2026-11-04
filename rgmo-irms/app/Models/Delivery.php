<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['received_on' => 'date'];
    }

    public function procurement()
    {
        return $this->belongsTo(Procurement::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
