<?php


namespace kings\uhc\entities\types;


use kings\uhc\KingsUHC;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\Level;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;

class Leaderboard extends Human
{


    public function __construct(Level $level, CompoundTag $nbt)
    {
        parent::__construct($level, $nbt);
        $this->setSkin(new Skin('Standard_Custom', str_repeat("\x00", 8192), '', 'geometry.humanoid.custom'));
        $this->sendSkin();
        $this->propertyManager->setPropertyValue(Entity::DATA_BOUNDING_BOX_WIDTH, Entity::DATA_TYPE_FLOAT, 0);
        $this->propertyManager->setPropertyValue(Entity::DATA_BOUNDING_BOX_HEIGHT, Entity::DATA_TYPE_FLOAT, 0);
    }

    public function entityBaseTick(int $tickDiff = 1): bool
    {
        $this->setNameTag($this->getLeaderboardText());
        return parent::entityBaseTick($tickDiff);
    }

    public function attack(EntityDamageEvent $source): void
    {
        if($source instanceof EntityDamageByEntityEvent){
            if($source->getDamager() instanceof Player){
                return;
            }
            return;
        }
    }

    protected function updateFallState(float $distanceThisTick, bool $onGround) : void{
        $this->resetFallDistance();
    }

    private function getLeaderboardText(): string
    {
        return KingsUHC::getInstance()->getSqliteProvider()->getGlobalTops();
    }
}