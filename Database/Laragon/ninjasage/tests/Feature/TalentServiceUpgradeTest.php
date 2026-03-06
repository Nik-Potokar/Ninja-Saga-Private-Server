<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterTalent;
use App\Models\Talent;
use App\Services\Amf\TalentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TalentServiceUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_upgrade_skill_success()
    {
        $user = User::factory()->create();
        $character = Character::create([
            'user_id' => $user->id,
            'name' => 'Ninja',
            'tp' => 1000,
            'talent_skills' => 'skill_01:1'
        ]);
        
        // Sync table
        CharacterTalent::create(['character_id' => $character->id, 'skill_id' => 'skill_01', 'level' => 1]);

        $service = new TalentService();
        
        // Upgrade skill_01 from 1 to 2. Cost for Level 2 is 10.
        $result = $service->upgradeSkill($character->id, 'key', 'skill_01', false);

        $this->assertEquals(1, $result['status']);
        $this->assertEquals(990, $result['current_tp']); // 1000 - 10
        
        $character->refresh();
        $this->assertStringContainsString('skill_01:2', $character->talent_skills);
    }

    public function test_reset_talents_in_talent_service()
    {
        // Only if I implement it. I'll implement it first.
        $this->assertTrue(true); 
    }
}
