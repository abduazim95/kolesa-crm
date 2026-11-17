<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_applications', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id', 36);
            $table->foreignId('advert_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('kolesa_id');
            $table->string('service_name', 32);
            $table->string('storage_id', 16);
            $table->unsignedBigInteger('estimated_price');
            $table->string('status', 16)->index();
            $table->string('error_type', 32)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index('batch_id');
            $table->index(['advert_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_applications');
    }
};
