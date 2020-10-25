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

namespace kings\uhc\arena;

use Exception;
use kings\uhc\math\Time;
use kings\uhc\math\Vector3;
use pocketmine\scheduler\Task;

class ArenaScheduler extends Task
{

    /** @var Arena $arena */
    protected $arena;

    /** @var float|int $gameTime */
    public $gameTime = 60 * 60;

    /** @var int $restartTime */
    public $restartTime = 10;

    /** @var array $restartData */
    public $restartData = [];

    /**
     * ArenaScheduler constructor.
     * @param Arena $arena
     */
    public function __construct(Arena $arena)
    {
        $this->arena = $arena;
    }

    /**
     * @param int $currentTick
     * @throws Exception
     */
    public function onRun(int $currentTick)
    {
        if ($this->arena->setup) return;
        switch ($this->arena->phase) {
            case Arena::PHASE_GAME:
                $this->arena->checkPlayersInsideBorder();
                $this->arena->updateScoreboard([
                    1 => "§7------------------",
                    2 => "§bRemaining: §7" . count($this->arena->players),
                    3 => "§bKills: §7{KILLS}",
                    4 => "§7------------------",
                    5 => "§bBorder: §7" . $this->arena->border,
                    6 => "§bCenter: §7{DISTANCE}",
                    7 => "§bScenarios:",
                ]);
                $this->arena->updateBossbar("§f§lMap: §r§9 " . $this->arena->level->getFolderName() . " §l§b» §r§f" . Time::calculateTime($this->gameTime));
                switch ($this->gameTime) {
                    case 55 * 60:
                        $this->arena->enablePvP();
                        $this->arena->broadcastMessage("§6§l» §r§7PvP has been enabled!");
                        $this->arena->broadcastMessage("§6§l» §r§7Border will shrink 100 blocks in 10 minutes.");
                        break;
                    case 50 * 60:
                        $this->arena->broadcastMessage("§6§l» §r§7Border will shrink 100 blocks in 5 minutes.");
                        break;
                    case 46 * 60:
                        $this->arena->broadcastMessage("§6§l» §r§7Border will shrink 100 blocks in 1 minute.");
                        break;
                    case 45 * 60:
                        $this->arena->shrinkEdge(100);
                        $this->arena->broadcastMessage("§6§l» §r§7Border is now 900 blocks.");
                        break;
                    case 35 * 60:
                        $this->arena->broadcastMessage("§6§l» §r§7Continous shrink starts in 5 minutes.");
                        break;
                    case 30 * 60:
                        $this->arena->broadcastMessage("§6§l» §r§7Continous shrink has been started.");
                        break;
                }
                if ($this->gameTime <= (30 * 60)) {
                    if ($this->arena->border > 50) {
                        $this->arena->shrinkEdge(1);
                    }
                }
                if ($this->arena->checkEnd()) {
                    $this->arena->startRestart();
                }
                if (count($this->arena->players) >= 2 && $this->gameTime <= 0) {
                    $this->arena->startRestart(false);
                }
                $this->gameTime--;
                break;
            case Arena::PHASE_RESTART:
                $this->arena->broadcastMessage("§6§l» §r§7Restarting in {$this->restartTime} sec.", Arena::MSG_TIP);
                $this->restartTime--;
                switch ($this->restartTime) {
                    case 0:
                        foreach ($this->arena->players as $player) {
                            $player->teleport($this->arena->plugin->getServer()->getDefaultLevel()->getSpawnLocation());
                            $player->getInventory()->clearAll();
                            $player->getArmorInventory()->clearAll();
                            $player->getCursorInventory()->clearAll();
                            $player->setFood(20);
                            $player->setHealth(20);
                            $player->setGamemode($this->arena->plugin->getServer()->getDefaultGamemode());
                        }
                        $this->arena->loadArena(true);
                        $this->reloadTimer();
                        break;
                }
                break;
        }
    }

    /**
     * Restarts all
     */
    public function reloadTimer()
    {
        $this->arena->disablePvP();
        $this->gameTime = 60 * 60;
        $this->restartTime = 10;
        $center = Vector3::fromString($this->arena->data['center']);
        $this->arena->maxX = $center->add($this->arena->border)->getX();
        $this->arena->minX = $center->subtract($this->arena->border)->getX();
        $this->arena->maxZ = $center->add(0, 0, $this->arena->border)->getZ();
        $this->arena->minZ = $center->subtract(0, 0, $this->arena->border)->getZ();
        $this->arena->voteManager->reload();
        $this->arena->killsManager->reload();
        $this->arena->limitationsStorage->reload();
    }
}
