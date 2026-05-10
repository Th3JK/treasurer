<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_refund_id')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('status')->index();
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
