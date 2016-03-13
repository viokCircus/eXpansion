<?php

namespace ManiaLivePlugins\eXpansion\Widgets_PlainLocalRecords;

use ManiaLive\PluginHandler\PluginHandler;

/**
 * Description of MetaData
 *
 * @author Petri
 */
class MetaData extends \ManiaLivePlugins\eXpansion\Core\types\config\MetaData
{

	public function onBeginLoad()
	{

		$this->setName("Widget: Plain Local Records");
		$this->setDescription("LocalRecords without maniascript");
		$this->setGroups(array('Widgets', 'Records'));

        	//$this->setEnviAsTitle(true);
		//$this->addTitleSupport('SM');
	}

}
