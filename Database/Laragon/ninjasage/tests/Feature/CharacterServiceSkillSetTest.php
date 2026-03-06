<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Character;
use App\Models\CharacterSkillSet;
use App\Services\Amf\CharacterService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CharacterServiceSkillSetTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_skill_sets_no_auto_creation()
    {
        $user = User::factory()->create();
        $character = Character::create(['user_id' => $user->id, 'name' => 'Ninja']);

        $service = new CharacterService();
        $result = $service->getSkillSets($character->id, 'key');

        $this->assertEquals(1, $result['status']);
        $this->assertCount(0, $result['skillsets']);
    }

    public function test_create_skill_set_free_user_fail()
    {
        $user = User::factory()->create(['account_type' => 0]); // Free
        $character = Character::create(['user_id' => $user->id, 'name' => 'Ninja']);

        $service = new CharacterService();
        $result = $service->createSkillSet($character->id, 'key');

        $this->assertEquals(2, $result['status']);
        $this->assertEquals('Premium required to unlock Skill Sets!', $result['result']);
    }

    public function test_create_skill_set_premium_pricing()
    {
        $user = User::factory()->create(['account_type' => 1, 'tokens' => 1000]); // Premium
        $character = Character::create(['user_id' => $user->id, 'name' => 'Ninja', 'equipment_skills' => 's1,s2']);

        $service = new CharacterService();

        // 1st Preset: Cost 0
        $result = $service->createSkillSet($character->id, 'key');
        $this->assertEquals(1, $result['status']);
        $this->assertCount(1, $result['skillsets']);
        $this->assertEquals(1000, $user->fresh()->tokens); // No change

        // 2nd Preset: Cost 100
        $result = $service->createSkillSet($character->id, 'key');
        $this->assertEquals(1, $result['status']);
        $this->assertCount(2, $result['skillsets']);
        $this->assertEquals(900, $user->fresh()->tokens); // 1000 - 100

        // 3rd Preset: Cost 200
        $result = $service->createSkillSet($character->id, 'key');
        $this->assertEquals(1, $result['status']);
        $this->assertCount(3, $result['skillsets']);
        $this->assertEquals(700, $user->fresh()->tokens); // 900 - 200

        // 4th Preset: Cost 300
        $result = $service->createSkillSet($character->id, 'key');
        $this->assertEquals(1, $result['status']);
        $this->assertCount(4, $result['skillsets']);
        $this->assertEquals(400, $user->fresh()->tokens); // 700 - 300

        // 5th Preset: Fail (Max)
        $result = $service->createSkillSet($character->id, 'key');
        $this->assertEquals(2, $result['status']);
        $this->assertEquals('Maximum 4 presets allowed!', $result['result']);
    }
}
