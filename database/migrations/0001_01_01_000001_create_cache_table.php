<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('cache.stores.database.table', 'laravel_cache');

        if (!Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $tableGroup) {
                $tableGroup->string('key')->primary();
                $tableGroup->mediumText('value');
                $tableGroup->integer('expiration')->index();
            });
        }

        if (!Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = config('cache.stores.database.table', 'laravel_cache');
        if ($table !== 'cache') {
            Schema::dropIfExists($table);
        }
        Schema::dropIfExists('cache_locks');
    }
};
