<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Models\CharacterMail;
use Illuminate\Console\Command;

class SendMail extends Command
{
    protected $signature = 'mail:send
        {character_id : The ID of the character to send the mail to}
        {--title=Test Mail : Mail subject line}
        {--body=This is a test mail from the admin. : Mail body text}
        {--sender=Admin : Display name for the sender}
        {--rewards= : Comma-separated reward strings, e.g. gold_50000,tokens_15}';

    protected $description = 'Send a mail to a character (admin utility)';

    public function handle()
    {
        $charId = (int) $this->argument('character_id');
        $char = Character::find($charId);

        if (!$char) {
            $this->error("Character #$charId not found.");
            return 1;
        }

        $rewardsRaw = $this->option('rewards');
        $rewards = [];
        if ($rewardsRaw) {
            $rewards = array_filter(array_map('trim', explode(',', $rewardsRaw)));
        }

        $mail = CharacterMail::create([
            'character_id' => $charId,
            'title'        => $this->option('title'),
            'sender'       => $this->option('sender'),
            'body'         => $this->option('body'),
            'type'         => 'system',
            'rewards'      => $rewards ?: null,
            'claimed'      => false,
            'viewed'       => false,
        ]);

        $this->info("Mail #{$mail->id} sent to character #{$charId} ({$char->name}).");
        if ($rewards) {
            $this->line("Rewards: " . implode(', ', $rewards));
        }
        return 0;
    }
}
