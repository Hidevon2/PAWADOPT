<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('species');
            $table->string('breed');
            $table->unsignedInteger('age_months');
            $table->string('gender');
            $table->string('size');
            $table->string('location');
            $table->text('description');
            $table->json('traits');
            $table->string('image');
            $table->unsignedInteger('adoption_fee')->default(0);
            $table->string('status')->default('available');
            $table->timestamps();
        });
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'pet_id']);
        });
        Schema::create('adoption_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained()->restrictOnDelete();
            $table->string('reference')->unique();
            $table->string('phone', 30);
            $table->string('city', 120);
            $table->string('housing', 40);
            $table->string('experience', 40);
            $table->text('message');
            $table->string('status')->default('submitted');
            $table->timestamps();
            $table->unique(['user_id', 'pet_id']);
        });
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email');
            $table->string('subject', 120);
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('adoption_applications');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('pets');
        Schema::dropIfExists('api_tokens');
    }
};
