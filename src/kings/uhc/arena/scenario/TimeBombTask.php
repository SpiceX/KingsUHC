<?php


namespace kings\uhc\arena\scenario;


use kings\uhc\KingsUHC;
use kings\uhc\utils\CustomFloatingText;
use kings\uhc\utils\PluginUtils;
use pocketmine\level\Explosion;
use pocketmine\level\Position;
use pocketmine\scheduler\Task;

class TimeBombTask extends Task
{
    /** @var CustomFloatingText */
    private $bombText;
    /** @var int */
    private $seconds = 30;
    /** @var Position */
    private $position;

    /**
     * TimeBombTask constructor.
     * @param Position $position
     */
    public function __construct(Position $position)
    {
        foreach ($position->getLevel()->getPlayers() as $player) {
            PluginUtils::addLightningBolt($player);
        }
        $this->bombText = new CustomFloatingText("§aStarting...", Position::fromObject($position->asVector3()->add(0.5, 0.5), $position->getLevel()));
        $this->bombText->spawn();
        $this->position = $position;
    }

    public function onRun(int $currentTick)
    {
        if ($this->seconds > 0) {
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


}