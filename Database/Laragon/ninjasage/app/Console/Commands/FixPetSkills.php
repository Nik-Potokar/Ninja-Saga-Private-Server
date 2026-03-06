<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pet;

class FixPetSkills extends Command
{
    protected $signature = 'pet:fix-skills';
    protected $description = 'Fixes pet skill unlock levels based on known progressions.';

    public function handle()
    {
        $petCurves = [
            'pet_easa' => [1, 5, 10, 15, 20, 30],
            'pet_keiko' => [1, 5, 10, 15, 20, 25],
            'pet_chiko' => [1, 10, 20, 30, 40, 50],
            // Add other known pets or patterns here.
            'pet_yamaru' => [1, 10, 20, 30, 40, 50],
            'pet_eriko' => [1, 10, 20, 30, 40, 50],
            'pet_yuki' => [1, 10, 20, 30, 40, 50],
            'pet_suu' => [1, 10, 20, 30, 40, 50],
            'pet_inokuchi' => [1, 10, 20, 30, 40, 50],
            'pet_tomaru' => [1, 10, 20, 30, 40, 50],
            'pet_akamaru' => [1, 10, 20, 30, 40, 50],
            'pet_bat' => [1, 10, 20, 30, 40, 50],
            'pet_saru' => [1, 10, 20, 30, 40, 50],
            // Use the standard curve when a pet-specific curve is unknown.
        ];

        $pets = Pet::all();

        foreach ($pets as $pet) {
            $curve = $petCurves[$pet->pet_id] ?? null;
            
            // Apply the standard curve when skills are empty and no override exists.
            if (!$curve && empty($pet->skills)) {
                $curve = [1, 10, 20, 30, 40, 50];
            }

            if ($curve) {
                $skills = $pet->skills ?? [];
                
                // Ensure each slot has a level entry even when skills are missing.
                
                $newSkills = [];
                for ($i = 0; $i < 6; $i++) {
                    $existing = $skills[$i] ?? [];
                    $level = $curve[$i] ?? ($curve[count($curve)-1] + 10);
                    
                    $newSkills[] = array_merge($existing, ['level' => $level]);
                }
                
                $pet->skills = $newSkills;
                $pet->save();
                $this->info("Updated skills for {$pet->name} ({$pet->pet_id})");
            }
        }

        $this->info("Pet skills fixed.");
    }
}
