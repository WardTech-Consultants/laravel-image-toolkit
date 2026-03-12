<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('optimized_images', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->string('disk');
            $table->unsignedBigInteger('original_size')->nullable();
            $table->unsignedBigInteger('optimized_size')->nullable();
            $table->string('webp_path')->nullable();
            $table->boolean('optimized')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('optimized_images');
    }
};
