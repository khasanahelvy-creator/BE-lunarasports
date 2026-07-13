<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            if (!Schema::hasColumn('venues', 'phone')) {
                $table->string('phone')->nullable()->after('lokasi');
            }
            if (!Schema::hasColumn('venues', 'image')) {
                $table->string('image')->nullable()->after('phone');
            }
        });

        // Update status kolom existing: 'approved' -> 'active' 
        \Illuminate\Support\Facades\DB::table('venues')
            ->where('status', 'approved')
            ->update(['status' => 'active']);
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['phone', 'image']);
        });
    }
};