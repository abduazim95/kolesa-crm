<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kolesa_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 16)->index();
            $table->string('current_section', 16)->nullable();
            $table->unsignedInteger('live_processed')->default(0);
            $table->unsignedInteger('archive_processed')->default(0);
            $table->unsignedInteger('live_total')->nullable();
            $table->unsignedInteger('archive_total')->nullable();
            $table->unsignedInteger('live_offset')->default(0);
            $table->unsignedInteger('archive_offset')->default(0);
            $table->unsignedInteger('marked_removed_count')->default(0);
            $table->string('error_type', 32)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kolesa_sync_runs');
    }
};
