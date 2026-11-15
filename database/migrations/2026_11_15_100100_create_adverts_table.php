<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adverts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kolesa_id')->unique();
            $table->string('title', 500)->nullable();
            $table->unsignedBigInteger('price')->nullable();
            $table->enum('status', ['live', 'archive', 'delete', 'temp'])->index();
            $table->string('photo_url', 1000)->nullable();
            $table->unsignedInteger('views_total')->default(0)->index();
            $table->unsignedInteger('views_week')->default(0);
            $table->unsignedInteger('phone_views_total')->default(0)->index();
            $table->unsignedInteger('phone_views_week')->default(0);
            $table->unsignedInteger('favorites_total')->default(0)->index();
            $table->unsignedInteger('favorites_week')->default(0);
            $table->json('active_services')->nullable();
            $table->timestamp('show_till')->nullable()->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('removed_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adverts');
    }
};
