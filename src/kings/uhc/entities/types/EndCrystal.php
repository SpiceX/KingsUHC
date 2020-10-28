<?php

namespace kings\uhc\entities\types;

use kings\uhc\arena\Arena;
use kings\uhc\KingsUHC;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\Level;
use pocketmine\level\particle\GenericParticle;
use pocketmine\level\particle\Particle;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\Player;

class EndCrystal extends Entity
{

    public const TAG_SHOW_BOTTOM = "ShowBottom";

    public const NETWORK_ID = self::ENDER_CRYSTAL;

    public $height = 0.98;
    public $width = 0.98;
    /** @var int */
    private $radius;

    public function __construct(Level $level, CompoundTag $nbt)
    {
        $this->radius = 0;
        parent::__construct($level, $nbt);
    }

    public function initEntity(): void
    {
        if (!$this->namedtag->hasTag(self::TAG_SHOW_BOTTOM, ByteTag::class)) {
            $this->namedtag->setByte(self::TAG_SHOW_BOTTOM, 0);
        }
        $this->setNameTagAlwaysVisible(true);
        $this->setNameTagVisible(true);
        $this->radius = 0;
        parent::initEntity();
    }

    public function isShowingBottom(): bool
    {
        return boolval($this->namedtag->getByte(self::TAG_SHOW_BOTTOM));
    }

    /**
     * @param bool $value
     */
    public function setShowingBottom(bool $value)
    {
        $this->namedtag->setByte(self::TAG_SHOW_BOTTOM, intval($value));
    }

    /**
     * @param Vector3 $pos
     */
    public function setBeamTarget(Vector3 $pos)
    {
        $this->namedtag->setTag(new ListTag("BeamTarget", [new DoubleTag("", $pos->getX()), new DoubleTag("", $pos->getY()), new DoubleTag("", $pos->getZ())]));
    }

    public function attack(EntityDamageEvent $source): void
    {
        if ($source instanceof EntityDamageByEntityEvent) {
            $source->setCancelled(true);
            $player = $source->getDamager();
            if ($player instanceof Player) {
                if (!KingsUHC::getInstance()->getSqliteProvider()->verifyPlayerInDB($player)) {
                    KingsUHC::getInstance()->getSqliteProvider()->addPlayer($player);
                }
                if (KingsUHC::getInstance()->getJoinGameQueue()->inQueue($player)) {
                    $player->sendMessage("§c§l» §r§7You are in a queue");
                    return;
                }
                /** @var Arena $arena */
                $arena = KingsUHC::getInstance()->getArenaManager()->getAvailableArena();
                if ($arena !== null) {
                    KingsUHC::getInstance()->getJoinGameQueue()->joinToQueue($player, $arena);
                } else {
                    KingsUHC::getInstance()->getFormManager()->sendAvailableArenaNotFound($player);
                }
                return;
            }
        }
    }

    public function entityBaseTick(int $tickDiff = 1): bool
    {
        $size = 1.2;
        $x = $this->getX();
        $y = $this->getY();
        $z = $this->getZ();
        $a = cos(deg2rad($this->radius / 0.09)) * $size;
        $b = sin(deg2rad($this->radius / 0.09)) * $size;
        $c = cos(deg2rad($this->radius / 0.3)) * $size;
        $this->getLevel()->addParticle(new GenericParticle(new Vector3($x - $a, $y + $c + 1.4, $z - $b), Particle::TYPE_SHULKER_BULLET));
        $this->getLevel()->addParticle(new GenericParticle(new Vector3($x + $a, $y + $c + 1.4, $z + $b), Particle::TYPE_SHULKER_BULLET));
        $this->radius++;
        $availableArenas = (KingsUHC::getInstance()->getArenaManager()->getAvailableArena() !== null) ? "§aAvailable Arenas" : "§cRunning Game";
        $playing = KingsUHC::getInstance()->getArenaManager()->getTotalPlaying();
        $spectating = KingsUHC::getInstance()->getArenaManager()->getTotalSpectating();
        $this->setNameTag("§l§5CLICK TO JOIN A GAME\n§r" . $availableArenas . "\n" .
            "§fPlaying: §a" . $playing . "\n" .
            "§fSpectating: §a" . $spectating
        );
        $viewers = $this->getViewers();
        $this->setBeamTarget($viewers[0] ?? $this->asVector3());
        return parent::entityBaseTick($tickDiff);
    }
}
