<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserBankAccount;
use App\Models\UserBankAccountPool;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UserBankAccountAssignmentService
{
    public function assignToUser(User $user, ?UserBankAccountPool $preferredPool = null): ?UserBankAccount
    {
        return DB::transaction(function () use ($user, $preferredPool) {
            $existingAccount = $this->getLockedCurrentAssignment($user);

            if ($existingAccount) {
                return $existingAccount;
            }

            $poolAccount = $this->getLockedAssignablePool($preferredPool?->id);

            if (!$poolAccount) {
                return null;
            }

            return $this->createAssignment($user, $poolAccount);
        });
    }

    public function reassignUser(User $user, UserBankAccountPool $preferredPool): UserBankAccount
    {
        return DB::transaction(function () use ($user, $preferredPool) {
            $currentAssignment = $this->getLockedCurrentAssignment($user);

            if ($currentAssignment && (int) $currentAssignment->user_bank_account_pool_id === (int) $preferredPool->id) {
                return $currentAssignment;
            }

            $poolAccount = $this->getLockedAssignablePool($preferredPool->id);

            if (!$poolAccount) {
                throw new RuntimeException('No available active bank account was found for reassignment.');
            }

            if ($currentAssignment) {
                $this->releaseCurrentAssignment($currentAssignment);
            }

            return $this->createAssignment($user, $poolAccount);
        });
    }

    public function releaseFromUser(User $user): void
    {
        DB::transaction(function () use ($user) {
            $currentAssignment = $this->getLockedCurrentAssignment($user);

            if (!$currentAssignment) {
                return;
            }

            $this->releaseCurrentAssignment($currentAssignment);
        });
    }

    protected function getLockedCurrentAssignment(User $user): ?UserBankAccount
    {
        return UserBankAccount::query()
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();
    }

    protected function getLockedAssignablePool(?int $poolId = null): ?UserBankAccountPool
    {
        $query = UserBankAccountPool::query()
            ->active()
            ->lockForUpdate();

        if ($poolId) {
            $poolAccount = $query->find($poolId);

            if (!$poolAccount) {
                throw new RuntimeException('The selected bank account pool record was not found or is inactive.');
            }

            if ($poolAccount->assigned_user_id !== null) {
                throw new RuntimeException('The selected bank account pool record is already assigned to another user.');
            }

            return $poolAccount;
        }

        return $query
            ->available()
            ->orderBy('id')
            ->first();
    }

    protected function createAssignment(User $user, UserBankAccountPool $poolAccount): UserBankAccount
    {
        $assignedAt = now();

        $account = UserBankAccount::query()->create([
            'user_id' => $user->id,
            'user_bank_account_pool_id' => $poolAccount->id,
            'iban' => $poolAccount->iban,
            'account_holder_name' => $poolAccount->account_holder_name ?: $user->fullname(),
            'bank_name' => $poolAccount->bank_name,
            'account_number' => $poolAccount->account_number,
            'currency_code' => $poolAccount->currency_code,
            'swift_bic' => $poolAccount->swift_bic,
            'country_code' => $poolAccount->country_code,
            'assignment_source' => $poolAccount->assignment_source ?: 'pool',
            'status' => 1,
            'assigned_at' => $assignedAt,
            'notes' => $poolAccount->notes,
            'meta' => $poolAccount->meta,
        ]);

        $poolAccount->forceFill([
            'assigned_user_id' => $user->id,
            'assigned_at' => $assignedAt,
        ])->save();

        return $account;
    }

    protected function releaseCurrentAssignment(UserBankAccount $assignment): void
    {
        if ($assignment->user_bank_account_pool_id) {
            $poolAccount = UserBankAccountPool::query()
                ->lockForUpdate()
                ->find($assignment->user_bank_account_pool_id);

            if ($poolAccount) {
                $poolAccount->forceFill([
                    'assigned_user_id' => null,
                    'assigned_at' => null,
                ])->save();
            }
        }

        $assignment->delete();
    }
}
