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

use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\level\particle\RedstoneParticle;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;


class ArenaScheduler extends Task
{

    /** @var Arena $arena */
    protected $arena;

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
     * @param Arena $arena
     */
    public function __construct(Arena $arena)
    {
        $this->arena = $arena;
    }

    /**
     * @param int $currentTick
     */
    public function onRun(int $currentTick)
    {
        if ($this->arena->setup) return;
        switch ($this->arena->phase) {
            case Arena::PHASE_GAME:
                foreach ($this->arena->players as $player) {
                    $aabb = new AxisAlignedBB($this->arena->minX, 0, $this->arena->minZ, $this->arena->maxX, $this->arena->level->getWorldHeight(), $this->arena->maxZ);
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
                        $this->arena->pvpEnabled = true;
                        $this->arena->broadcastMessage("§6uhc » §7PvP has been enabled!");
                        $this->arena->broadcastMessage("§6uhc »§7The edge will be reduced 90 blocks in 4 minutes!");
                        break;
                    case 10 * 60:
                        $this->arena->shrinkEdge(90);
                        $this->arena->broadcastMessage("§6uhc »§7The border has been shortened by 90 blocks!");
                        $this->arena->broadcastMessage("§6uhc »§7The edge will be reduced 90 blocks in 3 minutes!");
                        break;
                    case 7 * 60:
                        $this->arena->shrinkEdge(90);
                        $this->arena->broadcastMessage("§6uhc »§7The edge will be reduced 20 blocks in 1 minute!");
                        break;
                    case 6 * 60:
                        $this->arena->shrinkEdge(20);
                        $this->arena->broadcastMessage("§6uhc »§7The border has been shortened by 20 blocks!");
                        $this->arena->broadcastMessage("§6uhc » §7Deathmatch in 5 min.");
                        break;
                    case 3 * 60:
                        $this->arena->broadcastMessage("§6uhc » §7Deathmatch in 2 min.");
                        break;
                    case 1 * 60:
                        $this->arena->deathmatch = true;
                        break;
                }
                if ($this->arena->checkEnd()) {
                    $this->arena->startRestart();
                }
                if (count($this->arena->players) >= 2 && $this->gameTime <= 0) {
                    $this->arena->startRestart(false);
                }
                $this->gameTime--;
                $this->pvpTime--;
                break;
            case Arena::PHASE_RESTART:
                $this->arena->broadcastMessage("§6uhc » §7Restarting in {$this->restartTime} sec.", Arena::MSG_TIP);
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
        $this->arena->pvpEnabled = false;
        $this->startTime = 30;
        $this->gameTime = 30 * 60;
        $this->restartTime = 10;
    }
}
