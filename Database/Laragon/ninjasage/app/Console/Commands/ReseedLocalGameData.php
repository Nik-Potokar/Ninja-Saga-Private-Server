<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ReseedLocalGameData extends Command
{
    protected $signature = 'game:reseed-local';

    protected $description = 'Decompresses all .bin files in public/game_data/ back into .json files (useful after a reset or corruption).';

    public function handle(): int
    {
        $gameDataDir = public_path('game_data');

        $bins = glob($gameDataDir . '/*.bin') ?: [];

        if (empty($bins)) {
            $this->error('No .bin files found in ' . $gameDataDir);
            return 1;
        }

        foreach ($bins as $binPath) {
            $name = pathinfo($binPath, PATHINFO_FILENAME); // e.g. "gamedata"

            $this->output->write("Processing <comment>{$name}</comment> ... ");

            $compressed = @file_get_contents($binPath);
            if ($compressed === false) {
                $this->error("Cannot read {$binPath}");
                continue;
            }

            $json = @gzuncompress($compressed);
            if ($json === false) {
                $json = @gzdecode($compressed);
            }

            if ($json === false) {
                $this->warn("Could not decompress — skipped.");
                continue;
            }

            file_put_contents("{$gameDataDir}/{$name}.json", $json);

            $this->info("OK → public/game_data/{$name}.json");
        }

        $this->newLine();
        $this->info('Done. All files reseeded from local originals.');
        return 0;
    }
}