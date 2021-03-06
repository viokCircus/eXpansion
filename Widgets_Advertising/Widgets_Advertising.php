<?php

namespace ManiaLivePlugins\eXpansion\Widgets_Advertising;

/**
 * Description of Widgets_Advertising
 *
 * @author Petri
 */
class Widgets_Advertising extends \ManiaLivePlugins\eXpansion\Core\types\ExpPlugin
{

	/** @var Config */
	private $config;

	private $settingsChanged = false;

	public function exp_onReady()
	{
		$this->config = Config::GetInstance();
		$this->displayWidget(null);
		$this->enableApplicationEvents();
	}

	public function onSettingsChanged(\ManiaLivePlugins\eXpansion\Core\types\config\Variable $var)
	{
		$name = $var->getName();

		if (isset($this->config->$name)) {
			$this->settingsChanged = true;
		}
	}

	function onPreLoop()
	{
		if ($this->settingsChanged) {
			$this->displayWidget(null);
			$this->settingsChanged = false;
		}
	}

	public function displayWidget($login)
	{
		Gui\Widgets\WidgetAd::EraseAll();

		for ($x = 1; $x <= 5; $x++) {
			$varActive = "active_$x";
			if (isset($this->config->$varActive) && $this->config->$varActive) {
				$widget = Gui\Widgets\WidgetAd::Create($login, false);

				$varX = "x_$x";
				$varY = "y_$x";
				$varImageUrl = "imageUrl_$x";
				$varImageFocusUrl = "imageFocusUrl_$x";
				$varUrl = "url_$x";
				$varManialink = "manialink_$x";
				$varSize = "size_$x";
				$varImageSizeX = "imageSizeX_$x";
				$varImageSizeY = "imageSizeY_$x";

				$widget->setPosition($this->config->$varX, $this->config->$varY, -60);
				$widget->setImage($this->config->$varImageUrl, $this->config->$varImageFocusUrl);
				$widget->setManialink($this->config->$varManialink);
				$widget->setUrl($this->config->$varUrl);
				$widget->setImageSize($this->config->$varImageSizeX, $this->config->$varImageSizeY, $this->config->$varSize);
				$widget->setPositionX($this->config->$varX);
				$widget->setPositionY($this->config->$varY);
				$widget->show();
			}
		}
	}

	public function exp_onUnload()
	{
		Gui\Widgets\WidgetAd::EraseAll();
		parent::exp_onUnload();
	}

}
