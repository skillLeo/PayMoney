<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_bank_account_pools', function (Blueprint $table) {
            $table->id();
            $table->string('label')->nullable();
            $table->string('iban')->unique();
            $table->string('account_holder_name')->nullable();
            $table->string('bank_name');
            $table->string('account_number')->nullable();
            $table->string('currency_code', 20)->nullable();
            $table->string('swift_bic', 50)->nullable();
            $table->string('country_code', 10)->nullable();
            $table->string('assignment_source', 50)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
            $table->timestamp('assigned_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('assigned_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('user_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('user_bank_account_pool_id')->nullable()->unique();
            $table->string('iban')->unique();
            $table->string('account_holder_name')->nullable();
            $table->string('bank_name');
            $table->string('account_number')->nullable();
            $table->string('currency_code', 20)->nullable();
            $table->string('swift_bic', 50)->nullable();
            $table->string('country_code', 10)->nullable();
            $table->string('assignment_source', 50)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamp('assigned_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('user_bank_account_pool_id')->references('id')->on('user_bank_account_pools')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_bank_accounts');
        Schema::dropIfExists('user_bank_account_pools');
    }
};
