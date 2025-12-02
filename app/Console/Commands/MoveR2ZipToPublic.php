<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MoveR2ZipToPublic extends Command
{
    protected $signature = 'r2:publish-zip';
    protected $description = 'Move r2_backup.zip from storage/app to public folder';

    public function handle()
    {
        $source = storage_path('app/r2_backup.zip');
        $destination = public_path('r2_backup.zip');

        if (!file_exists($source)) {
            $this->error("❌ ZIP bestaat niet in storage/app");
            return 1;
        }

        if (copy($source, $destination)) {
            $this->info("🎉 ZIP gekopieerd naar /public/r2_backup.zip");
        } else {
            $this->error("❌ Kon ZIP niet kopiëren naar public/");
        }

        return 0;
    }
}
