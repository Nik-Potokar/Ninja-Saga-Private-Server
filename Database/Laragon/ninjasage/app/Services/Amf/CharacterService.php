<?php

namespace App\Services\Amf;

use App\Services\Amf\CharacterService\AccountService;
use App\Services\Amf\CharacterService\DevToolsService;
use App\Services\Amf\CharacterService\EquipmentService;
use App\Services\Amf\CharacterService\InfoService;
use App\Services\Amf\CharacterService\MissionService;
use App\Services\Amf\CharacterService\RecruitService;
use App\Services\Amf\CharacterService\RegistrationService;
use App\Services\Amf\CharacterService\ShopService;
use App\Services\Amf\CharacterService\SkillSetService;
use App\Services\Amf\CharacterService\SpecialClassService;
use App\Services\Amf\CharacterService\TalentService;

class CharacterService
{
    private RegistrationService $registrationService;
    private ShopService $shopService;
    private EquipmentService $equipmentService;
    private SkillSetService $skillSetService;
    private InfoService $infoService;
    private AccountService $accountService;
    private MissionService $missionService;
    private DevToolsService $devToolsService;
    private RecruitService $recruitService;
    private TalentService $talentService;
    private SpecialClassService $specialClassService;

    public function __construct()
    {
        $this->registrationService = new RegistrationService();
        $this->shopService = new ShopService();
        $this->equipmentService = new EquipmentService();
        $this->skillSetService = new SkillSetService();
        $this->infoService = new InfoService();
        $this->accountService = new AccountService();
        $this->missionService = new MissionService();
        $this->devToolsService = new DevToolsService();
        $this->recruitService = new RecruitService();
        $this->talentService = new TalentService();
        $this->specialClassService = new SpecialClassService();
    }

    /**
     * characterRegister
     */
    public function characterRegister($params)
    {
        return $this->registrationService->characterRegister($params);
    }

    /**
     * buySkill
     */
    public function buySkill($sessionKey, $charId, $skillId)
    {
        return $this->shopService->buySkill($sessionKey, $charId, $skillId);
    }

    /**
     * buyItem
     */
    public function buyItem($charId, $sessionKey, $itemId, $quantity)
    {
        return $this->shopService->buyItem($charId, $sessionKey, $itemId, $quantity);
    }

    /**
     * sellItem
     */
    public function sellItem($charId, $sessionKey, $itemId, $quantity)
    {
        return $this->shopService->sellItem($charId, $sessionKey, $itemId, $quantity);
    }

    /**
     * equipSet
     */
    public function equipSet($charId, $sessionKey, $weapon, $backItem, $clothing, $accessory, $hair, $hairColor, $skinColor)
    {
        return $this->equipmentService->equipSet($charId, $sessionKey, $weapon, $backItem, $clothing, $accessory, $hair, $hairColor, $skinColor);
    }

    /**
     * setPoints
     */
    public function setPoints($charId, $sessionKey, $wind, $fire, $lightning, $water, $earth, $free)
    {
        return $this->equipmentService->setPoints($charId, $sessionKey, $wind, $fire, $lightning, $water, $earth, $free);
    }

    /**
     * equipSkillSet
     */
    public function equipSkillSet($charId, $sessionKey, $skillString)
    {
        return $this->equipmentService->equipSkillSet($charId, $sessionKey, $skillString);
    }

    /**
     * getSkillSets
     */
    public function getSkillSets($charId, $sessionKey)
    {
        return $this->skillSetService->getSkillSets($charId, $sessionKey);
    }

    /**
     * saveSkillSet
     */
    public function saveSkillSet($charId, $sessionKey, $presetId, $skills)
    {
        return $this->skillSetService->saveSkillSet($charId, $sessionKey, $presetId, $skills);
    }

    /**
     * createSkillSet
     */
    public function createSkillSet($charId, $sessionKey)
    {
        return $this->skillSetService->createSkillSet($charId, $sessionKey);
    }

    /**
     * getInfo
     */
    public function getInfo($charId, $sessionKey, $targetId, $type = null)
    {
        return $this->infoService->getInfo($charId, $sessionKey, $targetId, $type);
    }

    /**
     * convertTokensToGold
     * Params: [sessionKey, charId, tokenAmount]
     */
    public function convertTokensToGold($sessionKey, $charId, $tokenAmount)
    {
        return $this->accountService->convertTokensToGold($sessionKey, $charId, $tokenAmount);
    }

    /**
     * deleteCharacter
     */
    public function deleteCharacter($charId, $sessionKey, $username, $password)
    {
        return $this->accountService->deleteCharacter($charId, $sessionKey, $username, $password);
    }

    /**
     * rename
     */
    public function rename($charIdOrParams, $sessionKey = null, $newName = null)
    {
        return $this->accountService->renameCharacter($charIdOrParams, $sessionKey, $newName);
    }

    /**
     * getMissionRoomData
     */
    public function getMissionRoomData($charId, $sessionKey)
    {
        return $this->missionService->getMissionRoomData($charId, $sessionKey);
    }

    /**
     * addItems (Developer Tools)
     */
    public function addItems($charIdOrParams, $sessionKey = null, $itemString = null)
    {
        return $this->devToolsService->addItems($charIdOrParams, $sessionKey, $itemString);
    }

    /**
     * setCharInfo (Developer Tools)
     */
    public function setCharInfo($charIdOrParams, $sessionKey = null, $level = null, $rank = null)
    {
        return $this->devToolsService->setCharInfo($charIdOrParams, $sessionKey, $level, $rank);
    }

    /**
     * toggleEmblem (Developer Tools)
     */
    public function toggleEmblem($charIdOrParams, $sessionKey = null)
    {
        return $this->devToolsService->toggleEmblem($charIdOrParams, $sessionKey);
    }

    /**
     * learnSkill (Developer Tools)
     */
    public function learnSkill($charIdOrParams, $sessionKey = null, $skillId = null)
    {
        return $this->devToolsService->learnSkill($charIdOrParams, $sessionKey, $skillId);
    }

    /**
     * recruitTeammate
     */
    public function recruitTeammate($charIdOrParams, $sessionKey = null, $recruitId = null)
    {
        return $this->recruitService->recruitTeammate($charIdOrParams, $sessionKey, $recruitId);
    }

    /**
     * removeRecruitments
     */
    public function removeRecruitments($charIdOrParams, $sessionKey = null)
    {
        return $this->recruitService->removeRecruitments($charIdOrParams, $sessionKey);
    }

    /**
     * buyGanMaterial
     * Params: [sessionKey, charId, quantity]
     */
    public function buyGanMaterial($sessionKey, $charId, $quantity)
    {
        return $this->shopService->buyGanMaterial($sessionKey, $charId, $quantity);
    }

    /**
     * buyAnimation
     */
    public function buyAnimation($charId, $sessionKey, $animationId)
    {
        return $this->shopService->buyAnimation($charId, $sessionKey, $animationId);
    }

    /**
     * useAnimation
     */
    public function useAnimation($charId, $sessionKey, $animationId)
    {
        return $this->shopService->useAnimation($charId, $sessionKey, $animationId);
    }

    /**
     * resetTalents
     */
    public function resetTalents($charIdOrParams, $sessionKey = null, $talents = [])
    {
        return $this->talentService->resetTalents($charIdOrParams, $sessionKey, $talents);
    }

    /**
     * buyTalentEssential
     * Params: [sessionKey, charId, quantity]
     */
    public function buyTalentEssential($sessionKey, $charId, $quantity)
    {
        return $this->shopService->buyTalentEssential($sessionKey, $charId, $quantity);
    }

    /**
     * buyRenameBadge
     * Params: [sessionKey, charId, quantity]
     */
    public function buyRenameBadge($sessionKey, $charId, $quantity)
    {
        return $this->shopService->buyRenameBadge($sessionKey, $charId, $quantity);
    }

    /**
     * useItem
     * Params: [charId, sessionKey, itemId]
     */
    public function useItem($charId, $sessionKey = null, $itemId = null)
    {
        return $this->shopService->useItem($charId, $sessionKey, $itemId);
    }

    /**
     * useBattleItem
     * Params: [sessionKey, charId, itemId]
     */
    public function useBattleItem($sessionKey, $charId = null, $itemId = null)
    {
        return $this->shopService->useBattleItem($sessionKey, $charId, $itemId);
    }

    /**
     * changeSpecialClass
     * Params: [charId, sessionKey, classSkillId]
     */
    public function changeSpecialClass($charId, $sessionKey, $classSkillId)
    {
        return $this->specialClassService->changeSpecialClass($charId, $sessionKey, $classSkillId);
    }
}