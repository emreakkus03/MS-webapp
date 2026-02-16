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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            
            // --- STATUS & WIZARD ---
            $table->string('status', 50)->default('nieuw'); // Max 50 tekens
            $table->boolean('worked_before')->default(0);
            $table->string('language', 10)->default('nl'); // Max 10 tekens

            // --- PAGINA 1: PERSOONSGEGEVENS ---
            $table->string('name'); // Namen laten we standaard (255) voor de zekerheid
            $table->string('firstname');
            $table->string('street');
            $table->string('house_number', 20); // Max 20
            $table->string('bus_number', 20)->nullable(); // Max 20
            $table->string('zip_code', 20); // Max 20
            $table->string('city');
            $table->string('country');
            
            $table->date('birth_date');
            $table->string('birth_place');
            $table->string('birth_country');
            $table->string('national_register_number', 50); // Max 50
            $table->string('gender', 20); // M/V/X -> Max 20
            
            $table->string('id_card_number', 50); // Max 50
            $table->date('id_card_valid_until');
            
            // Verblijfsvergunning
            $table->string('residence_permit_yn', 10)->default('nee'); // Ja/Nee -> Max 10
            $table->string('residence_permit_issued_at', 50)->nullable(); // Max 50
            $table->date('residence_permit_valid_until')->nullable();

            // --- BURGERLIJKE STAAT ---
            $table->string('marital_status', 50); // Max 50
            $table->date('marital_status_date')->nullable();

            // --- PARTNER ---
            $table->string('partner_name')->nullable();
            $table->string('partner_firstname')->nullable();
            $table->date('partner_birth_date')->nullable();
            $table->string('partner_profession')->nullable();
            $table->string('partner_fiscal_dependent', 10)->nullable(); // Max 10
            $table->string('partner_handicapped', 10)->nullable(); // Max 10

            // --- KINDEREN ---
            $table->integer('children_count')->default(0);
            $table->integer('children_handicapped_count')->default(0);
            $table->integer('other_dependents_count')->default(0);
            $table->integer('other_dependents_handicapped_count')->default(0);

            // --- ALGEMENE INLICHTINGEN ---
            $table->string('bank_account', 50); // IBAN -> Max 50
            $table->string('bic_code', 20); // BIC -> Max 20
            $table->string('phone', 50); // Telefoon -> Max 50
            $table->string('email');
            
            // --- RIJBEWIJZEN ---
            $table->string('driver_license_be_yn', 10)->default('nee'); // Max 10
            $table->date('driver_license_be_valid')->nullable();
            $table->string('driver_license_be_cat', 20)->nullable(); // Max 20
            $table->string('driver_license_be_code95', 20)->nullable(); // Max 20
            
            $table->string('driver_license_foreign_yn', 10)->default('nee'); // Max 10
            $table->date('driver_license_foreign_valid')->nullable();
            $table->string('driver_license_foreign_cat', 20)->nullable(); // Max 20
            $table->string('driver_license_foreign_code95', 20)->nullable(); // Max 20

            // --- KENNIS EN OPLEIDING ---
            $table->string('school_diploma'); 
            $table->string('school_diploma_country');
            $table->date('school_diploma_date')->nullable();

            $certificates = ['vca', 'vol_vca', 'ehbo', 'forklift', 'hoogwerker'];

            foreach ($certificates as $cert) {
                // Optimalisatie: Ja/Nee velden max 10 tekens
                $table->string("{$cert}_yn", 10)->default('nee'); 
                $table->date("{$cert}_obtained_date")->nullable();
                $table->date("{$cert}_valid_date")->nullable();
                $table->string("{$cert}_attest_yn", 10)->default('nee');
            }
            
            // Talen (JSON telt niet mee voor de limiet)
            $table->json('languages'); 

            // --- PAGINA 2: WERKERVARING ---
            for($i=1; $i<=6; $i++) {
                $table->string("exp_{$i}_function")->nullable();
                $table->string("exp_{$i}_company")->nullable();
                $table->string("exp_{$i}_country")->nullable();
                $table->string("exp_{$i}_period")->nullable();
                $table->string("exp_{$i}_years", 20)->nullable(); // Max 20
            }

            // --- OVERIGE VRAGEN (TEXT telt niet mee voor limiet) ---
            $table->text('health_restrictions'); 
            $table->text('hobbies')->nullable();
            $table->text('relation_ms_infra')->nullable();
            
            // Noodcontact
            $table->string('emergency_name');
            $table->string('emergency_contact');

            // Job verwachtingen
            $table->string('job_function_applied');
            $table->string('current_wage', 50); // Max 50
            $table->string('expected_wage', 50); // Max 50
            $table->text('secondary_benefits')->nullable();
            $table->string('notice_period', 50); // Max 50
            $table->date('start_date');

            // Kledij (VERPLICHT) - Allemaal max 20
            $table->string('size_shoes', 20);
            $table->string('size_jacket', 20);
            $table->string('size_pants', 20);
            $table->string('size_polo', 20);

            // Handtekening & Meta
            $table->string('signature_path'); 
            $table->date('signature_date'); 

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};