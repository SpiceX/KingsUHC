<?php


namespace kings\uhc\task;


use Exception;
use kings\uhc\arena\Arena;
use kings\uhc\KingsUHC;
use kings\uhc\utils\Scoreboard;
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
                    1 => '§7---------------',
                    2 => ' §aWaiting players...',
                    3 => " §aMap: §7{$arena->level->getFolderName()}",
                    4 => " §aInQueue: §7(" . count($this->arenas[$arena->level->getFolderName()]) . '/' . (int)$arena->data['slots'] . ')',
                    5 => ' §7---------------',
                ]);
            } else {
                if ($this->startingTimes[$arena->level->getFolderName()] === 0){
                    foreach ($players as $player) {
                        $arena->joinToArena($player);
                        unset($this->scoreboards[$player->getName()]);
                    }
                    $this->arenas[$arena->level->getFolderName()] = [];
                    $this->startingTimes[$arena->level->getFolderName()] = 10;
                }
                $this->startingTimes[$arena->level->getFolderName()]--;
            }

        }
    }

    /**
     * @param Player $player
     * @param Arena $arena
     */
    public function joinToQueue(Player $player, Arena $arena)
    {
        $this->arenas[$arena->level->getName()][] = $player;
        $this->scoreboards[$player->getName()] = $scoreboard = new Scoreboard($player);
        $scoreboard->spawn("§l§9Kings§fUHC");
    }

    /**
     * @param Player $player
     */
    public function leaveQueue(Player $player)
    {
        foreach ($this->arenas as $arena => $players) {
            foreach ($players as $index => $wantedPlayer) {
                /** @var Player $wantedPlayer */
                if ($player->getId() === $wantedPlayer->getId()) {
                    unset($this->arenas[$arena][$index]);
                }
            }
        }
        if (isset($this->scoreboards[$player->getName()])){
            unset($this->scoreboards[$player->getName()]);
        }
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