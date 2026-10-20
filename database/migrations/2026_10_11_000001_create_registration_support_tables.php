<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('PreReg_Students')) {
            Schema::create('PreReg_Students', function (Blueprint $table) {
                $table->string('email', 255)->primary();
                $table->string('roll', 50)->unique();
                $table->string('full_name', 255);
                $table->string('contact', 20)->nullable();
                $table->string('session', 50)->nullable();
            });
        }

        if (!Schema::hasTable('PreReg_Teachers')) {
            Schema::create('PreReg_Teachers', function (Blueprint $table) {
                $table->string('email', 255)->primary();
                $table->string('designation', 100);
                $table->string('full_name', 255);
                $table->string('contact', 20)->nullable();
            });
        }

        foreach (['PreReg_Librarians', 'PreReg_Directors'] as $tableName) {
            if (!Schema::hasTable($tableName)) {
                Schema::create($tableName, function (Blueprint $table) {
                    $table->string('email', 255)->primary();
                    $table->string('full_name', 255);
                    $table->string('contact', 20)->nullable();
                });
            }
        }

        if (!Schema::hasTable('Library_Settings')) {
            Schema::create('Library_Settings', function (Blueprint $table) {
                $table->increments('setting_id');
                $table->string('setting_key', 50)->unique();
                $table->text('setting_value');
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });
        }

        DB::table('Library_Settings')->insertOrIgnore([
            ['setting_key' => 'library_email', 'setting_value' => 'library@nstu.edu.bd'],
            ['setting_key' => 'library_phone', 'setting_value' => '+880 1234-567890'],
            ['setting_key' => 'library_hours', 'setting_value' => 'Mon-Fri: 9:00 AM - 5:00 PM'],
            ['setting_key' => 'library_location', 'setting_value' => 'Central Library, NSTU Campus'],
        ]);

        if (!Schema::hasTable('Temp_User_Verification')) {
            Schema::create('Temp_User_Verification', function (Blueprint $table) {
                $table->string('email', 255);
                $table->string('otp_code', 20);
                $table->string('purpose');
                $table->dateTime('created_at')->useCurrent();
                $table->dateTime('expires_at');
                $table->primary(['email', 'purpose']);
            });
        }
    }

    public function down(): void
    {
        // Keep registration and verification data intact if this migration is rolled back.
    }
};
