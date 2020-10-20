<?php

/**
 * Copyright 2020-2022 kings
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);
namespace kings\uhc;


use Exception;
use kings\uhc\arena\Arena;
use kings\uhc\arena\MapReset;
use kings\uhc\commands\MainCommand;
use kings\uhc\entities\Leaderboard;
use kings\uhc\forms\FormManager;
use kings\uhc\math\Vector3;
use kings\uhc\provider\YamlDataProvider;
use kings\uhc\utils\BossBar;
use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\level\Level;
use pocketmine\plugin\PluginBase;

class KingsUHC extends PluginBase implements Listener
{

	/** @var KingsUHC $instance */
	public static $instance;
	/**@var FormManager */
	public $formManager;
	/**@var YamlDataProvider */
	public $dataProvider;
    /** @var BossBar */
    private $bossbar;


    public function onEnable()
	{
		self::$instance = $this;
		Entity::registerEntity(Leaderboard::class, true, ['Leaderboard']);
		$this->getServer()->getCommandMap()->register('uhc', new MainCommand($this));
		$this->registerEvents();
		$this->formManager = new FormManager($this);
		$this->dataProvider = new YamlDataProvider($this);
		$this->getServer()->getLogger()->info("§6uhc enabled!");
		$this->getServer()->getLogger()->info("§9LICENSE: §7CU4MA-2JG1N-44EGQ-9VNGZ-Q60UD");
		$this->getServer()->getLogger()->info("§aPlugin made by §b@kings_");
	}

	public function onLoad()
	{
		$this->bossbar = new BossBar();
	}

	public function onDisable()
	{
		$this->dataProvider->saveArenas();
	}

	private function registerEvents()
	{
		$events = [new MainEvents($this)];
		foreach ($events as $event) {
			$this->getServer()->getPluginManager()->registerEvents($event, $this);
		}
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		unset($events);
	}


	/**
	 * @return KingsUHC
	 */
	public static function getInstance(): KingsUHC
	{
		return self::$instance;
	}

    /**
     * @return BossBar
     */
    public function getBossbar(): BossBar
    {
        return $this->bossbar;
    }
}