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
        Schema::create('provider_oauth_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->index();
            $table->string('client_id');
            $table->string('client_secret');
            $table->string('redirect_uri');
            $table->string('scope')->default('read:user user:email');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_oauth_credentials');
    }
};
