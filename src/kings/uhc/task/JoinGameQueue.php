<?php

namespace kings\uhc\task;

use Exception;
use kings\uhc\arena\Arena;
use kings\uhc\KingsUHC;
use kings\uhc\utils\Scoreboard;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\scheduler\Task;

class JoinGameQueue extends Task
{
    /** @var array */
    public $arenas = [];
    /** @var Scoreboard[] */
    public $scoreboards = [];
    /** @var KingsUHC */
    private $plugin;
    /** @var array */
    public $startingTimes = [];

    /**
     * JoinGameQueue constructor.
     * @param KingsUHC $plugin
     */
    public function __construct(KingsUHC $plugin)
    {
        $this->plugin = $plugin;
    }


    /**
     * @param int $currentTick
     * @throws Exception
     */
    public function onRun(int $currentTick)
    {
        foreach ($this->arenas as $arena => $players) {
            $arena = $this->plugin->getArenaManager()->getArena($arena);
            if (count($players) < 10) {
                $this->updateScoreboards([
                    1 => '§7------------------',
                    2 => ' §aWaiting players...',
                    3 => " §aMap: §7{$arena->level->getFolderName()}",
                    4 => " §aInQueue: §7(" . count($this->arenas[$arena->level->getFolderName()]) . '/' . (int)$arena->data['slots'] . ')',
                    5 => ' §7-----------------',
                ]);
            } else {
                $this->updateScoreboards([
                    1 => '§7------------------',
                    2 => ' §aStarting game in: ' . $this->startingTimes[$arena->level->getFolderName()],
                    3 => " §aMap: §7{$arena->level->getFolderName()}",
                    4 => " §aInQueue: §7(" . count($this->arenas[$arena->level->getFolderName()]) . '/' . (int)$arena->data['slots'] . ')',
                    5 => ' §7-----------------',
                ]);
                if ($this->startingTimes[$arena->level->getFolderName()] === 0) {
                    foreach ($players as $player) {
                        $arena->joinToArena($player);
                        $this->leaveQueue($player);
                    }
                    $arena->startGame();
                    $this->arenas[$arena->level->getFolderName()] = [];
                    $this->startingTimes[$arena->level->getFolderName()] = 10;
                }
                $this->startingTimes[$arena->level->getFolderName()]--;
            }

        }
    }

    public function getArenaByPlayer(Player $player)
    {
        foreach ($this->arenas as $arena => $players) {
            foreach ($players as $p) {
                if ($player->getName() === $p->getName()) {
                    return $this->plugin->getArenaManager()->getArena($arena);
                }
            }
        }
        return null;
    }

    /**
     * @param Player $player
     * @param Arena $arena
     */
    public function joinToQueue(Player $player, Arena $arena)
    {
        $this->arenas[$arena->level->getFolderName()][] = $player;
        $this->scoreboards[$player->getName()] = $scoreboard = new Scoreboard($player);
        $scoreboard->spawn("§l§3§k||§r §l§9Kings§fUHC §3§k||§r");
        $player->getInventory()->setItem(0, Item::get(Item::ENCHANTED_BOOK)->setCustomName('§9Vote'));
        $player->getInventory()->setItem(8, Item::get(Item::REDSTONE)->setCustomName('§cLeave Queue'));
        $player->sendMessage("§l§a» §r§7You have joined a queue for UHC.");
    }

    /**
     * @param Player $player
     */
    public function leaveQueue(Player $player)
    {
        $player->getInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        foreach ($this->arenas as $arena => $players) {
            foreach ($players as $index => $wantedPlayer) {
                /** @var Player $wantedPlayer */
                if ($player->getId() === $wantedPlayer->getId()) {
                    unset($this->arenas[$arena][$index]);
                    $arena = $this->plugin->getArenaManager()->getArena($arena);
                    $scenario = $arena->voteManager->getScenarioVoted($player);
                    if ($scenario !== null) {
                        $arena->voteManager->reduceVote($player, $scenario);
                    }
                }
            }
        }
        if (isset($this->scoreboards[$player->getName()])) {
            $this->scoreboards[$player->getName()]->despawn();
            unset($this->scoreboards[$player->getName()]);
        }
        $player->sendMessage("§a§l» §r§7You have left the queue.");
    }

    /**
     * @param array $lines
     * @throws Exception
     */
    private function updateScoreboards(array $lines)
    {
        foreach ($this->scoreboards as $scoreboard) {
            $scoreboard->removeLines();
            foreach ($lines as $index => $line) {
                $scoreboard->setScoreLine($index, $line);
            }
        }
    }
}