<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBankAccountPool extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'meta' => 'array',
        'assigned_at' => 'datetime',
    ];

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function assignment()
    {
        return $this->hasOne(UserBankAccount::class, 'user_bank_account_pool_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeAvailable($query)
    {
        return $query->whereNull('assigned_user_id');
    }
}
