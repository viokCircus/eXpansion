<?php

namespace ManiaLivePlugins\eXpansion\Adm\Gui\Windows;

use Exception;
use ManiaLib\Gui\Elements\Bgs1;
use ManiaLib\Gui\Elements\Icons64x64_1;
use ManiaLib\Gui\Elements\Quad;
use ManiaLib\Gui\Layouts\Column;
use ManiaLib\Gui\Layouts\Line;
use ManiaLive\Data\Storage;
use ManiaLive\DedicatedApi\Config;
use ManiaLive\Gui\Controls\Frame;
use ManiaLivePlugins\eXpansion\AdminGroups\AdminGroups;
use ManiaLivePlugins\eXpansion\AdminGroups\Permission;
use ManiaLivePlugins\eXpansion\Gui\Elements\Button as OkButton;
use ManiaLivePlugins\eXpansion\Gui\Elements\CheckboxScripted as Checkbox;
use ManiaLivePlugins\eXpansion\Gui\Elements\Editbox;
use ManiaLivePlugins\eXpansion\Gui\Elements\Inputbox;
use ManiaLivePlugins\eXpansion\Gui\Elements\InputboxMasked;
use ManiaLivePlugins\eXpansion\Gui\Elements\Ratiobutton;
use ManiaLivePlugins\eXpansion\Gui\Windows\Window;
use Maniaplanet\DedicatedServer\Connection;
use Maniaplanet\DedicatedServer\Structures\ServerOptions as Dedicated_ServerOptions;

class ServerOptions extends Window
{

	private $serverName, $serverComment, $maxPlayers, $maxSpec, $minLadder, $maxLadder, $serverPass, $serverSpecPass, $refereePass;

	private $cbPublicServer, $cbLadderServer, $cbAllowMapDl, $cbAllowp2pDown, $cbAllowp2pUp, $cbReferee;

	private $frameCb;

	private $frameInputbox, $frameLadder;

	private $buttonOK, $buttonCancel;

	/** @var Connection */
	private $connection;

	private $actionOK, $actionCancel;

	private $e = array();

	function onConstruct()
	{
		parent::onConstruct();
		$config = Config::getInstance();
		$this->connection = Connection::factory($config->host, $config->port);
		$this->actionOK = $this->createAction(array($this, "serverOptionsOk"));
		$this->actionCancel = $this->createAction(array($this, "serverOptionsCancel"));

		$this->setTitle(__('Server Options', $this->getRecipient()));

		$this->inputboxes();
		$this->checkboxes();

		$this->addComponent($this->frameCb);
		$this->addComponent($this->frameInputbox);
	}

	// Generate all inputboxes
	private function inputboxes()
	{

		/** @var ServerOptions */
		$server = $this->connection->getServerOptions();

		$this->frameInputbox = new Frame();
		$this->frameInputbox->setAlign("left", "top");
		$column = new Column();
		$column->setMargin(2, 1);
		$this->frameInputbox->setLayout($column);

		$this->serverName = new Inputbox("serverName");
		$this->serverName->setLabel(__("Server Name", $this->getRecipient()));
		$this->serverName->setText($this->connection->getServerName());
		$this->frameInputbox->addComponent($this->serverName);

		$this->serverComment = new Inputbox("serverComment", 60, 26);
		$this->serverComment->setLabel(__("Server Comment", $this->getRecipient()));
		$this->serverComment->setText($this->connection->getServerComment());
		$this->serverComment->setName("serverComment");
		$this->frameInputbox->addComponent($this->serverComment);


		// Players Min & Max goes to same row
		$this->framePlayers = new Frame();
		$this->framePlayers->setLayout(new Line());
		$this->framePlayers->setSize(100, 11);

		$this->maxPlayers = new Inputbox("maxPlayers", 12);
		$this->maxPlayers->setLabel(__("Players", $this->getRecipient()));
		$this->maxPlayers->setText($server->nextMaxPlayers);
		$this->framePlayers->addComponent($this->maxPlayers);

		$spacer = new Quad(3, 6);
		$spacer->setStyle(Icons64x64_1::EmptyIcon);
		$this->framePlayers->addComponent($spacer);

		$this->maxSpec = new Inputbox("maxSpec", 12);
		$this->maxSpec->setLabel(__("Spectators", $this->getRecipient()));
		$this->maxSpec->setText($server->nextMaxSpectators);
		$this->framePlayers->addComponent($this->maxSpec);

		$this->frameInputbox->addComponent($this->framePlayers);
		// end of players
		// Ladder Points goes to same row
		$this->frameLadder = new Frame();
		$this->frameLadder->setLayout(new Line());
		$this->frameLadder->setSize(100, 11);

		$this->minLadder = new Inputbox("ladderMin");
		$this->minLadder->setLabel(__("Ladderpoints minimum", $this->getRecipient()));
		$this->minLadder->setText($server->ladderServerLimitMin);
		$this->minLadder->setEditable(false);
		$this->frameLadder->addComponent($this->minLadder);

		$spacer = new Quad(3, 6);
		$spacer->setStyle(Icons64x64_1::EmptyIcon);
		$this->frameLadder->addComponent($spacer);

		$this->maxLadder = new Inputbox("ladderMax");
		$this->maxLadder->setLabel(__("Ladderpoints Maximum", $this->getRecipient()));
		$this->maxLadder->setText($server->ladderServerLimitMax);
		$this->maxLadder->setEditable(false);
		$this->frameLadder->addComponent($this->maxLadder);

		$this->frameInputbox->addComponent($this->frameLadder);
		// end of ladder points
		// server password
		$this->serverPass = new InputboxMasked("serverPass");
		$this->serverPass->setLabel(__("Password for server", $this->getRecipient()));
		$this->serverPass->setText($this->connection->getServerPassword());
		$this->serverPass->setShowClearText();
		$this->frameInputbox->addComponent($this->serverPass);

		// spectator password
		$this->serverSpecPass = new InputboxMasked("serverSpecPass");
		$this->serverSpecPass->setLabel(__("Password for spectators", $this->getRecipient()));
		$this->serverSpecPass->setText($this->connection->getServerPasswordForSpectator());
		$this->serverSpecPass->setShowClearText();
		$this->frameInputbox->addComponent($this->serverSpecPass);

		// referee password
		$this->refereePass = new InputboxMasked("refereePass");
		$this->refereePass->setLabel(__("Referee password", $this->getRecipient()));
		$this->refereePass->setText($this->connection->getRefereePassword());
		$this->refereePass->setShowClearText();
		$this->frameInputbox->addComponent($this->refereePass);
	}

// Generate all checkboxes
	private function checkboxes()
	{
		/** @var ServerOptions2 */
		$server = $this->connection->getServerOptions();
		$login = $this->getRecipient();

		$this->frameCb = new Frame();
		$this->frameCb->setAlign("left", "top");
		$this->frameCb->setLayout(new Column());

		// checkbox for public server 
		$publicServer = true;
		if ($server->hideServer > 0)
			$publicServer = false;  // 0 = visible, 1 = hidden 2 = hidden from nations
		$this->cbPublicServer = new Checkbox(4, 4, 50);
		$this->cbPublicServer->setStatus($publicServer);
		$this->cbPublicServer->setText(__("Show Server in public server list", $this->getRecipient()));
		$this->frameCb->addComponent($this->cbPublicServer);

		// checkbox for ladder server
		$this->cbLadderServer = new Checkbox();
		$this->cbLadderServer->setStatus($server->currentLadderMode);
		$this->cbLadderServer->setText(__("Ladder server", $this->getRecipient()));
		$this->frameCb->addComponent($this->cbLadderServer);

		// checkbox for allow map download
		$this->cbAllowMapDl = new Checkbox(4, 4, 50);
		$this->cbAllowMapDl->setStatus($server->allowMapDownload);
		$this->cbAllowMapDl->setText(__("Allow map download using ingame menu", $this->getRecipient()));
		$this->frameCb->addComponent($this->cbAllowMapDl);

		// checkbox for p2p download
		$this->cbAllowp2pDown = new Checkbox(4, 4, 50);
		$this->cbAllowp2pDown->setStatus($server->isP2PDownload);
		$this->cbAllowp2pDown->setText(__("Allow Peer-2-Peer download", $this->getRecipient()));
		$this->frameCb->addComponent($this->cbAllowp2pDown);

		// checkbox for p2p upload
		$this->cbAllowp2pUp = new Checkbox(4, 4, 50);
		$this->cbAllowp2pUp->setStatus($server->isP2PUpload);
		$this->cbAllowp2pUp->setText(__("Allow Peer-2-Peer upload", $this->getRecipient()));
		$this->frameCb->addComponent($this->cbAllowp2pUp);

		// checkbox for Enable referee mode
		$this->cbReferee = new Checkbox(4, 4, 50);
		$this->cbReferee->setStatus($server->refereeMode);
		$this->cbReferee->setText(__("Enable Referee-mode", $this->getRecipient()));
		$this->frameCb->addComponent($this->cbReferee);

		$this->e['DisableHorns'] = new Checkbox(4, 4, 50);
		$this->e['DisableHorns']->setStatus($server->disableHorns);
		$this->e['DisableHorns']->setText(__("Disable Horns", $login));
		$this->frameCb->addComponent($this->e['DisableHorns']);

		$this->e['DisableAnnounces'] = new Checkbox(4, 4, 50);
		$this->e['DisableAnnounces']->setStatus($server->disableServiceAnnounces);
		$this->e['DisableAnnounces']->setText(__("Disable Announces", $login));
		$this->frameCb->addComponent($this->e['DisableAnnounces']);

		$this->e['AutosaveReplays'] = new Checkbox(4, 4, 50);
		$this->e['AutosaveReplays']->setStatus($server->autoSaveReplays);
		$this->e['AutosaveReplays']->setText(__("Autosave All Replays", $login));
		$this->frameCb->addComponent($this->e['AutosaveReplays']);

		$this->e['AutosaveValidation'] = new Checkbox(4, 4, 50);
		$this->e['AutosaveValidation']->setStatus($server->autoSaveValidationReplays);
		$this->e['AutosaveValidation']->setText(__("Autosave Validation Replays", $login));
		$this->frameCb->addComponent($this->e['AutosaveValidation']);

		$this->e['KeepPlayerSlots'] = new Checkbox(4, 4, 50);
		$this->e['KeepPlayerSlots']->setStatus($server->keepPlayerSlots);
		$this->e['KeepPlayerSlots']->setText(__("Keep Player Slots", $login));
		$this->frameCb->addComponent($this->e['KeepPlayerSlots']);

		// spacer
		$quad = new Quad(20, 16);
		$quad->setStyle(Bgs1::BgEmpty);
		$this->frameCb->addComponent($quad);

		// Ok and Cancel buttons goes for own row
		$frame = new Frame();
		$frame->setAlign("left", "top");
		$frame->setSize(40, 20);
		$frame->setLayout(new Line());

		$this->buttonOK = new OkButton();
		$this->buttonOK->setText(__("Apply", $this->getRecipient()));
		$this->buttonOK->setAction($this->actionOK);
		$this->addComponent($this->buttonOK);

		$this->buttonCancel = new OkButton();
		$this->buttonCancel->setText(__("Cancel", $this->getRecipient()));
		$this->buttonCancel->setAction($this->actionCancel);
		$this->addComponent($this->buttonCancel);

		$this->frameCb->addComponent($frame);
	}

	function onDraw()
	{
		$login = $this->getRecipient();

		$this->serverName->setEditable(AdminGroups::hasPermission($login, Permission::server_name));
		$this->serverComment->setEditable(AdminGroups::hasPermission($login, Permission::server_comment));
		$this->maxPlayers->setEditable(AdminGroups::hasPermission($login, Permission::server_maxplayer));
		$this->maxSpec->setEditable(AdminGroups::hasPermission($login, Permission::server_maxspec));
		//$this->minLadder->setEditable(AdminGroups::hasPermission($login, Permission::server_ladder));
		//$this->maxLadder->setEditable(AdminGroups::hasPermission($login, Permission::server_ladder));
		$this->serverPass->setVisibility(AdminGroups::hasPermission($login, Permission::server_password));
		$this->serverSpecPass->setVisibility(AdminGroups::hasPermission($login, Permission::server_specpwd));
		$this->refereePass->setVisibility(AdminGroups::hasPermission($login, Permission::server_refpwd));
		$this->cbPublicServer->SetIsWorking(AdminGroups::hasPermission($login, Permission::server_genericOptions));
		$this->cbLadderServer->SetIsWorking(AdminGroups::hasPermission($login, Permission::server_genericOptions));
		$this->cbAllowMapDl->SetIsWorking(AdminGroups::hasPermission($login, Permission::server_genericOptions));
		$this->cbAllowp2pDown->SetIsWorking(AdminGroups::hasPermission($login, Permission::server_genericOptions));
		$this->cbAllowp2pUp->SetIsWorking(AdminGroups::hasPermission($login, Permission::server_genericOptions));
		$this->cbReferee->SetIsWorking(AdminGroups::hasPermission($login, Permission::server_genericOptions));

		parent::onDraw();
	}

	function destroy()
	{
		$this->buttonCancel->destroy();
		$this->buttonOK->destroy();
		$this->cbAllowMapDl->destroy();
		$this->cbAllowp2pDown->destroy();
		$this->cbAllowp2pUp->destroy();
		$this->cbLadderServer->destroy();
		$this->cbPublicServer->destroy();
		$this->cbReferee->destroy();
		$this->connection = null;
		$this->storage = null;
		$this->maxLadder->destroy();
		$this->minLadder->destroy();
		$this->maxPlayers->destroy();
		$this->maxSpec->destroy();
		$this->refereePass->destroy();
		$this->serverComment->destroy();
		$this->serverName->destroy();
		$this->serverPass->destroy();
		$this->serverSpecPass->destroy();
		$this->frameCb->clearComponents();
		$this->frameCb->destroy();
		$this->frameInputbox->clearComponents();
		$this->frameInputbox->destroy();
		$this->destroyComponents();
		parent::destroy();
	}

	function onResize($oldX, $oldY)
	{
		parent::onResize($oldX, $oldY);
		//   $this->pager->setSize($this->sizeX - 4, $this->sizeY -12);
		$this->serverName->setSize($this->sizeX - 8, 8);
		$this->serverComment->setSizeX($this->sizeX - 70);
		$this->serverPass->setSizeX(($this->sizeX - 8) / 2);
		$this->serverSpecPass->setSizeX(($this->sizeX - 8) / 2);
		$this->refereePass->setSizeX(($this->sizeX - 8) / 2);
		$this->frameInputbox->setPosition(0, -6);
		$this->frameCb->setPosition($this->sizeX / 2 + 20, -25);

		$this->buttonOK->setPosition($this->sizeX - $this->buttonCancel->sizeX - $this->buttonOK->sizeX, -$this->sizeY);
		$this->buttonCancel->setPosition($this->sizeX - $this->buttonCancel->sizeX, -$this->sizeY);
	}

	public function serverOptionsOk($login, $args)
	{

		$server = Storage::getInstance()->server;

		foreach ($this->frameCb->getComponents() as $component) {
			if ($component instanceof Checkbox) {
				$component->setArgs($args);
			}
		}

		$serverOptions = Array(
			"Name" => !AdminGroups::hasPermission($login, Permission::server_name) ? $server->name : $args['serverName'],
			"Comment" => !AdminGroups::hasPermission($login, Permission::server_comment) ? $server->comment : $args['serverComment'],
			"Password" => !AdminGroups::hasPermission($login, Permission::server_password) ? $server->password : $args['serverPass'],
			"PasswordForSpectator" => !AdminGroups::hasPermission($login, Permission::server_specpwd) ? $server->passwordForSpectator : $args['serverSpecPass'],
			"NextCallVoteTimeOut" => !AdminGroups::hasPermission($login, Permission::server_votes) ? $server->nextCallVoteTimeOut : intval($server->nextCallVoteTimeOut),
			"CallVoteRatio" => !AdminGroups::hasPermission($login, Permission::server_votes) ? $server->callVoteRatio : floatval($server->callVoteRatio),
			"RefereePassword" => !AdminGroups::hasPermission($login, Permission::server_refpwd) ? $server->refereePassword : $args['refereePass'],
			"IsP2PUpload" => !AdminGroups::hasPermission($login, Permission::server_genericOptions) ? $server->isP2PUpload : $this->cbAllowp2pUp->getStatus(),
			"IsP2PDownload" => !AdminGroups::hasPermission($login, Permission::server_genericOptions) ? $server->isP2PDownload : $this->cbAllowp2pDown->getStatus(),
			"AllowMapDownload" => !AdminGroups::hasPermission($login, Permission::server_genericOptions) ? $server->allowMapDownload : $this->cbAllowMapDl->getStatus(),
			"NextMaxPlayers" => !AdminGroups::hasPermission($login, Permission::server_maxplayer) ? $server->nextMaxPlayers : intval($args['maxPlayers']),
			"NextMaxSpectators" => !AdminGroups::hasPermission($login, Permission::server_maxspec) ? $server->nextMaxSpectators : intval($args['maxSpec']),
			"RefereeMode" => !AdminGroups::hasPermission($login, 'server_refmode') ? $server->refereeMode : $this->cbReferee->getStatus(),
			"AutoSaveReplays" => $this->e['AutosaveReplays']->getStatus(),
			"AutoSaveValidationReplays" => $this->e['AutosaveValidation']->getStatus(),
			"DisableHorns" => $this->e['DisableHorns']->getStatus(),
			"DisableServiceAnnounces" => $this->e['DisableAnnounces']->getStatus(),
			"KeepPlayerSlots" => $this->e['KeepPlayerSlots']->getStatus()
		);
		
		try {
			$this->connection->setServerOptions(Dedicated_ServerOptions::fromArray($serverOptions));
			$this->connection->keepPlayerSlots($this->e['KeepPlayerSlots']->getStatus());

			if (AdminGroups::hasPermission($login, Permission::server_maxplayer)) {
				$this->connection->setMaxPlayers(intval($args['maxPlayers']));
			}

			if (AdminGroups::hasPermission($login, Permission::server_maxspec)) {
				$this->connection->setMaxSpectators(intval($args['maxSpec']));
			}
		} catch (Exception $e) {
			$this->connection->chatSendServerMessage("Error: " . $e->getMessage());
			$this->connection->chatSendServerMessage(__("Settings not changed.", $login));
		}

		$this->Erase($this->getRecipient());
	}

	public function serverOptionsCancel($login)
	{
		$this->Erase($this->getRecipient());
	}

}
