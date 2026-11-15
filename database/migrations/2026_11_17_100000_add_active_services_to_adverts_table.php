<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adverts', function (Blueprint $table) {
            if (! Schema::hasColumn('adverts', 'active_services')) {
                $table->json('active_services')->nullable()->after('favorites_week');
            }
        });
    }

    public function down(): void
    {
        Schema::table('adverts', function (Blueprint $table) {
            if (Schema::hasColumn('adverts', 'active_services')) {
                $table->dropColumn('active_services');
            }
        });
    }
};
