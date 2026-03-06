<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pet;

class FixPetPrices extends Command
{
    protected $signature = 'pet:fix-prices';
    protected $description = 'Fixes pet prices and premium status.';

    public function handle()
    {
        // Format: [gold, tokens, premium]
        $prices = [
            'pet_easa' => [100000, 0, true],
            'pet_keiko' => [0, 2000, false],
            'pet_chiko' => [0, 2000, false],
            'pet_leiko' => [0, 2000, false],
            'pet_eriko' => [0, 2000, false],
            'pet_yamaru' => [0, 2000, false],
            'pet_suu' => [0, 2000, false],
            'pet_inokuchi' => [50000, 0, false],
            'pet_tomaru' => [0, 2000, false],
            'pet_akamaru' => [0, 2000, false],
            'pet_bat' => [50000, 0, false],
            'pet_saru' => [50000, 0, false],
            'pet_jyubi' => [0, 5000, true],
            'pet_kyubi' => [0, 5000, true],
            'pet_hachibi' => [0, 5000, true],
            'pet_nanabi' => [0, 5000, true],
            'pet_rokubi' => [0, 5000, true],
            'pet_gobi' => [0, 5000, true],
            'pet_sanbi' => [0, 5000, true],
            'pet_nibi' => [0, 5000, true],
            'pet_ichibi' => [0, 5000, true],
            // Add more as needed.
        ];

        $pets = Pet::all();

        foreach ($pets as $pet) {
            if (isset($prices[$pet->pet_id])) {
                $p = $prices[$pet->pet_id];
                $pet->price_gold = $p[0];
                $pet->price_tokens = $p[1];
                $pet->premium = $p[2];
                $pet->save();
                $this->info("Updated price for {$pet->name}: Gold={$p[0]}, Tokens={$p[1]}, Premium=" . ($p[2] ? 'Yes' : 'No'));
            } else {
                if ($pet->price_gold == 0 && $pet->price_tokens == 0) {
                    // Default fallback when no price is set.
                    $pet->price_gold = 100000;
                    $pet->save();
                    $this->info("Set default gold price for {$pet->name}");
                }
            }
        }

        $this->info("Pet prices fixed.");
    }
}
