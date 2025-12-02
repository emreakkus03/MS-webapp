<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class R2DownloadAll extends Command
{
    protected $signature = 'r2:download-all';
    protected $description = 'Download alle bestanden uit Cloudflare R2 als één ZIP-bestand';

    public function handle()
    {
        $this->info("📦 R2 bestanden verzamelen...");

        $files = Storage::disk('r2')->allFiles();

        if (empty($files)) {
            $this->warn("❌ Geen bestanden gevonden in R2");
            return 0;
        }

        $this->info("➡️ " . count($files) . " bestanden gevonden");

        $zipPath = storage_path('app/r2_backup.zip');

        // Verwijder oude ZIP indien bestaat
        if (file_exists($zipPath)) {
            unlink($zipPath);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
            $this->error("❌ Kan ZIP niet aanmaken");
            return 1;
        }

        foreach ($files as $file) {
            $stream = Storage::disk('r2')->get($file);
            $zip->addFromString(basename($file), $stream);
        }

        $zip->close();

        $this->info("🎉 ZIP aangemaakt: storage/app/r2_backup.zip");

        return 0;
    }
}
