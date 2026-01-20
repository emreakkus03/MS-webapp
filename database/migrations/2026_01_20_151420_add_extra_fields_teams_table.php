<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            
            $table->string('employee_number')->nullable()->after('name'); 
            
            
            $table->string('subcontractor')->nullable()->after('employee_number'); 
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['employee_number', 'subcontractor']);
        });
    }
};