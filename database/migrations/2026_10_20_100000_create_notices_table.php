<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('Notices')) {
            return;
        }

        Schema::create('Notices', function (Blueprint $table): void {
            $table->id('notice_id');
            $table->string('title');
            $table->text('message');
            $table->string('type', 40)->default('Info');
            $table->boolean('is_published')->default(true);
            $table->boolean('is_pinned')->default(false);
            $table->string('audience', 40)->default('All');
            $table->dateTime('published_at')->useCurrent();
            $table->dateTime('expires_at')->nullable();
            $table->string('created_by', 255)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['is_published', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Notices');
    }
};
