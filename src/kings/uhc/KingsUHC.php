<?php

/**
 * Copyright 2020-2022 KingsUHC
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


use kings\uhc\arena\ArenaManager;
use kings\uhc\commands\MainCommand;
use kings\uhc\entities\EntityManager;
use kings\uhc\entities\Leaderboard;
use kings\uhc\forms\FormManager;
use kings\uhc\provider\SQLite3Provider;
use kings\uhc\provider\YamlDataProvider;
use kings\uhc\task\JoinGameQueue;
use kings\uhc\utils\BossBar;
use kings\uhc\utils\CpsCounter;
use pocketmine\entity\Entity;
use pocketmine\event\Listener;
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
    /** @var ArenaManager */
    private $arenaManager;
    /** @var CpsCounter */
    private $cpsCounter;
    /** @var JoinGameQueue */
    private $joinGameQueue;
    /** @var EntityManager */
    private $entityManager;
    /** @var SQLite3Provider */
    private $sqliteProvider;


    public function onEnable()
    {
        self::$instance = $this;
        $this->getServer()->getCommandMap()->register('uhc', new MainCommand($this));
        $this->getServer()->getPluginManager()->registerEvents(new UHCListener($this), $this);
        $this->getScheduler()->scheduleRepeatingTask($this->joinGameQueue = new JoinGameQueue($this), 20);
        $this->formManager = new FormManager($this);
        $this->sqliteProvider = new SQLite3Provider($this->getDataFolder() . 'database.sq3');
        $this->dataProvider = new YamlDataProvider($this);
        $this->arenaManager = new ArenaManager($this);
        $this->cpsCounter = new CpsCounter($this);
        $this->entityManager = new EntityManager($this);
    }

    public function onDisable()
    {
        $this->arenaManager->saveArenas();
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

    /**
     * @return ArenaManager
     */
    public function getArenaManager(): ArenaManager
    {
        return $this->arenaManager;
    }

    /**
     * @return CpsCounter
     */
    public function getCpsCounter(): CpsCounter
    {
        return $this->cpsCounter;
    }

    /**
     * @return JoinGameQueue
     */
    public function getJoinGameQueue(): JoinGameQueue
    {
        return $this->joinGameQueue;
    }

    /**
     * @return FormManager
     */
    public function getFormManager(): FormManager
    {
        return $this->formManager;
    }

    /**
     * @return YamlDataProvider
     */
    public function getDataProvider(): YamlDataProvider
    {
        return $this->dataProvider;
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    /**
     * @return SQLite3Provider
     */
    public function getSqliteProvider(): SQLite3Provider
    {
        return $this->sqliteProvider;
    }
}