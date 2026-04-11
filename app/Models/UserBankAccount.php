<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBankAccount extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'meta' => 'array',
        'assigned_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pool()
    {
        return $this->belongsTo(UserBankAccountPool::class, 'user_bank_account_pool_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
