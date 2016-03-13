<?php

namespace ManiaLivePlugins\eXpansion\Core\types {

	use Exception;
	use ManiaLib\Application\ErrorHandling;
	use ManiaLive\Data\Player;
	use ManiaLive\Data\Storage as Storage2;
	use ManiaLive\Event\Dispatcher;
	use ManiaLive\PluginHandler\Dependency;
	use ManiaLive\PluginHandler\Exception as Exception3;
	use ManiaLive\PluginHandler\Plugin;
	use ManiaLive\PluginHandler\PluginHandler;
	use ManiaLive\PluginHandler\WaitingCompliant;
	use ManiaLive\Utilities\Console;
	use ManiaLive\Utilities\Logger;
	use ManiaLivePlugins\eXpansion\Core\BillManager;
	use ManiaLivePlugins\eXpansion\Core\ColorParser;
	use ManiaLivePlugins\eXpansion\Core\Config;
	use ManiaLivePlugins\eXpansion\Core\Core;
	use ManiaLivePlugins\eXpansion\Core\Events\GameSettingsEvent;
	use ManiaLivePlugins\eXpansion\Core\Events\GameSettingsEventListener;
	use ManiaLivePlugins\eXpansion\Core\Events\GlobalEvent;
	use ManiaLivePlugins\eXpansion\Core\Events\GlobalEventListener;
	use ManiaLivePlugins\eXpansion\Core\Events\PlayerEvent;
	use ManiaLivePlugins\eXpansion\Core\Events\PlayerEventListener;
	use ManiaLivePlugins\eXpansion\Core\Events\ScriptmodeEvent;
	use ManiaLivePlugins\eXpansion\Core\Events\ScriptmodeEventListener;
	use ManiaLivePlugins\eXpansion\Core\i18n;
	use ManiaLivePlugins\eXpansion\Core\i18n\Message as MultiLangMsg;
	use ManiaLivePlugins\eXpansion\Core\Structures\ExpPlayer;
	use ManiaLivePlugins\eXpansion\Core\types\config\MetaData;
	use ManiaLivePlugins\eXpansion\Core\types\config\types\Int;
	use ManiaLivePlugins\eXpansion\Core\types\config\Variable;
	use ManiaLivePlugins\eXpansion\Helpers\Helper;
	use ManiaLivePlugins\eXpansion\Helpers\Storage;
	use ManiaLivePlugins\Standard\TeamSpeak\TeamSpeak3\Helper\String;
	use Maniaplanet\DedicatedServer\Structures\GameInfos;
	use Maniaplanet\DedicatedServer\Structures\PlayerNetInfo;
	use Maniaplanet\DedicatedServer\Xmlrpc\LoginUnknownException;
	use Phine\Exception\Exception as Exception2;
	use \ManiaLive\DedicatedApi\Config as DedicatedConfig;
	use ManiaLive\DedicatedApi\Callback\Event as ServerEvent;

	/**
	 * Description of BasicPlugin
	 *
	 * @author oliverde8
	 */
	class BasicPlugin extends Plugin implements WaitingCompliant, GameSettingsEventListener, PlayerEventListener, GlobalEventListener
	{

		public static $plugins_list;

		/**
		 * The list of Plugin id's that may need to be started
		 *
		 * @var string[]
		 */
		public static $plugins_onHold = array();

		/**
		 * The list of Plugins that has chat redirect activated
		 */
		private static $exp_chatRedirected = array();

		/**
		 *
		 * @var BillManager
		 */
		private static $exp_billManager = null;

		/**
		 * THe list of plugins that have their announcement redirected
		 */
		private static $exp_announceRedirected = array();

		private $exp_unloading = false;

		/**
		 * The path to the directory of this plugin
		 *
		 * @var String
		 */
		private $exp_dir = null;

		/**
		 * The colorparser
		 *
		 * @var ColorParser
		 */
		protected $colorParser;

		private $_isReady = false;

		private $_scriptEventsEnabled = false;

		/**
		 *
		 * @var MetaData
		 */
		protected $metaData;

		/**
		 * @var Storage
		 */
		protected $expStorage;

		public final function onInit()
		{
			$this->expStorage = Storage::getInstance();

			$this->loadMetaData();
			if (!$this->metaData->checkAll()) {
				return;
			}

			$this->checkVersion();


			self::$plugins_list[get_class($this)] = $this;

			$this->setVersion(Core::EXP_VERSION);
			ErrorHandler::$server = $this->storage->serverLogin;
			try {
				$this->enableDatabase();
			} catch (Exception $e) {
				$this->dumpException('There seems be a problem while establishing a MySQL connection.', $e);
				exit(1);
			}

			//Recovering the eXpansion pack tools
			$this->colorParser = ColorParser::getInstance();

			$this->exp_unloading = false;
			i18n::getInstance()->registerDirectory($this->exp_getdir());


			//All plugins need the eXpansion Core to work properly
			if ($this->getId() != '\ManiaLivePlugins\eXpansion\Core' && $this->getId() != '\ManiaLivePlugins\eXpansion\AutoLoad\AutoLoad'
			)
				$this->addDependency(new Dependency('\ManiaLivePlugins\eXpansion\Core\Core'));

			$this->setPublicMethod('exp_unload');
			$this->setPublicMethod('getDependencies');
			$this->setPublicMethod('exp_chatSendServerMessage');
			$this->setPublicMethod('exp_activateChatRedirect');
			$this->setPublicMethod('exp_deactivateChatRedirect');
			$this->setPublicMethod('exp_activateAnnounceRedirect');
			$this->setPublicMethod('exp_deactivateAnnounceRedirect');
			$this->setPublicMethod('onSettingsChanged');

			$this->exp_onInit();

			Dispatcher::register(GameSettingsEvent::getClass(), $this);
			Dispatcher::register(PlayerEvent::getClass(), $this);
			Dispatcher::register(GlobalEvent::getClass(), $this);
		}

		private function loadMetaData()
		{
			$pieces = explode('\\', get_class($this));
			array_pop($pieces);
			$class = implode('\\', $pieces);
			$class .= '\\MetaData';
			$this->metaData = $class::getInstance($this->getId());
		}

		/**
		 *
		 * @return MetaData
		 */
		public static function getMetaData()
		{
			$class = get_called_class();
			$pieces = explode('\\', $class);
			array_pop($pieces);
			$class = implode('\\', $pieces);
			$class .= '\\MetaData';

			return $class::getInstance();
		}

		/**
		 * eXpansion method invoked Manialive onInit
		 *
		 * @abstract
		 */
		public function exp_onInit()
		{
			
		}

		/**
		 * enables eXpansion modescript bidings
		 *
		 * example:
		 * 
		 * $this->enableScriptEvents("LibXmlRpc_OnStunt");
		 * $this->enableScriptEvents(array("LibXmlRpc_OnStunt", "LibXmlRpc_OnWaypoint"));
		 *
		 * @param string|array $callback this can be either one callback or array of callbacks
		 *
		 */
		public final function enableScriptEvents($callback = false)
		{
			if ($callback === false) {
				throw new Exception('$this->enableScriptEvents($callback) needs a value for whitelisting...');
			}
			$this->enableDedicatedEvents(ServerEvent::ON_MODE_SCRIPT_CALLBACK);
			$this->_scriptEventsEnabled = true;
			Core::enableScriptCallback($callback);
			//	Dispatcher::register(ServerEvent::getClass(), $this, ServerEvent::ON_MODE_SCRIPT_CALLBACK);
		}

		public final function onLoad()
		{
			if (!$this->metaData->checkAll()) {
				$this->exp_unload();

				return;
			}
			try {
				$this->exp_onLoad();
			} catch (Exception $e) {
				Helper::log("[BasicPlugin]onLoad exception:" . $this->getId() . " -> " . $e->getMessage() . "\n");
				Helper::log("[BasicPlugin]" . ErrorHandling::computeMessage($e));
			}
		}

		/**
		 * eXpansion method invoked at Manialive onload
		 *
		 * @abstract
		 */
		public function exp_onLoad()
		{
			
		}

		public final function onReady()
		{
			if (!$this->metaData->checkAll()) {
				$this->exp_unload();
				return;
			}
			else {
				if (!$this->_isReady) {
					$this->_isReady = true;
					$this->exp_onReady();
				}
			}

			//Recovering the billManager if need.
			if (self::$exp_billManager == null) {
				self::$exp_billManager = new BillManager($this->connection, $this->db, $this);
			}

			// to save resources disable triggering scriptmode events from all plugins automatically
			// you have to use $this->enableScriptCallbacks(array("callbackname1","callbackname2") to enable them!
			if ($this->_scriptEventsEnabled === false) {
				$this->disableDedicatedEvents(ServerEvent::ON_MODE_SCRIPT_CALLBACK);
			}
		}

		/**
		 * eXpansion onReady handler
		 *
		 * @abstract
		 */
		public function exp_onReady()
		{
			
		}

		/**
		 * Overwritten modescript callback to trigger modescript functions
		 * @param string $param1
		 * @param string|array $param2
		 */
		final public function onModeScriptCallback($param1, $param2)
		{					
			$out = array();
			if (is_array($param2)) {
				foreach ($param2 as $value) {
                                    $out[] = $this->parseScriptValue($value);
				}
			} else {
+				$out = $this->parseScriptValue($param2);
 			}


			if (method_exists($this, $param1)) {
				call_user_func_array(array($this, $param1), $out);
			}
			else {
				$this->exp_onModeScriptCallback($param1, $out);
			}
		}
                
                protected function parseScriptValue($value) {
			if (filter_var($value, FILTER_VALIDATE_INT)) {
				return intval($value);
			} else if (is_numeric($value)) {
				return floatval($value);
			} else if ($value == "False") {
				return false;
			} else if ($value == "True") {
                                return true;
			} else {
				return $value;
			}
		}
		/**
		 * redirected onModeScriptCallback
		 * @param string $param1
		 * @param string|array $param2
		 */
		public function exp_onModeScriptCallback($param1, $param2)
		{

		}

		final public function onUnload()
		{
			Dispatcher::unregister(GameSettingsEvent::getClass(), $this);
			Dispatcher::unregister(PlayerEvent::getClass(), $this);
			Dispatcher::unregister(GlobalEvent::getClass(), $this);

			try {
				$this->exp_onUnload();
			} catch (Exception $e) {
				Helper::log("[BasicPlugin]onUnload exception:" . $this->getId() . " -> " . $e->getMessage() . "\n");
			}

			unset(self::$plugins_list[get_class($this)]);
			parent::onUnload();
		}

		public function exp_onUnload()
		{
			
		}

		private function checkVersion()
		{
			if (version_compare(
							\ManiaLive\Application\VERSION, Core::EXP_REQUIRE_MANIALIVE, 'lt'
					)
			) {
				$this->dumpException(
						"Looks like your ManiaLive is too old to run this version of eXpansion.\n"
						. "Your ManiaLive version: " . \ManiaLive\Application\VERSION . ", (required " . Core::EXP_REQUIRE_MANIALIVE . ")\n"
						. "Please update your manialive version in order to continue.", New Exception3("ManiaLive version is too old!")
				);
				exit();
			}
		}

		private function exp_getdir()
		{
			$trim = false;
			if ($this->exp_dir == null) {
				$exploded = explode("\\", get_class($this));
				$trim = false;
				$this->exp_dir = "libraries/";
				if (!is_dir("libraries/ManiaLivePlugins/eXpansion/Core")) {
					$trim = true;
					$this->exp_dir = "vendor/ml-expansion/";
				}
				$i = 0;
				while ($i < sizeof($exploded) - 2) {
					$this->exp_dir .= $exploded[$i] . "/";
					$i++;
				}
				$this->exp_dir .= $exploded[$i];
			}
			if ($trim) {
				$this->exp_dir = str_replace("\\ManiaLivePlugins/", "", $this->exp_dir);
				$this->exp_dir = str_replace("eXpansion", "expansion", $this->exp_dir);
			}

			return $this->exp_dir;
		}

		/**
		 *
		 * to send everybody:
		 * exp_chatSendServerMessage("Message with parameters %1$s %2$s", null, array("parameter1","parameter2));
		 *
		 * to send login:
		 * exp_chatSendServerMessage("Message with parameters %1$s %2$s", $login, array("parameter1","parameter2));
		 *
		 * @param string|MultiLangMsg $msg String or MultiLangMsg to sent
		 * @param null|string $login       null for everybody, string for individual
		 * @param array $args              simple array of parameters
		 */
		public function exp_chatSendServerMessage($msg, $login = null, $args = array())
		{
			if (!($msg instanceof MultiLangMsg)) {
				if (DEBUG) {
					$this->console("#Plugin " . $this->getId() . " uses chatSendServerMessage in an unoptimized way!!");
				}
				$msg = exp_getMessage($msg);
			}

			if ($login == null) {
				/* array_unshift($args, $msg->getMessage());
				  $msg = call_user_func_array('sprintf', $args);
				  $this->exp_announce($msg);
				 */
				$this->exp_multilangAnnounce($msg, $args);
			}
			else {
				array_unshift($args, $msg, $login);
				$msgString = call_user_func_array('__', $args);

				//Check if it needs to be redirected
				$this->exp_redirectedChatSendServerMessage($msgString, $login, get_class($this));
			}
		}

		/**
		 * Sends a chat message to the server or redirect to another plugin
		 *
		 * @param type $msg   The message
		 * @param type $login The login to whom it needs to be sent
		 */
		private function exp_redirectedChatSendServerMessage($msg, $login)
		{
			$sender = get_class($this);
			$fromPlugin = explode("\\", $sender);
			$fromPlugin = str_replace("_", " ", end($fromPlugin));

			if (isset(self::$exp_chatRedirected[$sender])) {
				$message = $msg;
				if (is_object(self::$exp_chatRedirected[$sender][0]))
					call_user_func_array(
							self::$exp_chatRedirected[$sender], array($login, $this->colorParser->parseColors($message))
					);
				else {
					$this->callPublicMethod(
							self::$exp_chatRedirected[$sender][0], self::$exp_chatRedirected[$sender][1], array($login, $this->colorParser->parseColors($message))
					);
				}
			}
			else {

				try {
					$this->connection->chatSendServerMessage($this->colorParser->parseColors($msg), $login);
				} catch (Exception $e) {
					$this->console(
							"Error while sending chat message to '" . $login . "'\n Server said:" . $e->getMessage()
					);
				}
			}
		}

		/**
		 * Sends annoucement throught chat to the server or redirects it to another plugin
		 *
		 * @param type $message
		 * @param type $icon
		 * @param type $callback
		 * @param type $pluginid
		 */
		protected function exp_announce($msg, $icon = null, $callback = null, $pluginid = null)
		{
			$sender = get_class($this);
			$fromPlugin = explode("\\", $sender);
			$fromPlugin = str_replace("_", " ", end($fromPlugin));

			if (isset(self::$exp_announceRedirected[$sender])) {
				$message = clone $msg;
				if (is_object(self::$exp_announceRedirected[$sender][0]))
					call_user_func_array(
							self::$exp_announceRedirected[$sender], array($this->colorParser->parseColors($message), $icon, $callback, $pluginid)
					);
				else {
					$this->callPublicMethod(
							self::$exp_chatRedirected[$sender][0], self::$exp_chatRedirected[$sender][1], array($this->colorParser->parseColors($message), $icon, $callback, $pluginid)
					);
				}
			}
			else {
				try {
					$this->connection->chatSendServerMessage(
							'$n' . $fromPlugin . '$z$s$ff0 〉$fff' . $this->colorParser->parseColors($msg)
					);
				} catch (LoginUnknownException $ex) {
					Console::println(
							"[eXpansion]Attempt to send Announce to a login failed. Login unknown"
					);
					Logger::info(
							"[eXpansion]Attempt to send Announce to a login failed. Login unknown"
					);
				} catch (Exception $e) {
					$this->console("Error while sending Announce message => Server said:" . $e->getMessage());
				}
			}
		}

		protected function exp_multilangAnnounce(MultiLangMsg $msg, array $args)
		{
			$sender = get_class($this);
			$fromPlugin = explode("\\", $sender);
			$fromPlugin = str_replace("_", " ", end($fromPlugin));


			if (isset(self::$exp_chatRedirected[$sender])) {
				$message = clone $msg;
				$message->setArgs($args);
				if (is_object(self::$exp_chatRedirected[$sender][0]))
					call_user_func_array(self::$exp_chatRedirected[$sender], array(null, $message));
				else {
					$this->callPublicMethod(
							self::$exp_chatRedirected[$sender][0], self::$exp_chatRedirected[$sender][1], array(null, $message)
					);
				}
			}
			else {
				try {
					$msg->setArgs($args);
					$this->connection->chatSendServerMessageToLanguage($msg->getMultiLangArray());
				} catch (LoginUnknownException $ex) {
					Console::println(
							"[eXpansion]Attempt to send Multilang Announce to a login failed. Login unknown"
					);
					Logger::info(
							"[eXpansion]Attempt to send Multilang Announce to a login failed. Login unknown"
					);
				} catch (Exception $e) {
					$this->console("Error while sending Multilang Announce message => Server said:" . $e->getMessage());
				}
			}
		}

		/**
		 * Unloads the plugin.
		 *
		 * @abstract
		 */
		final public function exp_unload()
		{
			if ($this->exp_unloading)
				return;

			Dispatcher::unregister(GameSettingsEvent::getClass(), $this);
			Dispatcher::unregister(PlayerEvent::getClass(), $this);
			Dispatcher::unregister(GlobalEvent::getClass(), $this);

			$this->console('Unloading ' . $this->getId());
			$pHandler = PluginHandler::getInstance();

			$plugins = $pHandler->getLoadedPluginsList();
			$this->console('[eXpansion] Unloading Dependencies of ' . $this->getId() . '');
			foreach ($plugins as $plugin) {
				try {
					if ($plugin != $this->getId()) {
						$deps = array();
						if (method_exists($plugin, 'getDependencies')) {
							try {
								$deps = $this->callPublicMethod($plugin, 'getDependencies');
							} catch (Exception $ex) {
								//Nothing to do, not a eXpansion plugin we will hope for the best
							}
						}
						if (!empty($deps)) {
							foreach ($deps as $dep) {
								if ($dep->getPluginId() == $this->getId()) {
									$this->callPublicMethod($plugin, 'exp_unload');
									break;
								}
							}
						}
					}
				} catch (Exception $ex) {
					Helper::log("[BasicPlugin]onUnload exception:" . $ex->getFile() . ":" . $ex->getLine() . "\n" . $ex->getMessage());
				}
			}

			//Unloading it self
			$this->exp_unloading = true;
			$pHandler->unload($this->getId());
			self::$plugins_onHold[$this->getId()] = $this->getId();
		}

		/**
		 * Activates the message redirect for this plugin.
		 *
		 * @param type $array The Object or plugin id and the function to call
		 */
		public function exp_activateChatRedirect($array)
		{
			self::$exp_chatRedirected[get_class($this)] = $array;
		}

		/**
		 * Deactivate chat redirect to send it back throught the chat
		 */
		public function exp_deactivateChatRedirect()
		{
			unset(self::$exp_chatRedirected[get_class($this)]);
		}

		/**
		 * Activates the announcement redirect ot send it to a plugin
		 *
		 * @param type $array The Object or plugin id and the function to call
		 */
		public function exp_activateAnnounceRedirect($array)
		{
			self::$exp_announceRedirected[get_class($this)] = $array;
		}

		/**
		 * Deactivate chat redirect to send it back throught the chat
		 */
		public function exp_deactivateAnnounceRedirect()
		{
			unset(self::$exp_announceRedirected[get_class($this)]);
		}

		/**
		 * Will start a billing process.
		 *
		 * @param       $source_login      The login to whom the planets will be taken from
		 * @param       $destination_login The login to whom the planets will be send
		 * @param       $amount            The amoint of planets that wil be sent
		 * @param       $msg               The label of the bill
		 * @param array $callback          The callback in case of sucess
		 * @param array $params            The parameters to pass whith the calback
		 *
		 * @return Bill*
		 */
		final public function exp_startBill(
		$source_login, $destination_login, $amount, $msg, $callback = array(), $params = array()
		)
		{
			$bill = new Bill($source_login, $destination_login, $amount, $msg);
			self::$exp_billManager->sendBill($bill);
			$bill->setValidationCallback($callback, $params);

			$bill->setPluginName($this->exp_getOldId());
			$bill->setSubject($msg);

			return $bill;
		}

		final public function exp_getOldId($id = null)
		{
			if ($id == null) {
				$id = $this->getId();
			}
			$e = explode("\\", $id);

			return $e[1] . "\\" . $e[2];
		}

		/**
		 * Returns the current game mode taking in acount script modes that might be equivalent with old modes
		 *
		 * @return Int The gamemode which is compatible with the current script. 0 if none
		 */
		final static public function exp_getCurrentCompatibilityGameMode()
		{
			$gameInfo = Storage2::getInstance()->gameInfos;
			if ($gameInfo->gameMode == GameInfos::GAMEMODE_SCRIPT) {
				return self::exp_getScriptCompatibilityMode($gameInfo->scriptName);
			}
			else {
				return $gameInfo->gameMode;
			}
		}

		/**
		 *
		 * @param type $scriptName
		 *
		 * @return int The gamemode which is compatible with the script. 0 if none
		 */
		final static public function exp_getScriptCompatibilityMode($scriptName)
		{
			$class = get_called_class();
			$soft = true;

			$compatibility = explode(".", $scriptName);
			$compatibility = strtoupper($compatibility[0]);

			if ($soft) {
				if (strpos($compatibility, 'TIMEATTACK') !== false) {
					$compatibility = GameInfos::GAMEMODE_TIMEATTACK;
				}
				elseif (strpos($compatibility, 'ROUNDS') !== false || strpos(
								$compatibility, 'ROUNDSBASE'
						) !== false
				) {
					$compatibility = GameInfos::GAMEMODE_ROUNDS;
				}
				elseif (strpos($compatibility, 'TEAM') !== false) {
					$compatibility = GameInfos::GAMEMODE_TEAM;
				}
				elseif (strpos($compatibility, 'CUP') !== false) {
					$compatibility = GameInfos::GAMEMODE_ROUNDS;
				}
				elseif (strpos($compatibility, 'LAPS') !== false) {
					$compatibility = GameInfos::GAMEMODE_LAPS;
				}
				else {
					$compatibility = 0;
				}
			}
			else {
				switch ($compatibility) {
					case 'TIMEATTACK' :
						$compatibility = GameInfos::GAMEMODE_TIMEATTACK;
						break;
					case 'ROUNDS' :
					case 'ROUNDSBASE' :
						$compatibility = GameInfos::GAMEMODE_ROUNDS;
						break;
					case 'TEAM' :
						$compatibility = GameInfos::GAMEMODE_TEAM;
						break;
					case 'CUP' :
						$compatibility = GameInfos::GAMEMODE_CUP;
						break;
					default :
						$compatibility = 0;
				}
			}

			return $compatibility;
		}

		final public function debug($message)
		{
			$config = Config::getInstance();
			if (!$config->debug)
				return;

			if (is_string($message)) {
				Console::println($message);
				Logger::log($message, true, "exp-debug.txt");
			}
			if (is_array($message)) {
				$info = print_r($message, true);
				Console::println($info);
				Logger::log($info, true, "exp-debug.txt");
			}
			if (is_object($message)) {
				$info = var_export($message, true);
				Console::println($info);
				Logger::log($message, true, "exp-debug.txt");
			}
		}

		final public function dumpException($message, Exception $e)
		{
			$this->console('                                ____                  _  ');
			$this->console('                               / __ \                | |');
			$this->console('                              | |  | | ___  _ __  ___| |');
			$this->console('                              | |  | |/ _ \|  _ \/ __| |');
			$this->console('                              | |__| | (_) | |_) \__ \_|');
			$this->console('                               \____/ \___/| .__/|___(_)');
			$this->console('                                           | | ');
			$this->console('                                           |_| ');
			$this->console('');

			$fill = "";
			$firstline = explode("\n", $message, 2);
			if (!is_array($firstline))
				$firstline = array($firstline);
			for ($x = 0; $x < ((80 - strlen($firstline[0])) / 2); $x++) {
				$fill .= " ";
			}
			$this->console($fill . $message);
			$this->console('');
			$file = explode(DIRECTORY_SEPARATOR, $e->getFile());
			$this->console('Advanced details');
			$this->console('File: ' . end($file));
			$this->console('Line: ' . $e->getLine());
			$this->console('Message: ' . $e->getMessage());
			$this->console('');
		}

		/**
		 * Returns player object from given playerId
		 *
		 * @param mix $id
		 *
		 * @return Player
		 */
		public function getPlayerObjectById($id)
		{
			if (!is_numeric($id))
				throw new Exception2("player id is not numeric");
			foreach ($this->storage->players as $login => $player) {
				if ($player->playerId == $id)
					return $player;
			}
			foreach ($this->storage->spectators as $login => $player) {
				if ($player->playerId == $id)
					return $player;
			}

			return new Player();
		}

		final public function console($message)
		{
			$logFile = $this->storage->serverLogin . ".console.log";
			/** @var Logger */
			$logger = Logger::getLog("eXpansion");

			if (is_string($message)) {
				Console::println($message);
				$logger::log($message, true, $logFile);
			}
			if (is_array($message)) {
				$info = print_r($message, true);
				Console::println($info);
				$logger::log($info, true, $logFile);
			}
			if (is_object($message)) {
				$info = var_export($message, true);
				Console::println($info);
				$logger::log($info, true, $logFile);
			}
		}

		public function onSettingsChanged(Variable $var)
		{
			
		}

		public function onGameModeChange($oldGameMode, $newGameMode)
		{
			
		}

		public function onGameSettingsChange(GameInfos $oldSettings, GameInfos $newSettings, $changes)
		{
			
		}

		/**
		 * @param ExpPlayer $player player object of the player given up
		 */
		public function onPlayerGiveup(ExpPlayer $player)
		{
			
		}

		/**
		 *
		 * @param ExpPlayer $player player object of the player
		 * @param int $oldPos       old position
		 * @param int $newPos       new position
		 */
		public function onPlayerPositionChange(
		ExpPlayer $player, $oldPos, $newPos
		)
		{
			
		}

		/**
		 * @param ExpPlayer[] $playerPositions array(string => ExpPlayer);
		 */
		public function onPlayerNewPositions($playerPositions)
		{
			
		}

		public function onMapRestart()
		{
			
		}

		public function onMapSkip()
		{
			
		}

		/**
		 * @param PlayerNetInfo[] $players
		 */
		public function onPlayerNetLost($players)
		{
			
		}

	}

}

namespace {

	use \ManiaLivePlugins\eXpansion\Helpers\Helper;

	/**
	 * Convert php.ini memory shorthand string to integer bytes
	 * http://www.php.net/manual/en/function.ini-get.php#96996
	 */
	function shorthand2bytes($size_str)
	{

		switch (substr($size_str, -1)) {
			case 'M':
			case 'm':
				return (int) $size_str * 1048576;
			case 'K':
			case 'k':
				return (int) $size_str * 1024;
			case 'G':
			case 'g':
				return (int) $size_str * 1073741824;
			default:
				return (int) $size_str;
		}
	}

	// return_bytes
	//
	// fix for  php 5.5.0
	error_reporting(E_ALL ^ E_DEPRECATED);
	// do custom logging also
	$limit = ini_get('memory_limit');
	if (shorthand2bytes($limit) < 256 * 1048576)
		ini_set('memory_limit', '256M');

	set_time_limit(0);

	set_error_handler('\\ManiaLivePlugins\\eXpansion\\Core\\types\\ErrorHandler::createExceptionFromError');

	/* 
	// moved to core exp_onInit
	  if (!defined("DEBUG")) {
		$config = ManiaLivePlugins\eXpansion\Core\Config::getInstance();
		define("DEBUG", filter_var($config->debug, FILTER_VALIDATE_BOOLEAN));
	} */


	if (!function_exists('__')) {

		/**
		 * $player = \ManiaLive\Data\Storage::getInstance()->getPlayerObject($login);
		 * if($player == null){
		 * array_unshift($args, $msg);
		 * $msgString = call_user_func_array('__', $args);
		 * }else{
		 * array_unshift($args, $msg, $login);
		 * $msgString = call_user_func_array('__', $args);
		 * }
		 *
		 */
		function __()
		{
			$args = func_get_args();

			if (empty($args))
				return '';

			$message = array_shift($args);

			if ($message == "" || $message == null)
				return '';

			$language = null;
			if (sizeof($args) > 0) {
				$login = array_shift($args);
				$player = \ManiaLive\Data\Storage::getInstance()->getPlayerObject($login);
				if ($player == null) {
					$language = null;
				}
				else {
					$language = $player->language;
				}
			}
			else {
				$language = null;
			}

			if (is_object($message)) {
				$lang = $message->getMessage($language);
			}
			else {
				$lang = \ManiaLivePlugins\eXpansion\Core\i18n::getInstance()->getString($message, $language);
			}

			array_unshift($args, $lang);

			try {
				return call_user_func_array('sprintf', $args);
			} catch (\Exception $e) {
				Helper::logError('[basicPlugin/Chat]Error with translations strings : ' . $lang);
				Helper::logError('[basicPlugin/Chat]' . $e->getMessage());

				return $lang;
			}
		}

		/**
		 * exp_getMessage(string $string)
		 *
		 * @param string $string
		 *
		 * @return \ManiaLivePlugins\eXpansion\Core\i18n\Message
		 */
		function exp_getMessage($string)
		{
			return \ManiaLivePlugins\eXpansion\Core\i18n::getInstance()->getObject($string);
		}

	}
}

