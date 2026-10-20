<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('Requests') || Schema::hasColumn('Requests', 'pic_path')) {
            return;
        }

        Schema::table('Requests', function (Blueprint $table): void {
            $table->string('pic_path', 500)->nullable()->after('pdf_path');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('Requests') && Schema::hasColumn('Requests', 'pic_path')) {
            Schema::table('Requests', function (Blueprint $table): void {
                $table->dropColumn('pic_path');
            });
        }
    }
};
