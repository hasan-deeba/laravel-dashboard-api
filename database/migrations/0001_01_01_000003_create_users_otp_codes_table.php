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
        Schema::create('users_otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('guard', 20)->default('cms');
            $table->string('code', 6);
            $table->string('type');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['email', 'guard']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_otp_codes');
    }
};
