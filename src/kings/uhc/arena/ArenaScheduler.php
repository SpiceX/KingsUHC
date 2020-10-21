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

namespace kings\uhc\arena;

use kings\uhc\math\Time;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\level\particle\RedstoneParticle;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\ClickSound;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;


class ArenaScheduler extends Task
{

    /** @var Arena $plugin */
    protected $plugin;

    /** @var int $startTime */
    public $startTime = 40;

    /** @var float|int $gameTime */
    public $gameTime = 30 * 60;

    /** @var int $restartTime */
    public $restartTime = 10;

    /** @var int $pvpTime */
    public $pvpTime = 6 * 60;

    /** @var array $restartData */
    public $restartData = [];

    /**
     * ArenaScheduler constructor.
     * @param Arena $plugin
     */
    public function __construct(Arena $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * @param int $currentTick
     */
    public function onRun(int $currentTick)
    {
        if ($this->plugin->setup) return;
        switch ($this->plugin->phase) {
            case Arena::PHASE_LOBBY:
                if (count($this->plugin->players) >= 10) {
                    $this->plugin->broadcastMessage("§a» Starting in " . Time::calculateTime($this->startTime) . " sec «", Arena::MSG_TIP);
                    $this->startTime--;
                    if ($this->startTime == 0) {
                        $this->plugin->startGame();
                        foreach ($this->plugin->players as $player) {
                            $this->plugin->level->addSound(new AnvilUseSound($player->asVector3()));
                        }
                    } else {
                        foreach ($this->plugin->players as $player) {
                            $this->plugin->level->addSound(new ClickSound($player->asVector3()));
                        }
                    }
                } else {
                    $this->plugin->broadcastMessage("§c» Waiting players «", Arena::MSG_TIP);
                    $this->startTime = 40;
                }
                break;
            case Arena::PHASE_GAME:
                $this->plugin->scoreboard->removeLines();
                $this->plugin->scoreboard->setLine(2, " §b" . date('d/m/Y'));
                if (!$this->plugin->pvpEnabled) {
                    $this->plugin->scoreboard->setLine(3, " §aEnabling pvp in: " . Time::calculateTime($this->pvpTime));
                } else {
                    if ($this->plugin->deathmatch) {
                        $this->plugin->scoreboard->setLine(3, " §4Deathmatch");
                    } else {
                        $this->plugin->scoreboard->setLine(3, " §aDeathmatch in: " . Time::calculateTime($this->gameTime - (60 * 2)));
                    }
                }
                $this->plugin->scoreboard->setLine(4, " §eMap: §b" . $this->plugin->level->getFolderName());
                $this->plugin->scoreboard->setLine(6, " §eMode:§a Solo");
                $this->plugin->scoreboard->setLine(8, " §eminelcgames.sytes.net:25271");
                foreach ($this->plugin->players as $player) {
                    $this->plugin->bossbar->updateFor($player, "§f§lTime: §a" . Time::calculateTime($this->gameTime));
                    $this->plugin->scoreboard->showTo($player);
                    $aabb = new AxisAlignedBB($this->plugin->minX, 0, $this->plugin->minZ, $this->plugin->maxX, $this->plugin->level->getWorldHeight(), $this->plugin->maxZ);
                    if (!$aabb->isVectorInXZ($player->getLocation())) {
                        for ($i = 0; $i < 20; ++$i) {
                            $vector = self::getRandomVector()->multiply(3);
                            $vector->y = abs($vector->getY());
                            $player->getLevel()->addParticle(new RedstoneParticle($player->getLocation()->add($vector->x, $vector->y, $vector->z)));
                            $player->getLocation()->add($vector->x, $vector->y, $vector->z);
                        }
                        $player->addEffect(new EffectInstance(Effect::getEffect(Effect::INSTANT_DAMAGE), 1, 0, false));
                    }
                }
                switch ($this->gameTime) {
                    case 14 * 60:
                        $this->plugin->pvpEnabled = true;
                        $this->plugin->broadcastMessage("§6uhc » §7PvP has been enabled!");
                        $this->plugin->broadcastMessage("§6uhc »§7The edge will be reduced 90 blocks in 4 minutes!");
                        break;
                    case 10 * 60:
                        $this->plugin->shrinkEdge(90);
                        $this->plugin->broadcastMessage("§6uhc »§7The border has been shortened by 90 blocks!");
                        $this->plugin->broadcastMessage("§6uhc »§7The edge will be reduced 90 blocks in 3 minutes!");
                        break;
                    case 7 * 60:
                        $this->plugin->shrinkEdge(90);
                        $this->plugin->broadcastMessage("§6uhc »§7The edge will be reduced 20 blocks in 1 minute!");
                        break;
                    case 6 * 60:
                        $this->plugin->shrinkEdge(20);
                        $this->plugin->broadcastMessage("§6uhc »§7The border has been shortened by 20 blocks!");
                        $this->plugin->broadcastMessage("§6uhc » §7Deathmatch in 5 min.");
                        break;
                    case 3 * 60:
                        $this->plugin->broadcastMessage("§6uhc » §7Deathmatch in 2 min.");
                        break;
                    case 1 * 60:
                        $this->plugin->deathmatch = true;
                        $this->plugin->startDeathmatch();
                        break;
                }
                if ($this->plugin->checkEnd()) {
                    $this->plugin->startRestart();
                }
                if (count($this->plugin->players) >= 2 && $this->gameTime <= 0) {
                    $this->plugin->startRestart(false);
                }
                $this->gameTime--;
                $this->pvpTime--;
                break;
            case Arena::PHASE_RESTART:
                $this->plugin->broadcastMessage("§6uhc » §7Restarting in {$this->restartTime} sec.", Arena::MSG_TIP);
                $this->restartTime--;

                switch ($this->restartTime) {
                    case 0:
                        foreach ($this->plugin->players as $player) {
                            $player->teleport($this->plugin->plugin->getServer()->getDefaultLevel()->getSpawnLocation());

                            $player->getInventory()->clearAll();
                            $player->getArmorInventory()->clearAll();
                            $player->getCursorInventory()->clearAll();
                            $this->plugin->bossbar->hideFrom($player);
                            $player->setFood(20);
                            $player->setHealth(20);

                            $player->setGamemode($this->plugin->plugin->getServer()->getDefaultGamemode());
                        }
                        $this->plugin->loadArena(true);
                        $this->reloadTimer();
                        break;
                }
                break;
        }
    }

    /**
     * @return Vector3
     */
    private static function getRandomVector(): Vector3
    {
        $x = rand() / getrandmax() * 2 - 1;
        $y = rand() / getrandmax() * 2 - 1;
        $z = rand() / getrandmax() * 2 - 1;
        $v = new Vector3($x, $y, $z);
        return $v->normalize();
    }

    /**
     * Restarts all timers
     */
    public function reloadTimer()
    {
        $this->pvpTime = 6 * 60;
        $this->plugin->pvpEnabled = false;
        $this->startTime = 30;
        $this->gameTime = 30 * 60;
        $this->restartTime = 10;
    }
}
