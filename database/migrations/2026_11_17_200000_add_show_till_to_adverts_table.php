<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adverts', function (Blueprint $table) {
            if (! Schema::hasColumn('adverts', 'show_till')) {
                $table->timestamp('show_till')->nullable()->after('active_services');
                $table->index('show_till');
            }
        });
    }

    public function down(): void
    {
        Schema::table('adverts', function (Blueprint $table) {
            if (Schema::hasColumn('adverts', 'show_till')) {
                $table->dropIndex(['show_till']);
                $table->dropColumn('show_till');
            }
        });
    }
};
