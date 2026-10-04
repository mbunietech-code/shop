<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('click_pesa_webhook_logs')) {
            return;
        }

        Schema::create('click_pesa_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->char('payment_request_id', 36)->nullable()->index();
            $table->string('provider', 50)->default('click_pesa')->index();
            $table->string('event', 100)->nullable()->index();
            $table->string('request_identifier', 191)->nullable()->index();
            $table->string('provider_reference', 191)->nullable()->index();
            $table->string('provider_transaction_id', 191)->nullable()->index();
            $table->longText('payload')->nullable();
            $table->string('processing_status', 50)->default('received')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('click_pesa_webhook_logs');
    }
};
