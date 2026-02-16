<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('r2_pending_uploads', function (Blueprint $table) {
            // ✅ Error message kolom toevoegen (voor debugging)
            if (!Schema::hasColumn('r2_pending_uploads', 'error_message')) {
                $table->text('error_message')->nullable()->after('status');
            }
            
            // ✅ Index toevoegen voor snelle queries
            if (!Schema::hasIndex('r2_pending_uploads', ['task_id', 'status'])) {
                $table->index(['task_id', 'status']);
            }
        });
    }

   public function down(): void
    {
        Schema::table('r2_pending_uploads', function (Blueprint $table) {
            // 👇 Zet hier commentaar voor of verwijder de regel
            // $table->dropIndex(['task_id', 'status']); 
            
            // Laat deze wel staan, want de kolom bestaat waarschijnlijk wel
            if (Schema::hasColumn('r2_pending_uploads', 'error_message')) {
                $table->dropColumn('error_message');
            }
        });
    }
};