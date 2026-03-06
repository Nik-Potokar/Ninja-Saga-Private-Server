<?php

namespace App\Services\Amf\MailService;

use App\Models\Character;
use App\Models\CharacterMail;
use App\Models\CharacterSkill;
use App\Models\Clan;
use App\Models\ClanMember;
use App\Services\Amf\Concerns\ValidatesSession;
use App\Services\Amf\RewardGrantService;
use Illuminate\Support\Facades\Log;

class ExecuteService
{
    use ValidatesSession;

    public function executeService($subService, $params)
    {
        Log::info("AMF MailService.executeService: SubService $subService");

        if (method_exists($this, $subService)) {
            return $this->$subService($params);
        }

        Log::error("MailService: SubService $subService not implemented.");
        return ['status' => 0, 'error' => "SubService $subService not found"];
    }

    // -------------------------------------------------------------------------
    // getMails([charId, sessionKey])
    // -------------------------------------------------------------------------
    private function getMails($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) return $guard;

        $charId = (int) $params[0];
        Log::info("AMF MailService.getMails: Char $charId");

        $mails = CharacterMail::where('character_id', $charId)
            ->orderByDesc('created_at')
            ->get();

        $list = $mails->map(fn ($m) => $this->formatMail($m))->values()->all();

        return ['status' => 1, 'mails' => $list];
    }

    // -------------------------------------------------------------------------
    // openMail([charId, sessionKey, mail_id])
    // -------------------------------------------------------------------------
    private function openMail($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) return $guard;

        $charId = (int) $params[0];
        $mailId = (int) ($params[2] ?? 0);

        $mail = CharacterMail::where('id', $mailId)
            ->where('character_id', $charId)
            ->first();

        if (!$mail) {
            return ['status' => 0, 'error' => 'Mail not found'];
        }

        if (!$mail->viewed) {
            $mail->viewed = true;
            $mail->save();
        }

        return ['status' => 1, 'mail' => $this->formatMail($mail)];
    }

    // -------------------------------------------------------------------------
    // deleteMail([charId, sessionKey, mail_id])
    // -------------------------------------------------------------------------
    private function deleteMail($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) return $guard;

        $charId = (int) $params[0];
        $mailId = (int) ($params[2] ?? 0);

        CharacterMail::where('id', $mailId)
            ->where('character_id', $charId)
            ->delete();

        return ['status' => 1];
    }

    // -------------------------------------------------------------------------
    // deleteAllMails([charId, sessionKey])
    // -------------------------------------------------------------------------
    private function deleteAllMails($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) return $guard;

        $charId = (int) $params[0];

        CharacterMail::where('character_id', $charId)->delete();

        return ['status' => 1];
    }

    // -------------------------------------------------------------------------
    // claimReward([charId, sessionKey, mail_id])  – Flash calls singular form
    // -------------------------------------------------------------------------
    private function claimReward($params)
    {
        return $this->claimRewards($params);
    }

    // -------------------------------------------------------------------------
    // claimRewards([charId, sessionKey, mail_id])
    // -------------------------------------------------------------------------
    private function claimRewards($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) return $guard;

        $charId = (int) $params[0];
        $mailId = (int) ($params[2] ?? 0);

        $mail = CharacterMail::where('id', $mailId)
            ->where('character_id', $charId)
            ->first();

        if (!$mail) {
            return ['status' => 0, 'error' => 'Mail not found'];
        }

        if ($mail->claimed) {
            return ['status' => 2, 'error' => 'Rewards already claimed'];
        }

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        $rewards = $mail->rewards ?? [];
        $grantService = new RewardGrantService();
        foreach ($rewards as $rewardStr) {
            if (is_string($rewardStr) && $rewardStr !== '') {
                $grantService->grant($char, $rewardStr);
            }
        }

        $mail->claimed = true;
        $mail->viewed  = true;
        $mail->save();

        return [
            'status'      => 1,
            'rewards'     => $rewards,
            'char_skills' => $this->getSkillsString($charId),
        ];
    }

    // -------------------------------------------------------------------------
    // claimAllRewards([charId, sessionKey])
    // -------------------------------------------------------------------------
    private function claimAllRewards($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) return $guard;

        $charId = (int) $params[0];

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        $mails = CharacterMail::where('character_id', $charId)
            ->where('claimed', false)
            ->whereNotNull('rewards')
            ->get();

        $grantService   = new RewardGrantService();
        $allRewards     = [];
        foreach ($mails as $mail) {
            foreach ($mail->rewards ?? [] as $rewardStr) {
                if (is_string($rewardStr) && $rewardStr !== '') {
                    $grantService->grant($char, $rewardStr);
                    $allRewards[] = $rewardStr;
                }
            }
            $mail->claimed = true;
            $mail->viewed  = true;
            $mail->save();
        }

        return [
            'status'      => 1,
            'rewards'     => $allRewards,
            'result'      => count($allRewards) > 0 ? 'Rewards claimed!' : 'No rewards to claim.',
            'char_skills' => $this->getSkillsString($charId),
        ];
    }

    // -------------------------------------------------------------------------
    // acceptInvitation([charId, sessionKey, mail_id])
    // Called when a player clicks Accept on a clan invite mail (mail_type 3/6)
    // -------------------------------------------------------------------------
    private function acceptInvitation($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) return $guard;

        $charId = (int) $params[0];
        $mailId = (int) ($params[2] ?? 0);

        $mail = CharacterMail::where('id', $mailId)
            ->where('character_id', $charId)
            ->where('type', 'clan_invite')
            ->first();

        if (!$mail) {
            return ['status' => 0, 'error' => 'Invitation not found'];
        }

        $data   = json_decode($mail->body, true);
        $clanId = $data['clan_id'] ?? null;

        if (!$clanId) {
            return ['status' => 0, 'error' => 'Invalid invitation data'];
        }

        if (ClanMember::where('character_id', $charId)->exists()) {
            return ['status' => 2, 'result' => 'You are already in a clan'];
        }

        $clan = Clan::find($clanId);
        if (!$clan) {
            return ['status' => 0, 'error' => 'Clan no longer exists'];
        }

        if (ClanMember::where('clan_id', $clanId)->count() >= $clan->max_members) {
            return ['status' => 2, 'result' => 'Clan is full'];
        }

        ClanMember::create([
            'clan_id'      => $clanId,
            'character_id' => $charId,
            'role'         => 'member',
            'stamina'      => 5,
            'max_stamina'  => 5,
        ]);

        $mail->update(['viewed' => true]);

        return ['status' => 1, 'result' => 'You have joined ' . $clan->name . '!'];
    }

    // -------------------------------------------------------------------------
    // acceptFriendRequest([charId, sessionKey, mail_id])
    // Called when a player accepts a friend request mail (mail_type 2)
    // -------------------------------------------------------------------------
    private function acceptFriendRequest($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) return $guard;

        $charId = (int) $params[0];
        $mailId = (int) ($params[2] ?? 0);

        $mail = CharacterMail::where('id', $mailId)
            ->where('character_id', $charId)
            ->first();

        if ($mail) {
            $mail->update(['viewed' => true]);
        }

        // Friend system not yet implemented; acknowledge gracefully
        return ['status' => 1, 'result' => 'Friend request accepted!'];
    }

    private function getSkillsString(int $charId): string
    {
        $skills = CharacterSkill::where('character_id', $charId)->pluck('skill_id')->toArray();
        return implode(',', $skills);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------
    private function formatMail(CharacterMail $mail): array
    {
        $rewards = $mail->rewards ?? [];

        // Flash displayRewardIcons() reads mail_rewards as a comma-separated
        // string and splits on ",". Each element is "rewardId" or "rewardId:qty".
        $rewardsStr = implode(',', $rewards);

        // Flash openMailInfo() switches on integer mail_type:
        //   2 = friend invite   (Accept button → acceptFriendRequest)
        //   3 = clan invite     (Accept button → acceptClanInvitation)
        //   4 = reward display, no claim button (already claimed)
        //   5 = reward display + claim button (unclaimed)
        //   1 = plain mail, no action holder
        switch ($mail->type) {
            case 'friend_invite':
                $flashType = 2;
                break;
            case 'clan_invite':
                $flashType = 3;
                break;
            default:
                $flashType = !empty($rewards) ? ($mail->claimed ? 4 : 5) : 1;
                break;
        }

        return [
            'mail_id'      => $mail->id,
            'mail_title'   => $mail->title,
            'mail_sender'  => $mail->sender,
            'mail_body'    => $mail->body ?? '',
            'mail_type'    => $flashType,
            'mail_rewards' => $rewardsStr,
            'mail_claimed' => (int) $mail->claimed,
            'mail_viewed'  => (int) $mail->viewed,
            'sent_date'    => $mail->created_at ? $mail->created_at->toDateTimeString() : '',
        ];
    }
}