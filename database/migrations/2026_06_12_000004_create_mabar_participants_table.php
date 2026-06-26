<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mabar_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mabar_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            
            // Seorang user hanya boleh request join 1 kali ke mabar yang sama
            $table->unique(['mabar_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mabar_participants');
    }
};
