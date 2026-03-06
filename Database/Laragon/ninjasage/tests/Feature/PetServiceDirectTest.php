<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Character;
use App\Models\Pet;
use App\Models\CharacterPet;
use App\Models\CharacterItem;
use App\Services\Amf\PetService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PetServiceDirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_rename_pet()
    {
        // 1. Create User
        $user = User::factory()->create(['account_type' => 0]); // Free user

        // 2. Create Character
        $character = Character::create([
            'user_id' => $user->id,
            'name' => 'Ninja',
            'gender' => 0,
            'hair_style' => 1,
            'hair_color' => 'black',
            'skin_color' => 'light',
            'level' => 1,
            'rank' => 1,
            'gold' => 1000,
        ]);

        // 3. Create Pet Config
        $petConfig = Pet::create([
            'pet_id' => 'P001',
            'name' => 'Dog',
            'swf' => 'pet_dog.swf',
            'price_gold' => 100,
            'premium' => false,
        ]);

        // 4. Create Character Pet
        $characterPet = CharacterPet::create([
            'character_id' => $character->id,
            'pet_id' => 'P001',
            'level' => 1,
            'name' => 'OriginalName'
        ]);

        // 5. Add Rename Badges (3 required for free user)
        CharacterItem::create([
            'character_id' => $character->id,
            'item_id' => 'essential_01',
            'quantity' => 3,
            'category' => 'essential'
        ]);

        // 6. Call Service
        $service = new PetService();
        // params: [charId, sessionKey, petId, newName]
        $params = [$character->id, 'session_key_dummy', $characterPet->id, 'NewName'];
        
        $result = $service->renamePet($params);

        // 7. Assertions
        $this->assertEquals(1, $result['status']);
        
        $characterPet->refresh();
        $this->assertEquals('NewName', $characterPet->name);
        
        // Verify item deduction
        $this->assertDatabaseMissing('character_items', [
            'character_id' => $character->id,
            'item_id' => 'essential_01',
        ]); // Should be deleted as qty becomes 0
    }
}
