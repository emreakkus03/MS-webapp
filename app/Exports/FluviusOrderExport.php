<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings; 
use Maatwebsite\Excel\Concerns\WithMapping; 

class FluviusOrderExport implements FromCollection, WithHeadings, WithMapping
{
    protected $order;

    
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    
    public function collection()
    {
        return $this->order->materials;
    }

    
    public function headings(): array
    {
        return [
            'SAP Nummer',
            'Aantal',
        ];
    }

    
    public function map($material): array
    {
        return [
            $material->sap_number,       
            $material->pivot->quantity,  
        ];
    }
}