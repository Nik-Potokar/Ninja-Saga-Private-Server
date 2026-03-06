<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class DumpGameData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'game:dump-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Downloads, decompresses, and saves Ninja Sage game data as JSON files, then seeds the database.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $assets = [
            "library",
            "enemy",
            "npc",
            "pet",
            "mission",
            "skills",
            "gamedata",
            "talents",
            "senjutsu",
            "skill-effect",
            "weapon-effect",
            "back_item-effect",
            "accessory-effect",
            "arena-effect",
            "animation",
        ];

        $baseUrl = "https://ns-assets.ninjasage.id/static/lib/";
        $jsonOutputDir = "game_data";
        $binOutputDir = public_path("static/lib");

        $this->info("Starting Ninja Sage Game Data Dump...");

        // Ensure directories exist
        if (!File::exists($binOutputDir)) {
            File::makeDirectory($binOutputDir, 0755, true);
        }

        // Ensure private game_data directory exists in storage/app/private
        if (!Storage::disk('local')->exists($jsonOutputDir)) {
            Storage::disk('local')->makeDirectory($jsonOutputDir);
        }

        foreach ($assets as $asset) {
            $url = $baseUrl . $asset . ".bin";
            $this->output->write("Processing: <comment>{$asset}</comment> ... ");

            try {
                $response = Http::get($url);

                if (!$response->successful()) {
                    $this->error("FAILED (HTTP " . $response->status() . ")");
                    continue;
                }

                $compressedData = $response->body();

                // 1. Save raw .bin file to public folder
                $binFileName = "{$binOutputDir}/{$asset}.bin";
                File::put($binFileName, $compressedData);

                // Try Zlib decompress (ActionScript Zlib standard)
                $jsonData = @gzuncompress($compressedData);

                if ($jsonData === false) {
                    // Fallback to Gzip decode
                    $jsonData = @gzdecode($compressedData);
                }

                if ($jsonData === false) {
                    $this->error("FAILED TO DECOMPRESS");
                    continue;
                }

                // 2. Save extracted JSON to private storage (storage/app/private/game_data)
                $jsonFileName = "{$jsonOutputDir}/{$asset}.json";
                Storage::disk('local')->put($jsonFileName, $jsonData);

                $this->info("SUCCESS");
                $this->line("   - BIN: public/static/lib/{$asset}.bin");
                $this->line("   - JSON: storage/app/private/{$jsonFileName}");

            } catch (\Exception $e) {
                $this->error("ERROR: " . $e->getMessage());
                Log::error("Game Data Dump Error ($asset): " . $e->getMessage());
            }
        }

        $this->info("\nFiles dumped successfully. Now updating database...");

        try {
            Artisan::call('db:seed', [
                '--class' => 'DatabaseSeeder',
                '--force' => true
            ]);
            $this->info("Database updated successfully via DatabaseSeeder.");
        } catch (\Exception $e) {
            $this->error("Failed to update database: " . $e->getMessage());
        }

        $this->info("\nDone! All available game data has been dumped and imported.");
        return 0;
    }
}
