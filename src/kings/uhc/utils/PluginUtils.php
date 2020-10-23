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

namespace kings\uhc\utils;


use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
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
    public static function destroyTree(Player $player, Block $block)
    {
        $damage = 0;
        if ($block->getId() != Block::WOOD) {
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
                            $player->getInventory()->addItem(Item::get(Item::WOOD, 0, mt_rand(1, 2)));
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
}