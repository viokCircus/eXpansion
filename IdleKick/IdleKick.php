<?php

namespace ManiaLivePlugins\eXpansion\IdleKick;

use ManiaLivePlugins\eXpansion\IdleKick\Config;
use ManiaLivePlugins\eXpansion\AdminGroups\AdminGroups;

class IdleKick extends \ManiaLivePlugins\eXpansion\Core\types\ExpPlugin {

    private $timeStamps = array();
    private $tickCounter = 0;

    /** @var Config */
    private $config;

    function exp_onReady() {
        $this->enableDedicatedEvents();
        $this->enableTickerEvent();
        foreach ($this->storage->players as $player)
            $this->onPlayerConnect($player->login, false);
    }

    function onPlayerConnect($login, $isSpectator) {
        $this->checkActivity($login);
    }

    public function onPlayerDisconnect($login, $reason = null) {
        if (array_key_exists($login, $this->timeStamps))
            unset($this->timeStamps[$login]);
    }

    public function onPlayerCheckpoint($playerUid, $login, $timeOrScore, $curLap, $checkpointIndex) {
        $this->checkActivity($login);
    }

    function onTick() {
        if ($this->tickCounter % 10 == 0) {
            $this->tickCounter = 0;
            $this->config = Config::getInstance();
            foreach ($this->timeStamps as $playerLogin => $value) {
                if ((time() - $value) > ($this->config->idleMinutes * 60)) {

                    $player = $this->storage->getPlayerObject($playerLogin);

                    if ($this->config->idleKickReally) {
                        $this->exp_chatSendServerMessage('%s $z$s$fff is idle and is being idle kicked!', null, array($player->nickName));
                        $this->connection->kick($playerLogin, "Idle Kick");
                    } else {
                        $this->exp_chatSendServerMessage('%s $z$s$fff is idle and is being sent to spectate!', null, array($player->nickName));
                        $this->connection->forceSpectator($playerLogin, 3);
                        unset($this->timeStamps[$playerLogin]);
                    }
                }
            }
        }
        $this->tickCounter++;
    }

    function checkActivity($login) {
        if ($login != $this->storage->serverLogin) {
            $this->timeStamps[$login] = time();
        }
    }

    function onPlayerChat($playerUid, $login, $text, $isRegistredCmd) {
        if ($playerUid != 0) {
            $player = $this->storage->getPlayerObject($login);
            if (!$player->spectator) {
                $this->checkActivity($login);
            }
        }
    }

    function onPlayerInfoChanged($playerInfo) {
        $player = \Maniaplanet\DedicatedServer\Structures\PlayerInfo::fromArray($playerInfo);
        $login = $player->login;
        
        if ($player->spectator) {
            if (array_key_exists($login, $this->timeStamps))
                unset($this->timeStamps[$login]);
        } else {
            $this->checkActivity($login);
        }
    }

}

?>