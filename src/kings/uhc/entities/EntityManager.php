<?php

namespace kings\uhc\entities;

use kings\uhc\entities\types\Creeper;
use kings\uhc\entities\types\Leaderboard;
use kings\uhc\KingsUHC;
use pocketmine\entity\Entity;

class EntityManager
{
    /**
     * @var KingsUHC
     */
    private $plugin;

    public function __construct(KingsUHC $plugin)
    {
        $this->plugin = $plugin;
        $this->init();
    }

    public function init()
    {
        Entity::registerEntity(Leaderboard::class, true, ['Leaderboard']);
        Entity::registerEntity(Creeper::class, true, ['Creeper', 'minecraft:creeper']);
    }

}