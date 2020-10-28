<?php


namespace kings\uhc\entities\types;


use kings\uhc\KingsUHC;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\Level;
use pocketmine\level\particle\GenericParticle;
use pocketmine\level\particle\Particle;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;

class Leaderboard extends Human
{

    /** @var int|mixed */
    private $radius;

    public function __construct(Level $level, CompoundTag $nbt)
    {
        parent::__construct($level, $nbt);
        $this->setSkin(new Skin('Standard_Custom', str_repeat("\x00", 8192), '', 'geometry.humanoid.custom'));
        $this->sendSkin();
        $this->propertyManager->setPropertyValue(Entity::DATA_BOUNDING_BOX_WIDTH, Entity::DATA_TYPE_FLOAT, 0);
        $this->propertyManager->setPropertyValue(Entity::DATA_BOUNDING_BOX_HEIGHT, Entity::DATA_TYPE_FLOAT, 0);
        $this->radius = 0;
    }

    public function initEntity(): void
    {
        $this->radius = 0;
        parent::initEntity();
    }

    public function entityBaseTick(int $tickDiff = 1): bool
    {
        $this->setNameTag($this->getLeaderboardText());
        $size = 0.8;
        $a = cos(deg2rad($this->radius / 0.04)) * $size;
        $b = sin(deg2rad($this->radius / 0.04)) * $size;
        $c = cos(deg2rad($this->radius / 0.04)) * 0.6;
        $d = sin(deg2rad($this->radius / 0.04)) * 0.6;
        $x = $this->getX();
        $y = $this->getY();
        $z = $this->getZ();
        $this->level->addParticle(new GenericParticle(new Vector3($x - $b, $y + $c + $d + 1.2, $z - $a), Particle::TYPE_CONDUIT));
        $this->level->addParticle(new GenericParticle(new Vector3($x + $a, $y + $c + $d + 1.2, $z + $b), Particle::TYPE_CONDUIT));
        $this->level->addParticle(new GenericParticle(new Vector3($x + $b, $y + $c + $d + 1.2, $z - $a), Particle::TYPE_CONDUIT));
        $this->level->addParticle(new GenericParticle(new Vector3($x + $a, $y + $c + $d + 1.2, $z - $b), Particle::TYPE_CONDUIT));
        $this->level->addParticle(new GenericParticle(new Vector3($x + $a, $y + 2, $z + $b), Particle::TYPE_CONDUIT));
        $this->radius++;
        return parent::entityBaseTick($tickDiff);
    }

    public function attack(EntityDamageEvent $source): void
    {
        if ($source instanceof EntityDamageByEntityEvent) {
            if ($source->getDamager() instanceof Player) {
                return;
            }
            return;
        }
    }

    protected function updateFallState(float $distanceThisTick, bool $onGround): void
    {
        $this->resetFallDistance();
    }

    private function getLeaderboardText(): string
    {
        return KingsUHC::getInstance()->getSqliteProvider()->getGlobalTops();
    }
}