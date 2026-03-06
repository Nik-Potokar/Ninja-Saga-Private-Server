<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Character;
use App\Models\Talent;
use App\Services\Amf\TalentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\CharacterTalent;

class TalentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_talent_skills_format()
    {
        $user = User::factory()->create();
        $character = Character::create([
            'user_id' => $user->id, 
            'name' => 'Ninja',
            // 'talent_skills' string is no longer used by getTalentSkills
        ]);
        
        CharacterTalent::create(['character_id' => $character->id, 'skill_id' => 'talent_01_skill_1', 'level' => 5]);
        CharacterTalent::create(['character_id' => $character->id, 'skill_id' => 'talent_02_skill_1', 'level' => 1]);

        $service = new TalentService();
        $result = $service->getTalentSkills($character->id, 'key');

        $this->assertEquals(1, $result['status']);
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(2, $result['data']);
        
        $item = $result['data'][0];
        $this->assertArrayHasKey('item_id', $item);
        $this->assertArrayHasKey('item_level', $item);
        $this->assertArrayHasKey('talent_type', $item);
        
        // Check extraction
        $this->assertEquals('01', $item['talent_type']);
    }

    public function test_discover_talent_success()
    {
        $user = User::factory()->create(['tokens' => 1000]);
        $character = Character::create(['user_id' => $user->id, 'name' => 'Ninja', 'gold' => 10000]);

        Talent::create([
            'talent_id' => 'talent_01',
            'name' => 'Bloodline',
            'price_tokens' => 500,
            'price_gold' => 1000,
            'is_emblem' => false
        ]);

        $service = new TalentService();
        $result = $service->discoverTalent($character->id, 'key', 'Extreme', 'talent_01');

        $this->assertEquals(1, $result['status']);
        $this->assertEquals(1, $result['newt']);
        $this->assertEquals(500, $result['tokens']); // 1000 - 500
        $this->assertEquals(9000, $result['golds']); // 10000 - 1000
        
        $character->refresh();
        $this->assertEquals('talent_01', $character->talent_1);
    }
}
