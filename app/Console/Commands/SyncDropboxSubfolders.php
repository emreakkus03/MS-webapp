<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DropboxFolder;
use App\Services\DropboxService;
use Illuminate\Support\Facades\Log;

class SyncDropboxSubfolders extends Command
{
    protected $signature = 'dropbox:sync-subfolders';
    protected $description = 'Haalt recursief alle submappen op van zichtbare hoofdmappen';

    public function handle(DropboxService $dropbox)
    {
        $this->info('🚀 Starten met synchronisatie...');

        // 1. Zoek mappen die AAN staan, maar nog niet klaar zijn
        $foldersToSync = DropboxFolder::where('is_visible', true)
                                      ->where('is_synced', false)
                                      ->get();

        if ($foldersToSync->isEmpty()) {
            $this->info('Geen nieuwe mappen om te synchroniseren.');
            return;
        }

        foreach ($foldersToSync as $parentFolder) {
            $this->info("📂 Bezig met ophalen inhoud van: " . $parentFolder->name);
            Log::info("Sync gestart voor: " . $parentFolder->path_display);
            
            try {
                // 👇 HIER WAS DE FOUT: We gebruiken nu de INFRA ID, net zoals in de controller
                $nsId = $dropbox->getInfraNamespaceId(); 
                
                $hasMore = true;
                $cursor = null;
                $totalEntries = 0;

                // 2. Loop door de pagina's van Dropbox
                while ($hasMore) {
                    if ($cursor) {
                        // Volgende pagina ophalen
                        $result = $dropbox->listFoldersContinue($nsId, $cursor);
                    } else {
                        // Eerste keer: RECURSIVE = TRUE (Alles ophalen!)
                        // We gebruiken het path_display direct uit de database
                        $result = $dropbox->listFoldersInNamespace($nsId, $parentFolder->path_display, true);
                    }

                    $entries = $result['entries'];
                    $cursor = $result['cursor'];
                    $hasMore = $result['has_more'];

                    // 3. Verwerken in DB
                    foreach ($entries as $entry) {
                        if (($entry['.tag'] ?? '') === 'folder') {
                            DropboxFolder::updateOrCreate(
                                ['dropbox_id' => $entry['id']],
                                [
                                    'name' => $entry['name'],
                                    'path_display' => $entry['path_display'],
                                    'parent_path' => $parentFolder->path_display, // Koppel aan de hoofdmap (2025)
                                    'is_visible' => true // Submappen zijn ook zichtbaar
                                ]
                            );
                            $totalEntries++;
                        }
                    }
                    $this->info("... {$totalEntries} mappen verwerkt.");
                }

                // 4. Markeer als klaar
                $parentFolder->update(['is_synced' => true]);
                $this->info("✅ Klaar! {$totalEntries} submappen toegevoegd.");

            } catch (\Exception $e) {
                $this->error("❌ Fout: " . $e->getMessage());
                Log::error("Sync error: " . $e->getMessage());
            }
        }
    }
}