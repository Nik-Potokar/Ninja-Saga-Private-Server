<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Character;
use App\Services\Amf\NinjaTutorExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NinjaTutorExamServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_data_returns_expected_shape()
    {
        $user = User::factory()->create();
        $character = Character::create([
            'user_id' => $user->id,
            'name' => 'Tutor',
            'level' => 80,
            'rank' => 7,
            'ninja_tutor_exam_progress' => '1,0,0,0,0,0,0,0,0,0,0,0',
            'ninja_tutor_claimed' => 0,
        ]);

        $service = new NinjaTutorExamService();
        $result = $service->getData($character->id, 'key');

        $this->assertEquals(1, $result['status']);
        $this->assertEquals(0, $result['error']);
        $this->assertCount(12, $result['data']);
        $this->assertSame(24, $result['data'][0]['id']);
        $this->assertSame(1, $result['data'][0]['status']);
        $this->assertFalse($result['data'][0]['claimed']);
    }

    public function test_start_and_finish_stage_return_expected_shape()
    {
        $user = User::factory()->create();
        $character = Character::create([
            'user_id' => $user->id,
            'name' => 'Tutor',
            'level' => 80,
            'rank' => 7,
            'ninja_tutor_exam_progress' => '1,0,0,0,0,0,0,0,0,0,0,0',
            'ninja_tutor_claimed' => 0,
        ]);

        $service = new NinjaTutorExamService();
        $start = $service->startStage($character->id, 'key', 24);

        $this->assertEquals(1, $start['status']);
        $this->assertEquals(0, $start['error']);
        $this->assertCount(12, $start['data']);
        $this->assertEquals('start_stage_24', $start['result']);
        $this->assertSame(64, strlen($start['hash']));

        $finish = $service->finishStage($character->id, 'key', 25, []);

        $this->assertEquals(1, $finish['status']);
        $this->assertEquals(0, $finish['error']);
        $this->assertCount(12, $finish['data']);
        $this->assertEquals('Completed', $finish['result']);
        $this->assertSame(2, $finish['data'][1]['status']);
        $this->assertSame(1, $finish['data'][2]['status']);
        $this->assertSame(64, strlen($finish['hash']));
    }

    public function test_promote_to_ninja_tutor_returns_expected_shape()
    {
        $user = User::factory()->create();
        $character = Character::create([
            'user_id' => $user->id,
            'name' => 'Tutor',
            'level' => 80,
            'rank' => 7,
            'ninja_tutor_exam_progress' => implode(',', array_fill(0, 12, '2')),
            'ninja_tutor_claimed' => 0,
        ]);

        $service = new NinjaTutorExamService();
        $result = $service->promoteToNinjaTutor($character->id, 'key');

        $this->assertEquals(1, $result['status']);
        $this->assertEquals(0, $result['error']);
        $this->assertEquals('Congratulations! You are now a Senior Ninja Tutor!', $result['result']);
        $this->assertContains('wpn_988', $result['rewards']);
        $this->assertContains('set_942_%s', $result['rewards']);
        $this->assertContains('back_430', $result['rewards']);

        $character->refresh();
        $this->assertEquals(9, $character->rank);
        $this->assertEquals(1, $character->ninja_tutor_claimed);
    }
}
