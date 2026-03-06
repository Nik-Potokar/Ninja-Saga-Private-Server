<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Character;
use App\Models\Skill;
use App\Models\CharacterSkill;
use App\Services\Amf\CharacterService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CharacterServiceElementTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_user_element_limit()
    {
        // 1. Setup User (Free)
        $user = User::factory()->create(['account_type' => 0, 'tokens' => 1000]);
        $character = Character::create([
            'user_id' => $user->id,
            'name' => 'FreeNinja',
            'element_1' => 1, // Wind
            'gold' => 10000,
            'level' => 10
        ]);

        // 2. Setup Skills
        Skill::create(['skill_id' => 'skill_wind', 'name' => 'Wind Blade', 'element' => 1, 'level' => 1]);
        Skill::create(['skill_id' => 'skill_fire', 'name' => 'Fireball', 'element' => 2, 'level' => 1]);
        Skill::create(['skill_id' => 'skill_lightning', 'name' => 'Thunder', 'element' => 3, 'level' => 1]);

        $service = new CharacterService();

        // 3. Buy Fire (2nd Element) - Should succeed
        $result = $service->buySkill('key', $character->id, 'skill_fire');
        $this->assertEquals(1, $result['status']);
        $character->refresh();
        $this->assertEquals(2, $character->element_2);

        // 4. Buy Lightning (3rd Element) - Should FAIL for Free user
        $result = $service->buySkill('key', $character->id, 'skill_lightning');
        $this->assertEquals(4, $result['status']);
        $character->refresh();
        $this->assertEquals(0, $character->element_3);
        $this->assertDatabaseMissing('character_skills', [
            'character_id' => $character->id,
            'skill_id' => 'skill_lightning'
        ]);
    }

    public function test_premium_user_element_limit()
    {
        // 1. Setup User (Premium)
        $user = User::factory()->create(['account_type' => 1, 'tokens' => 1000]);
        $character = Character::create([
            'user_id' => $user->id,
            'name' => 'PremNinja',
            'element_1' => 1, // Wind
            'gold' => 10000,
            'level' => 10
        ]);

        Skill::create(['skill_id' => 'skill_fire', 'name' => 'Fireball', 'element' => 2, 'level' => 1]);
        Skill::create(['skill_id' => 'skill_lightning', 'name' => 'Thunder', 'element' => 3, 'level' => 1]);
        Skill::create(['skill_id' => 'skill_water', 'name' => 'Water Dragon', 'element' => 4, 'level' => 1]);

        $service = new CharacterService();

        // 2. Buy Fire (2nd) - Success
        $service->buySkill('key', $character->id, 'skill_fire');
        $character->refresh();
        $this->assertEquals(2, $character->element_2);

        // 3. Buy Lightning (3rd) - Success
        $service->buySkill('key', $character->id, 'skill_lightning');
        $character->refresh();
        $this->assertEquals(3, $character->element_3);

        // 4. Buy Water (4th) - Fail
        $result = $service->buySkill('key', $character->id, 'skill_water');
        $this->assertEquals(4, $result['status']);
        $this->assertDatabaseMissing('character_skills', [
            'character_id' => $character->id,
            'skill_id' => 'skill_water'
        ]);
    }
}
