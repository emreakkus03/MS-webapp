<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('dropbox_folders', function (Blueprint $table) {
            $table->id();
            $table->string('dropbox_id')->unique(); // Uniek ID van Dropbox
            $table->string('name');                 // Bv: "2025 - VERGUNNINGEN" of "Dossier A"
            $table->string('path_display');         // Bv: "/MS INFRA/2025/Dossier A"
            $table->string('parent_path')->nullable()->index(); // Om te weten wie de "vader" is

            $table->boolean('is_visible')->default(false); // Het vinkje van de Admin
            $table->boolean('is_synced')->default(false);  // Hebben we de submappen al opgehaald?

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dropbox_folders');
    }
};
