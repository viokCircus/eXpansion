<?php

namespace ManiaLivePlugins\eXpansion\Chatlog;
use ManiaLivePlugins\Standard\AutoQueue\Config;

/**
 * Get all chat and logs it
 *
 * @author Reaby
 */
class Chatlog extends \ManiaLivePlugins\eXpansion\Core\types\ExpPlugin {

    /**
     * @var \SplDoublyLinkedList
     */
    private $log;

    public function exp_onLoad() {
	$this->enableDedicatedEvents(\ManiaLive\DedicatedApi\Callback\Event::ON_PLAYER_CHAT);
	$this->registerChatCommand("chatlog", "showLog", 0, true);
	$this->log = new \SplDoublyLinkedList();
	$this->log->setIteratorMode(\SplDoublyLinkedList::IT_MODE_LIFO);
    }

    /**
     * When player chat we log it
     *
     * @param $playerUid
     * @param $login
     * @param $text
     * @param $isRegistredCmd
     */
    public function onPlayerChat($playerUid, $login, $text, $isRegistredCmd) {
	if ($playerUid == 0 || substr($text, 0, 1) == "/")
	    return;
	$player = $this->storage->getPlayerObject($login);
	if($player == null)
		return;
	$chatMessage = new Structures\ChatMessage(time(), $login, $player->nickName, $text);
	$this->log->push($chatMessage);

	if($this->log->count() > Config::getInstance()->historyLenght)
        $this->log->shift();
    }

    /**
     * Displays the chat log to the players
     *
     * @param $login
     */
    public function showLog($login) {
	$window = Gui\Windows\ChatlogWindow::Create($login);
	$window->setTitle(__('Chatlog', $login));
	
	$window->setSize(140, 100);
	$window->populateList($this->log);
	$window->centerOnScreen();
	$window->show();
    }

    public function exp_onUnload()
    {
	parent::exp_onUnload();
	Gui\Windows\ChatlogWindow::EraseAll();
    }
}

?>
