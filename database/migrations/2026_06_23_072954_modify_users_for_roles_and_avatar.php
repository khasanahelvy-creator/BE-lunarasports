<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Kita biarkan is_admin tetap ada agar backward compatible, 
            // tapi kita tambah role untuk sistem multi-level
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('user')->after('is_admin'); // user, admin, superadmin
            }
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'avatar']);
        });
    }
};