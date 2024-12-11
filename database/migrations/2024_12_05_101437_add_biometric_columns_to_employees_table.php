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
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('is_fingerprint_enrolled')->default(false)->after('salary');
            $table->integer('machine_number')->nullable()->after('is_fingerprint_enrolled');
            $table->integer('fingerprint_index')->nullable()->after('machine_number');
            $table->text('fingerprint_data')->nullable()->after('fingerprint_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            //
        });
    }
};
