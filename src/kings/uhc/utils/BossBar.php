<?php

declare(strict_types=1);

namespace kings\uhc\utils;

use pocketmine\entity\Attribute;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityIds;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\Player;

class BossBar extends Vector3
{
    /** @var float */
    protected $healthPercent = 0, $maxHealthPercent = 1;
    /** @var int */
    protected $entityId;
    /** @var array */
    protected $metadata = [];
    /** @var Player */
    private $player;

    public function __construct(Player $player)
    {
        parent::__construct(0, 255);

        $flags = (
            (1 << Entity::DATA_FLAG_INVISIBLE) |
            (1 << Entity::DATA_FLAG_IMMOBILE)
        );
        $this->metadata = [
            Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
            Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, '']
        ];

        $this->entityId = Entity::$entityCount++;

        $this->setHealthPercent(1, 1);
        $this->player = $player;
    }

    public function setTitle(string $title): void
    {
        $this->setMetadata(Entity::DATA_NAMETAG, Entity::DATA_TYPE_STRING, $title);
    }

    public function update($title = '', $hp = 100): void
    {
        $pk = new BossEventPacket();
        $pk->bossEid = $this->entityId;
        $pk->eventType = BossEventPacket::TYPE_TITLE;
        $pk->healthPercent = $hp ?? $this->getHealthPercent();
        $pk->title = $title;
        $pk2 = clone $pk;
        $pk2->eventType = BossEventPacket::TYPE_HEALTH_PERCENT;
        $this->player->dataPacket($pk);
        $this->player->dataPacket($pk2);
        $this->player->dataPacket($this->getHealthPacket());
        $mpk = new SetActorDataPacket();
        $mpk->entityRuntimeId = $this->entityId;
        $mpk->metadata = $this->metadata;
        $this->player->dataPacket($mpk);
    }

    public function getHealthPercent(): float
    {
        return $this->healthPercent;
    }

    public function setHealthPercent(?float $hp = 1.0, ?float $maxHp = 1.0): void
    {
        if ($maxHp !== null) {
            $this->maxHealthPercent = $maxHp;
        }

        if ($hp !== null) {
            if ($hp > $this->maxHealthPercent) {
                $this->maxHealthPercent = $hp;
            }

            $this->healthPercent = $hp;
        }

    }

    /**
     * @return bool
     */
    public function isSpawned(): bool
    {
        return $this->player !== null;
    }

    public function getTitle(): string
    {
        return $this->getMetadata(Entity::DATA_NAMETAG);
    }

    /**
     * @param int $key
     * @return mixed
     */
    public function getMetadata(int $key)
    {
        return isset($this->metadata[$key]) ? $this->metadata[$key][1] : null;
    }

    public function setMetadata(int $key, int $dtype, $value): void
    {
        $this->metadata[$key] = [$dtype, $value];
    }

    protected function getHealthPacket(): UpdateAttributesPacket
    {
        /** @var Attribute $attr */
        $attr = Attribute::getAttribute(Attribute::HEALTH);
        $attr->setMaxValue($this->maxHealthPercent);
        $attr->setValue($this->healthPercent);

        $pk = new UpdateAttributesPacket();
        $pk->entityRuntimeId = $this->entityId;
        $pk->entries = [$attr];

        return $pk;
    }

    public function getMaxHealthPercent(): float
    {
        return $this->maxHealthPercent;
    }

    public function spawn(): void
    {
        $pk = new AddActorPacket();
        $pk->entityRuntimeId = $this->entityId;
        $pk->type = AddActorPacket::LEGACY_ID_MAP_BC[EntityIds::SHULKER];
        $pk->metadata = $this->metadata;
        $pk->position = $this;

        $this->player->dataPacket($pk);
        $this->player->dataPacket($this->getHealthPacket());

        $pk2 = new BossEventPacket();
        $pk2->bossEid = $this->entityId;
        $pk2->eventType = BossEventPacket::TYPE_SHOW;
        $pk2->title = $this->getTitle();
        $pk2->healthPercent = $this->healthPercent;
        $pk2->color = 0;
        $pk2->overlay = 0;
        $pk2->unknownShort = 0;

        $this->player->dataPacket($pk2);
    }

    public function despawn(): void
    {
        $pk = new BossEventPacket();
        $pk->bossEid = $this->entityId;
        $pk->eventType = BossEventPacket::TYPE_HIDE;

        $this->player->dataPacket($pk);

        $pk2 = new RemoveActorPacket();
        $pk2->entityUniqueId = $this->entityId;
        $this->player->dataPacket($pk2);
    }

    /**
     * @return Player
     */
    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function __destruct()
    {
        $this->despawn();
        unset($this->player);
    }

}