<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_requests', 'provider_reference')) {
                $table->string('provider_reference', 191)->nullable()->after('transaction_id')->index();
            }
            if (!Schema::hasColumn('payment_requests', 'provider_transaction_id')) {
                $table->string('provider_transaction_id', 191)->nullable()->after('provider_reference')->index();
            }
            if (!Schema::hasColumn('payment_requests', 'provider_payment_method')) {
                $table->string('provider_payment_method', 100)->nullable()->after('provider_transaction_id');
            }
            if (!Schema::hasColumn('payment_requests', 'payment_phone')) {
                $table->string('payment_phone', 30)->nullable()->after('provider_payment_method')->index();
            }
            if (!Schema::hasColumn('payment_requests', 'control_number')) {
                $table->string('control_number', 100)->nullable()->after('payment_phone')->index();
            }
            if (!Schema::hasColumn('payment_requests', 'payment_status')) {
                $table->string('payment_status', 50)->nullable()->after('control_number')->index();
            }
            if (!Schema::hasColumn('payment_requests', 'payment_currency')) {
                $table->string('payment_currency', 20)->nullable()->after('payment_status');
            }
            if (!Schema::hasColumn('payment_requests', 'payment_metadata')) {
                $table->longText('payment_metadata')->nullable()->after('payment_currency');
            }
            if (!Schema::hasColumn('payment_requests', 'verification_result')) {
                $table->string('verification_result', 191)->nullable()->after('payment_metadata');
            }
            if (!Schema::hasColumn('payment_requests', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verification_result');
            }
            if (!Schema::hasColumn('payment_requests', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('verified_at');
            }
            if (!Schema::hasColumn('payment_requests', 'failed_at')) {
                $table->timestamp('failed_at')->nullable()->after('paid_at');
            }
            if (!Schema::hasColumn('payment_requests', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('failed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            foreach ([
                'provider_reference',
                'provider_transaction_id',
                'provider_payment_method',
                'payment_phone',
                'control_number',
                'payment_status',
                'payment_currency',
                'payment_metadata',
                'verification_result',
                'verified_at',
                'paid_at',
                'failed_at',
                'expires_at',
            ] as $column) {
                if (Schema::hasColumn('payment_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
