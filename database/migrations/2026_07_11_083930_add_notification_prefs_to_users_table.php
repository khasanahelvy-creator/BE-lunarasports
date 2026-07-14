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
            $table->boolean('pref_whatsapp_reminder')->default(true);
            $table->boolean('pref_email_promo')->default(true);
            $table->boolean('pref_friend_activity')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pref_whatsapp_reminder', 'pref_email_promo', 'pref_friend_activity']);
        });
    }
};
