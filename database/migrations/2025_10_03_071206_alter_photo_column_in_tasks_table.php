<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Verander de kolom naar TEXT zodat er meer paden in passen
            $table->text('photo')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Terugzetten naar VARCHAR(255) (of wat het oorspronkelijk was)
            $table->string('photo', 255)->nullable()->change();
        });
    }
};
