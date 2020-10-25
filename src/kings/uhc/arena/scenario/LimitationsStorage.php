<?php

namespace kings\uhc\arena\scenario;

use kings\uhc\arena\Arena;
use pocketmine\Player;

class LimitationsStorage
{

    public const DIAMOND_TYPE = "Diamond";
    public const GOLD_TYPE = "Gold";
    public const IRON_TYPE = "Iron";

    /** @var array */
    public $diamondCount = [];
    /** @var array */
    public $ironCount = [];
    /** @var array */
    public $goldCount = [];
    /** @var Arena */
    private $arena;

    /**
     * LimitationsStorage constructor.
     * @param Arena $arena
     */
    public function __construct(Arena $arena)
    {
        $this->arena = $arena;
    }

    public function addOreCount(Player $player, string $oreType)
    {
        switch ($oreType) {
            case self::DIAMOND_TYPE:
                if (!isset($this->diamondCount[$player->getName()])) {
                    $this->diamondCount[$player->getName()] = 1;
                    return;
                }
                $this->diamondCount[$player->getName()]++;
                break;
            case self::IRON_TYPE:
                if (!isset($this->ironCount[$player->getName()])) {
                    $this->ironCount[$player->getName()] = 1;
                    return;
                }
                $this->ironCount[$player->getName()]++;
                break;
            case self::GOLD_TYPE:
                if (!isset($this->goldCount[$player->getName()])) {
                    $this->goldCount[$player->getName()] = 1;
                    return;
                }
                $this->goldCount[$player->getName()]++;
                break;
        }
    }

    public function canBreakOre(Player $player, string $oreType){
        switch ($oreType) {
            case self::DIAMOND_TYPE:
                $count = $this->diamondCount[$player->getName()] ?? 0;
                return $count < 16;
            case self::IRON_TYPE:
                $count = $this->ironCount[$player->getName()] ?? 0;
                return $count < 64;
            case self::GOLD_TYPE:
                $count = $this->goldCount[$player->getName()] ?? 0;
                return $count < 32;
            default:
                return true;
        }
    }

    public function getOreCount(Player $player, string $oreType){
        switch ($oreType) {
            case self::DIAMOND_TYPE:
                return $this->diamondCount[$player->getName()] ?? 0;
            case self::IRON_TYPE:
                return $this->ironCount[$player->getName()] ?? 0;
            case self::GOLD_TYPE:
                return $this->goldCount[$player->getName()] ?? 0;
            default:
                return true;
        }
    }


    public function reload()
    {
        $this->diamondCount = [];
        $this->ironCount = [];
        $this->goldCount = [];
    }
}