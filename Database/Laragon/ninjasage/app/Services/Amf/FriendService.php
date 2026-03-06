<?php

namespace App\Services\Amf;

use App\Services\Amf\FriendService\BattleService;
use App\Services\Amf\FriendService\FavoriteService;
use App\Services\Amf\FriendService\ListService;
use App\Services\Amf\FriendService\RecruitService;
use App\Services\Amf\FriendService\RemovalService;
use App\Services\Amf\FriendService\RequestsService;
use App\Services\Amf\FriendService\ShopService;

class FriendService
{
    private RequestsService $requestsService;
    private ListService $listService;
    private FavoriteService $favoriteService;
    private RemovalService $removalService;
    private RecruitService $recruitService;
    private BattleService $battleService;
    private ShopService $shopService;

    public function __construct()
    {
        $this->requestsService = new RequestsService();
        $this->listService = new ListService();
        $this->favoriteService = new FavoriteService();
        $this->removalService = new RemovalService();
        $this->recruitService = new RecruitService();
        $this->battleService = new BattleService();
        $this->shopService = new ShopService();
    }

    public function addFriend($charId, $sessionKey, $friendId)
    {
        return $this->requestsService->addFriend($charId, $sessionKey, $friendId);
    }

    public function friendRequests($charId, $sessionKey, $page = 1)
    {
        return $this->requestsService->friendRequests($charId, $sessionKey, $page);
    }

    public function acceptFriend($charId, $sessionKey, $friendId)
    {
        return $this->requestsService->acceptFriend($charId, $sessionKey, $friendId);
    }

    public function acceptAll($charId, $sessionKey)
    {
        return $this->requestsService->acceptAll($charId, $sessionKey);
    }

    public function removeAll($charId, $sessionKey)
    {
        return $this->requestsService->removeAll($charId, $sessionKey);
    }

    public function friends($charId, $sessionKey, $page = 1)
    {
        return $this->listService->friends($charId, $sessionKey, $page);
    }

    public function getFavorite($charId, $sessionKey, $page = 1)
    {
        return $this->listService->getFavorite($charId, $sessionKey, $page);
    }

    public function search($charId, $sessionKey, $query)
    {
        return $this->listService->search($charId, $sessionKey, $query);
    }

    public function getRecommendations($charId, $sessionKey, $page = 1)
    {
        return $this->listService->getRecommendations($charId, $sessionKey, $page);
    }

    public function setFavorite($charId, $sessionKey, $friendId)
    {
        return $this->favoriteService->setFavorite($charId, $sessionKey, $friendId);
    }

    public function removeFavorite($charId, $sessionKey, $friendId)
    {
        return $this->favoriteService->removeFavorite($charId, $sessionKey, $friendId);
    }

    public function removeFriend($charId, $sessionKey, $friendIdOrIds)
    {
        return $this->removalService->removeFriend($charId, $sessionKey, $friendIdOrIds);
    }

    public function unfriendAll($charId, $sessionKey)
    {
        return $this->removalService->unfriendAll($charId, $sessionKey);
    }

    public function recruitable($charId, $sessionKey)
    {
        return $this->recruitService->recruitable($charId, $sessionKey);
    }

    public function recruitFriend($charId, $sessionKey, $friendId)
    {
        return $this->recruitService->recruitFriend($charId, $sessionKey, $friendId);
    }

    public function startBerantem($charId, $friendId, $hash, $sessionKey)
    {
        return $this->battleService->startBerantem($charId, $friendId, $hash, $sessionKey);
    }

    public function endBerantem($charId, $battleCode, $hash, $sessionKey, $battleData)
    {
        return $this->battleService->endBerantem($charId, $battleCode, $hash, $sessionKey, $battleData);
    }

    public function getItems($charId, $sessionKey)
    {
        return $this->shopService->getItems($charId, $sessionKey);
    }

    public function buyItem($charId, $sessionKey, $exchangeId)
    {
        return $this->shopService->buyItem($charId, $sessionKey, $exchangeId);
    }
}
