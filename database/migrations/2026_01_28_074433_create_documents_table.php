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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            // Link naar de sollicitatie "Map"
            $table->foreignId('application_id')->constrained()->onDelete('cascade');
            
            // Soort document (bijv. 'contract', 'veiligheid_fiche')
            $table->string('type'); 
            
            // Status van dit specifieke document
            $table->string('status')->default('concept'); // concept, getekend
            
            // Extra data die de admin invult (JSON is hier handig voor)
            $table->json('admin_data')->nullable(); 
            
            // Handtekening paden
            $table->string('admin_signature')->nullable();
            $table->string('applicant_signature')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
