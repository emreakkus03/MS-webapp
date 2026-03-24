<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\KlipService;

class KlipController extends Controller
{
    protected $klipService;

    public function __construct(KlipService $klipService)
    {
        $this->klipService = $klipService;
    }

    /**
     * Toon het overzicht van alle KLIP planaanvragen.
     */
    public function index()
    {
        try {
            // Haal de MapRequests op via onze service
            $mapRequestsData = $this->klipService->getMapRequests();
            
            // Vaak zit de echte array met requests in een 'items' of 'data' key in de JSON.
            // Check de API response of dit direct een array is, of dat er een key in zit.
            $mapRequests = $mapRequestsData; // Pas aan indien nodig op basis van de Athumi JSON structuur

            return view('klip.index', compact('mapRequests'));

        } catch (\Exception $e) {
            // Foutafhandeling: stuur de gebruiker terug met een error message
            return back()->with('error', 'Kon KLIP data niet ophalen: ' . $e->getMessage());
        }
    }
}