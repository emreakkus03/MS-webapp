<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Notifications\OrderReady;
use App\Models\Team;
use Illuminate\Support\Facades\Log;

use App\Exports\MasterExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\DropboxService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\File;

class WarehouseController extends Controller
{
    private function checkAdminAndWarehousemanAccess()
    {
        $user = Auth::user();

        // Als er geen user is OF de rol is geen admin of warehouseman -> STOP.
        if (!$user || !in_array($user->role, ['admin', 'warehouseman'])) {
            abort(403, 'Geen toegang. Alleen voor beheerders.');
        }
    }
    public function index(Request $request)
    {
        $this->checkAdminAndWarehousemanAccess();

        // We starten een query op de Order tabel
        $query = Order::with(['team', 'materials']);

        // FILTER 1: MODUS (Openstaand vs Historiek)
        // Als we in de URL ?show=history hebben staan:
        if ($request->get('show') === 'history') {
            $isHistory = true;
            $query->where('status', 'ready'); // Alleen wat klaar is
            $query->orderBy('pickup_date', 'desc'); // Nieuwste bovenaan
        } else {
            // Standaard modus: Werklijst
            $isHistory = false;
            $query->whereIn('status', ['pending', 'printed']); // Nog te doen
            $query->orderBy('pickup_date', 'asc'); // Dringendste eerst (oudste datum)
        }

        // FILTER 2: DATUM
        // Als er een datum gekozen is, filteren we daarop
        if ($request->filled('date')) {
            $query->whereDate('pickup_date', $request->date);
        }

        // PAGINERING
        // We gebruiken paginate(20) zodat je bij 4 jaar historiek niet 10.000 rijen laadt
        $orders = $query->paginate(20)->withQueryString();

        return view('warehouse.index', compact('orders', 'isHistory'));
    }

    public function printOrder(Order $order)
    {
        $this->checkAdminAndWarehousemanAccess();
        // Update status naar 'printed' als hij nog op 'pending' stond.
        // Zo weet een collega: "Hé, deze wordt al gepakt".
        if ($order->status === 'pending') {
            $order->update(['status' => 'printed']);
        }

        return view('warehouse.print', compact('order'));
    }

 public function markAsReady(Request $request, $id, DropboxService $dropboxService)
    {
        $this->checkAdminAndWarehousemanAccess();
        
        // 1. BELANGRIJK: Voeg 'team' toe aan de with() zodat we de teamnaam straks hebben
        $order = Order::with(['materials', 'team'])->findOrFail($id);

        $order->update(['status' => 'ready']);
        
        // Omdat we 'team' hierboven al hebben ingeladen, hoeven we Team::find() niet meer te doen
        if ($order->team) {
            $order->team->notify(new OrderReady($order));
        }

        $msg = 'Order is klaar gemeld!';

        try {
            $namespaceId = $dropboxService->getFluviusNamespaceId();

            $categories = ['fluvius', 'handgereedschap'];

            foreach ($categories as $category) {
                $materialsOfCategory = $order->materials->filter(function ($material) use ($category) {
                    return strtolower($material->category) === $category;
                });

                if ($materialsOfCategory->isNotEmpty()) {
                    // 2. Geef $order mee als laatste parameter aan de functie!
                    $msg .= $this->appendAndUploadMasterExcel($materialsOfCategory, $category, $dropboxService, $namespaceId, $order);
                }
            }

        } catch (\Exception $e) {
            Log::error("Fout bij ophalen MS FLUVIUS namespace: " . $e->getMessage());
            $msg .= ' (Kon Dropbox verbinding niet maken).';
        }

        return back()->with('success', $msg);
    }

    // 3. Update de functie zodat hij $order accepteert
    private function appendAndUploadMasterExcel($materials, $category, $dropboxService, $namespaceId, $order)
    {
        try {
            $jaar = date('Y'); 
            $folderName = ucfirst($category); 
            $fileName = "{$folderName}_Totaal_{$jaar}.xlsx"; 
            
            $dropboxPath = "/Magazijn_Bestellingen/{$folderName}/{$fileName}";
            $localTempPath = "temp/{$fileName}";
            
            if (!Storage::disk('local')->exists('temp')) {
                Storage::disk('local')->makeDirectory('temp');
            }

            $existingRows = [];

            $fileContent = $dropboxService->download($namespaceId, $dropboxPath);

            if ($fileContent) {
                Storage::disk('local')->put($localTempPath, $fileContent);
                $existingData = Excel::toArray(new \stdClass, $localTempPath, 'local');
                
                if (!empty($existingData) && isset($existingData[0])) {
                    $existingRows = array_slice($existingData[0], 1); 
                }
            }

            // HIER IS DE NIEUWE LOGICA VOOR DATUM EN TEAM
            // We pakken de datum van vandaag (bv. '10-03-2026')
            $datumVandaag = date('d-m-Y'); 
            
            // We halen de teamnaam op. (Let op: als jouw kolom in de teams tabel niet 'name' heet maar bv 'naam', pas dit dan even aan!)
            $teamNaam = $order->team ? $order->team->name : 'Onbekend Team';

            $newRows = [];
            foreach ($materials as $material) {
                $newRows[] = [
                    $teamNaam,                    // Kolom 1: Team
                    $datumVandaag,                // Kolom 2: Datum Klaargemeld
                    $material->sap_number,        // Kolom 3: SAP
                    $material->pivot->quantity,   // Kolom 4: Aantal
                ];
            }

            $allRows = array_merge($existingRows, $newRows);

            $saved = Excel::store(new MasterExport($allRows), $localTempPath, 'local');

            if (!$saved) {
                throw new \Exception("Excel package weigert het bestand te genereren in de temp map!");
            }

            $absolutePath = Storage::disk('local')->path($localTempPath);
            
            if (!file_exists($absolutePath)) {
                throw new \Exception("Bestand is onvindbaar op schijf: {$absolutePath}");
            }

            $fileObject = new \Illuminate\Http\File($absolutePath);
            $dropboxService->uploadOverwrite($namespaceId, $dropboxPath, $fileObject);

            Storage::disk('local')->delete($localTempPath);

            return " Gegevens bijgevuld in {$fileName} en geüpload naar Dropbox!";

        } catch (\Exception $e) {
            Log::error("Fout bij updaten Master Excel voor {$category} in jaar " . date('Y') . ": " . $e->getMessage());
            return " (Updaten {$category} Excel mislukt).";
        }
    }

}
