<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('prereg_students')) {
            Schema::create('prereg_students', function (Blueprint $table): void {
                $table->string('email', 255)->primary();
                $table->string('roll', 50)->unique();
                $table->string('full_name', 255);
                $table->string('contact', 20)->nullable();
                $table->string('session', 50)->nullable();
            });
        }

        if (!Schema::hasTable('prereg_teachers')) {
            Schema::create('prereg_teachers', function (Blueprint $table): void {
                $table->string('email', 255)->primary();
                $table->string('designation', 100);
                $table->string('full_name', 255);
                $table->string('contact', 20)->nullable();
            });
        }

        if (!Schema::hasTable('prereg_librarians')) {
            Schema::create('prereg_librarians', function (Blueprint $table): void {
                $table->string('email', 255)->primary();
                $table->string('full_name', 255);
                $table->string('contact', 20)->nullable();
            });
        }

        if (!Schema::hasTable('prereg_directors')) {
            Schema::create('prereg_directors', function (Blueprint $table): void {
                $table->string('email', 255)->primary();
                $table->string('full_name', 255);
                $table->string('contact', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prereg_directors');
        Schema::dropIfExists('prereg_librarians');
        Schema::dropIfExists('prereg_teachers');
        Schema::dropIfExists('prereg_students');
    }
};
