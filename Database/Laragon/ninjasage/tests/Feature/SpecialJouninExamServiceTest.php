<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Character;
use App\Models\Item;
use App\Services\Amf\SpecialJouninExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SpecialJouninExamServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_data_returns_expected_shape()
    {
        $user = User::factory()->create();
        $character = Character::create([
            'user_id' => $user->id,
            'name' => 'SpecJounin',
            'level' => 60,
            'rank' => 5,
            'special_jounin_exam_progress' => '1,0,0,0,0,0,0,0,0,0,0,0,0',
            'special_jounin_claimed' => 0,
        ]);

        $service = new SpecialJouninExamService();
        $result = $service->getData('key', $character->id);

        $this->assertEquals(1, $result['status']);
        $this->assertEquals(0, $result['error']);
        $this->assertCount(13, $result['data']);
        $this->assertSame(1, $result['data'][0]['status']);
        $this->assertFalse($result['data'][0]['claimed']);
    }

    public function test_start_and_finish_stage_return_expected_shape()
    {
        $user = User::factory()->create();
        $character = Character::create([
            'user_id' => $user->id,
            'name' => 'SpecJounin',
            'level' => 60,
            'rank' => 5,
            'special_jounin_exam_progress' => '1,0,0,0,0,0,0,0,0,0,0,0,0',
            'special_jounin_claimed' => 0,
        ]);

        $service = new SpecialJouninExamService();
        $start = $service->startStage('key', $character->id, 11);

        $this->assertEquals(1, $start['status']);
        $this->assertEquals(0, $start['error']);
        $this->assertCount(13, $start['data']);
        $this->assertEquals('start_stage_6', $start['result']);
        $this->assertSame(64, strlen($start['hash']));

        $finish = $service->finishStage('key', $character->id, 11, []);

        $this->assertEquals(1, $finish['status']);
        $this->assertEquals(0, $finish['error']);
        $this->assertCount(13, $finish['data']);
        $this->assertEquals('Completed', $finish['result']);
        $this->assertSame(2, $finish['data'][0]['status']);
        $this->assertSame(1, $finish['data'][1]['status']);
        $this->assertSame(64, strlen($finish['hash']));
    }

    public function test_promote_to_special_jounin_returns_expected_shape()
    {
        $user = User::factory()->create();
        $character = Character::create([
            'user_id' => $user->id,
            'name' => 'SpecJounin',
            'level' => 60,
            'rank' => 5,
            'special_jounin_exam_progress' => implode(',', array_fill(0, 13, '2')),
            'special_jounin_claimed' => 0,
        ]);

        Item::create(['item_id' => 'set_588_0', 'name' => 'Special Jounin Vest', 'category' => 'set']);

        $service = new SpecialJouninExamService();
        $result = $service->promoteToSpecialJounin('key', $character->id, 'skill_4001');

        $this->assertEquals(1, $result['status']);
        $this->assertEquals(0, $result['error']);
        $this->assertEquals('Promoted', $result['result']);
        $this->assertContains('skill_345', $result['rewards']);
        $this->assertContains('set_588_%s', $result['rewards']);

        $character->refresh();
        $this->assertEquals(7, $character->rank);
        $this->assertEquals('skill_4001', $character->class);
        $this->assertEquals(1, $character->special_jounin_claimed);
    }
}
