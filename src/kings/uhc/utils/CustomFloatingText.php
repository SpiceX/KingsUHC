<?php

namespace kings\uhc\utils;

use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\Player;
use pocketmine\utils\UUID;

class CustomFloatingText
{
    /** @var array */
    public static $store = [];

    /**
     * @param Player $player
     * @param string $text
     * @param Vector3 $position
     */
    public static function create(Player $player, string $text, Vector3 $position): void
    {
        $eid = Entity::$entityCount++;
        self::$store[$player->getName()] = $eid;
        $pk = new AddPlayerPacket();
        $pk->entityRuntimeId = $eid;
        $pk->uuid = UUID::fromRandom();
        $pk->username = $text;
        $pk->entityUniqueId = $eid;
        $pk->position = $position;
        $pk->item = Item::get(Item::AIR);
        $flags =
            1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG |
            1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG |
            1 << Entity::DATA_FLAG_IMMOBILE;

        $pk->metadata = [
            Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
            Entity::DATA_SCALE => [Entity::DATA_TYPE_FLOAT, 0],
        ];
        $player->dataPacket($pk);
    }

    /**
     * @param Player $player
     */
    public static function remove(Player $player)
    {
        if (isset(self::$store[$player->getName()])) {
            $pk = new RemoveActorPacket();
            $pk->entityUniqueId = self::$store[$player->getName()];
            $player->dataPacket($pk);
        }
    }

}