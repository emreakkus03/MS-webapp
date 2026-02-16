<?php

namespace App\Imports;

use App\Models\Material;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow; // Nodig voor startrij
use Maatwebsite\Excel\Concerns\WithUpserts;  // Nodig om dubbels te voorkomen

class MaterialsImport implements ToModel, WithStartRow, WithUpserts
{
    /**
     * @return int
     */
    public function startRow(): int
    {
        return 11; // We beginnen pas te lezen vanaf rij 11
    }

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        // Check: Is kolom A (SAP nr) leeg? Dan stoppen we (voor het geval er na rij 399 nog rommel staat)
        if (!isset($row[0])) {
            return null;
        }

        return new Material([
            'sap_number'  => $row[0], // Kolom A
            'description' => $row[1], // Kolom B
            'unit'        => $row[2], // Kolom C
            'packaging'   => $row[3], // Kolom D
            'category'    => $row[4],
        ]);
    }

    /**
     * Zorgt ervoor dat als een SAP nummer al bestaat, hij geüpdatet wordt 
     * in plaats van dubbel aangemaakt.
     */
    public function uniqueBy()
    {
        return 'sap_number';
    }
}