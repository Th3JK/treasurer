<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider')->index();
            $table->string('provider_payment_id')->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('status')->index();
            $table->string('method')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['provider', 'provider_payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
