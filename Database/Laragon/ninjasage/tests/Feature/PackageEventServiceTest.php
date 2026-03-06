<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Character;
use App\Models\Item;
use App\Models\GameConfig;
use App\Models\CharacterItem;
use App\Models\CharacterSkill;
use App\Services\Amf\PackageEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PackageEventServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_buy_chunin_package_success()
    {
        // 1. Setup Config
        GameConfig::set('chunin_package', [
            'cost' => 100, // Reduced cost for test
            'rewards' => [
                ['type' => 'skill', 'id' => 'skill_test'],
                ['type' => 'item', 'id' => 'item_test'],
            ]
        ]);

        // 2. Create User/Char
        $user = User::factory()->create(['tokens' => 150]);
        $character = Character::create([
            'user_id' => $user->id,
            'name' => 'Ninja',
        ]);

        // 3. Create Item definitions
        Item::create(['item_id' => 'item_test', 'name' => 'Test Item', 'category' => 'weapon']);

        // 4. Call Service
        $service = new PackageEventService();
        $result = $service->buyChuninPackage([$character->id, 'key']);

        // 5. Assertions
        $this->assertEquals(1, $result['status']);
        $this->assertEquals(50, $user->fresh()->tokens); // 150 - 100

        // Check Items
        $this->assertDatabaseHas('character_items', [
            'character_id' => $character->id,
            'item_id' => 'item_test'
        ]);

        // Check Skills
        $this->assertDatabaseHas('character_skills', [
            'character_id' => $character->id,
            'skill_id' => 'skill_test'
        ]);
    }

    public function test_buy_chunin_package_insufficient_tokens()
    {
        GameConfig::set('chunin_package', ['cost' => 100]);

        $user = User::factory()->create(['tokens' => 50]);
        $character = Character::create(['user_id' => $user->id, 'name' => 'Ninja']);

        $service = new PackageEventService();
        $result = $service->buyChuninPackage([$character->id, 'key']);

        $this->assertEquals(2, $result['status']);
        $this->assertEquals(50, $user->fresh()->tokens); // No change
    }

    public function test_buy_chunin_package_direct_call()
    {
        GameConfig::set('chunin_package', [
            'cost' => 100, 
            'rewards' => []
        ]);

        $user = User::factory()->create(['tokens' => 150]);
        $character = Character::create([
            'user_id' => $user->id,
            'name' => 'Ninja',
        ]);

        $service = new PackageEventService();
        // Call with unpacked arguments (direct call simulation)
        $result = $service->buyChuninPackage($character->id, 'key');

        $this->assertEquals(1, $result['status']);
        $this->assertEquals(50, $user->fresh()->tokens);
    }

    public function test_buy_chunin_package_already_owned()
    {
        GameConfig::set('chunin_package', [
            'cost' => 100, 
            'rewards' => [['type' => 'item', 'id' => 'item_test']]
        ]);

        $user = User::factory()->create(['tokens' => 200]);
        $character = Character::create(['user_id' => $user->id, 'name' => 'Ninja']);
        
        // Give item first
        CharacterItem::create([
            'character_id' => $character->id, 
            'item_id' => 'item_test',
            'quantity' => 1
        ]);

        $service = new PackageEventService();
        $result = $service->buyChuninPackage($character->id, 'key');

        $this->assertEquals(0, $result['status']);
        $this->assertEquals('You already purchased this package!', $result['error']);
        $this->assertEquals(200, $user->fresh()->tokens); // No deduction
    }
}
