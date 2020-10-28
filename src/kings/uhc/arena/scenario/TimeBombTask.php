<?php

namespace kings\uhc\arena\scenario;

use kings\uhc\arena\Arena;
use kings\uhc\KingsUHC;
use kings\uhc\utils\CustomFloatingText;
use kings\uhc\utils\PluginUtils;
use pocketmine\block\Block;
use pocketmine\level\Explosion;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\tile\Chest;
use pocketmine\tile\Tile;

class TimeBombTask extends Task
{
    /** @var CustomFloatingText */
    private $bombText;
    /** @var int */
    private $seconds = 30;
    /** @var Position */
    private $position;
    /** @var Arena */
    private $arena;

    /**
     * TimeBombTask constructor.
     * @param Arena $arena
     * @param Position $position
     */
    public function __construct(Arena $arena, Position $position)
    {
        foreach ($position->getLevel()->getPlayers() as $player) {
            PluginUtils::addLightningBolt($player);
        }
        $this->bombText = new CustomFloatingText("§aStarting...", Position::fromObject($position->asVector3()->add(0.5, 0.5), $position->getLevel()));
        $this->bombText->spawn();
        $this->position = $position;
        $this->arena = $arena;
    }

    public function onRun(int $currentTick)
    {
        if ($this->seconds > 0) {
            if ($this->arena->phase === Arena::PHASE_RESTART) {
                $this->bombText->remove();
                $this->seconds = 30;
                KingsUHC::getInstance()->getScheduler()->cancelTask($this->getTaskId());
                return;
            }
            $this->bombText->update("§aExploding in " . $this->seconds);
            $this->seconds--;
        } else {
            $this->bombText->remove();
            $this->explode();
            $this->seconds = 30;
            KingsUHC::getInstance()->getScheduler()->cancelTask($this->getTaskId());
        }
    }

    public function explode()
    {
        $explosion = new Explosion($this->position, 6);
        $explosion->explodeA();
        $explosion->explodeB();
    }

    public static function createChest(Player $player, array $items)
    {

        $level = $player->getPosition();
        $world = $player->getLevel();
        $block = Block::get(Block::CHEST);
        $x = ((int)$level->getX());
        $y = ((int)$level->getY());
        $z = ((int)$level->getZ());
        $world->setBlock(new Vector3($x, $y, $z), $block);
        $world->setblock(new Vector3($x + 1, $y, $z), $block);
        $nbt = Chest::createNBT(new Vector3($x, $y, $z));
        $nbt2 = Chest::createNBT(new Vector3($x + 1, $y, $z));
        $tile = Tile::createTile(Tile::CHEST, $world, $nbt);
        $tile2 = Tile::createTile(Tile::CHEST, $world, $nbt2);
        $tile->pairwith($tile2);
        $tile2->pairwith($tile);
        $tile->pairwith($tile2);
        $tile2->pairwith($tile);
        if ($tile instanceof Chest) {
            foreach ($items as $item) {
                $tile->getInventory()->addItem($item);
            }
        }

    }


}