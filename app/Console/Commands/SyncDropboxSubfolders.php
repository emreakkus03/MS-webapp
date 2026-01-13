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
    $this->info('🚀 Starten met synchronisatie controle...');

    // 1. Haal ALLE zichtbare mappen op, ongeacht of ze al eens gesynct zijn
    // We willen immers checken of er IETS NIEUWS is bijgekomen.
    $foldersToSync = DropboxFolder::where('is_visible', true)->get();

    if ($foldersToSync->isEmpty()) {
        $this->info('Geen zichtbare mappen gevonden om te controleren.');
        return;
    }

    foreach ($foldersToSync as $parentFolder) {
        $this->info("🔄 Controleren op updates in: " . $parentFolder->name);
        
        try {
            $nsId = $dropbox->getInfraNamespaceId(); 
            
            $hasMore = true;
            $cursor = null; // Als je slim wilt zijn, sla je deze cursor op in je DB voor de volgende keer, maar voor nu doen we een 'full scan'
            $totalEntries = 0;

            while ($hasMore) {
                if ($cursor) {
                    $result = $dropbox->listFoldersContinue($nsId, $cursor);
                } else {
                    // We halen ALLES recursief op. 
                    // Let op: Bij HELE grote mappen kan dit traag worden, 
                    // maar voor nu is dit de zekerste manier om nieuwe submappen te vinden.
                    $result = $dropbox->listFoldersInNamespace($nsId, $parentFolder->path_display, true);
                }

                $entries = $result['entries'];
                $cursor = $result['cursor'];
                $hasMore = $result['has_more'];

                foreach ($entries as $entry) {
                    // Alleen mappen opslaan
                    if (($entry['.tag'] ?? '') === 'folder') {
                        
                        // updateOrCreate zorgt ervoor dat:
                        // 1. Nieuwe mappen worden aangemaakt
                        // 2. Bestaande mappen worden geupdate (bv als de naam veranderd is)
                        DropboxFolder::updateOrCreate(
                            ['dropbox_id' => $entry['id']], 
                            [
                                'name' => $entry['name'],
                                'path_display' => $entry['path_display'],
                                'parent_path' => $parentFolder->path_display,
                                'is_visible' => true 
                            ]
                        );
                        $totalEntries++;
                    }
                }
            }

            // We zetten hem op true (voor de zekerheid), maar de volgende keer pakken we hem toch weer mee
            $parentFolder->update(['is_synced' => true]);
            $this->info("✅ {$parentFolder->name} bijgewerkt. Totaal {$totalEntries} submappen in database/gecontroleerd.");

        } catch (\Exception $e) {
            $this->error("❌ Fout bij {$parentFolder->name}: " . $e->getMessage());
            Log::error("Sync error: " . $e->getMessage());
        }
    }
}
}