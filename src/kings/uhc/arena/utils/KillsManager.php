<?php

namespace kings\uhc\arena\utils;

use kings\uhc\arena\Arena;
use pocketmine\Player;

class KillsManager
{
    /** @var Arena */
    private $arena;
    /** @var Integer[] */
    private $kills = [];

    /**
     * VoteManager constructor.
     * @param Arena $arena
     */
    public function __construct(Arena $arena)
    {
        $this->arena = $arena;
    }

    /**
     * @param Player $player
     */
    public function addKill(Player $player): void
    {
        if (!isset($this->kills[$player->getName()])) {
            $this->kills[$player->getName()] = 1;
            return;
        }
        $this->kills[$player->getName()]++;
    }

    /**
     * @param Player $player
     * @return int
     */
    public function getKills(Player $player): int
    {
        return $this->kills[$player->getName()] ?? 0;
    }

    public function reload()
    {
        $this->kills = [];
    }
}