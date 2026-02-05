<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_settings', function (Blueprint $table) {
            $table->id();
            $table->string('template');
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('type')->default('text');
            $table->timestamps();

            $table->unique(['template', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_settings');
    }
};
