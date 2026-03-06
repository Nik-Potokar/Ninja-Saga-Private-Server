<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Character;
use App\Models\Item;
use App\Services\Amf\JouninExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class JouninExamTest extends TestCase
{
    use RefreshDatabase;

    public function test_promote_to_jounin_success()
    {
        // 1. Setup
        $user = User::factory()->create();
        $character = Character::create([
            'user_id' => $user->id,
            'name' => 'ChuninNinja',
            'level' => 40,
            'rank' => 3, // Chunin
            'jounin_exam_progress' => '2,2,2,2,2', // All completed
            'jounin_claimed' => 0
        ]);

        // Create reward item
        Item::create(['item_id' => 'set_18_0', 'name' => 'Jounin Jacket', 'category' => 'set']);

        // 2. Call Service
        $service = new JouninExamService();
        $result = $service->promoteToJounin('key', $character->id);

        // 3. Assertions
        $this->assertEquals(1, $result['status']);
        
        $character->refresh();
        $this->assertEquals(5, $character->rank); // Jounin
        $this->assertEquals(1, $character->jounin_claimed);
        
        // Check Item
        $this->assertDatabaseHas('character_items', [
            'character_id' => $character->id,
            'item_id' => 'set_18_0'
        ]);
    }

    public function test_promote_to_jounin_incomplete()
    {
        $user = User::factory()->create();
        $character = Character::create([
            'user_id' => $user->id,
            'name' => 'LazyNinja',
            'level' => 40,
            'rank' => 3,
            'jounin_exam_progress' => '2,2,2,1,0', // Incomplete
        ]);

        $service = new JouninExamService();
        $result = $service->promoteToJounin('key', $character->id);

        $this->assertEquals(2, $result['status']);
        $this->assertEquals(3, $character->fresh()->rank);
    }
}
