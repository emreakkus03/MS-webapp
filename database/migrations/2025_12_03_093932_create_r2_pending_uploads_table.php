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
       Schema::create('r2_pending_uploads', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('task_id');
    $table->string('r2_path');
    $table->string('namespace_id');
    $table->string('perceel')->nullable();
    $table->string('regio_path')->nullable();
    $table->string('adres_path')->nullable();
    $table->string('target_dropbox_path')->nullable();
    $table->string('status')->default('pending');
    $table->timestamps();

    $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('r2_pending_uploads');
    }
};
