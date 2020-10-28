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

namespace kings\uhc\utils;


use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityIds;
use pocketmine\entity\object\ItemEntity;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\Player;

class PluginUtils
{
    public const lineLength = 30;

    public const charWidth = 6;

    public const spaceChar = ' ';

    /**
     * @return Vector3
     */
    public static function getRandomVector(): Vector3
    {
        $x = rand() / getrandmax() * 2 - 1;
        $y = rand() / getrandmax() * 2 - 1;
        $z = rand() / getrandmax() * 2 - 1;
        $v = new Vector3($x, $y, $z);
        return $v->normalize();
    }

    /**
     * @param Player $player
     * @param Block $block
     * @return int
     */
    public static function destroyTree(Player $player, Block $block): int
    {
        $damage = 0;
        if ($block->getId() !== Block::WOOD) {
            return $damage;
        }
        $down = $block->getSide(Vector3::SIDE_DOWN);
        if ($down->getId() == Block::WOOD) {
            return $damage;
        }

        $level = $block->getLevel();

        $cX = $block->getX();
        $cY = $block->getY();
        $cZ = $block->getZ();

        for ($y = $cY + 1; $y < 128; ++$y) {
            if ($level->getBlockIdAt($cX, $y, $cZ) == Block::AIR) {
                break;
            }
            for ($x = $cX - 4; $x <= $cX + 4; ++$x) {
                for ($z = $cZ - 4; $z <= $cZ + 4; ++$z) {
                    $block = $level->getBlock(new Vector3($x, $y, $z));

                    if ($block->getId() !== Block::WOOD && $block->getId() !== Block::LEAVES) {
                        continue;
                    }

                    ++$damage;
                    if ($block->getId() === Block::WOOD) {
                        if ($player->getInventory()->canAddItem(Item::get(Item::WOOD))) {
                            $player->getInventory()->addItem(Item::get(Item::WOOD));
                        }
                    }

                    $level->setBlockIdAt($x, $y, $z, 0);
                    $level->setBlockDataAt($x, $y, $z, 0);
                }
            }
        }
        return $damage;
    }

    public static function assocArrayToScoreboard(array $array)
    {
        $string = '';
        foreach ($array as $item) {
            $string .= "§b- §7{$item}\n";
        }
        return $string;
    }

    /**
     * @param Player $player
     */
    public static function addLightningBolt(Player $player)
    {
        $pk = new AddActorPacket();
        $pk->entityRuntimeId = $pk->entityUniqueId = Entity::$entityCount++;
        $pk->type = AddActorPacket::LEGACY_ID_MAP_BC[EntityIds::LIGHTNING_BOLT];
        $pk->position = $player->asPosition();
        $pk->motion = $player->getMotion();
        $player->sendDataPacket($pk);
    }

    public static function getCompassDirection(float $deg): string
    {
        //https://github.com/Muirfield/pocketmine-plugins/blob/master/GrabBag/src/aliuly/common/ExpandVars.php
        //Determine bearing in degrees
        $deg %= 360;
        if ($deg < 0) {
            $deg += 360;
        }

        if (22.5 <= $deg and $deg < 67.5) {
            return "Northwest";
        } elseif (67.5 <= $deg and $deg < 112.5) {
            return "North";
        } elseif (112.5 <= $deg and $deg < 157.5) {
            return "Northeast";
        } elseif (157.5 <= $deg and $deg < 202.5) {
            return "East";
        } elseif (202.5 <= $deg and $deg < 247.5) {
            return "Southeast";
        } elseif (247.5 <= $deg and $deg < 292.5) {
            return "South";
        } elseif (292.5 <= $deg and $deg < 337.5) {
            return "Southwest";
        } else {
            return "West";
        }
    }
}