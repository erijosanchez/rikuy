<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extiende datasets con todo lo que necesita la ingesta: origen, archivo
     * subido, mapeo de columnas, error y momento de procesamiento.
     */
    public function up(): void
    {
        Schema::table('datasets', function (Blueprint $table) {
            $table->string('source')->default('upload')->after('name');
            $table->string('original_filename')->nullable()->after('source');
            $table->string('file_path')->nullable()->after('original_filename');
            $table->json('column_map')->nullable()->after('file_path');
            $table->text('error')->nullable()->after('status');
            $table->timestamp('processed_at')->nullable()->after('rows');
        });
    }

    public function down(): void
    {
        Schema::table('datasets', function (Blueprint $table) {
            $table->dropColumn([
                'source', 'original_filename', 'file_path',
                'column_map', 'error', 'processed_at',
            ]);
        });
    }
};
