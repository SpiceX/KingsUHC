<?php


namespace kings\uhc\arena\utils;


use kings\uhc\arena\Arena;
use kings\uhc\arena\scenario\Scenarios;
use pocketmine\Player;

class VoteManager
{
    /** @var Arena */
    private $arena;
    /** @var Integer[] */
    private $votes = [];
    /** @var Player[] */
    private $players = [];

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
     * @param string $scenario
     */
    public function addVote(Player $player, string $scenario)
    {
        $this->players[$player->getName()] = $scenario;
        if (!isset($this->votes[$scenario])) {
            $this->votes[$scenario] = 1;
            return;
        }
        $this->votes[$scenario]++;
    }

    public function reduceVote(Player $player, string $scenario)
    {
        unset($this->players[$player->getName()]);
        if (isset($this->votes[$scenario])) {
            $this->votes[$scenario]--;
        }
    }

    /**
     * @param Player $player
     * @return bool
     */
    public function hasVoted(Player $player)
    {
        return isset($this->players[$player->getName()]);
    }

    /**
     * @return array
     */
    public function getSelectedScenarios(): array
    {
        $selectedScenarios = [];
        $maxScenarios = $this->arena->plugin->getDataProvider()->getMaxScenarios();
        arsort($this->votes);
        $scenarios = array_keys($this->votes);
        for ($i = 0; $i < $maxScenarios; $i++) {
            $selectedScenarios[] = $scenarios[$i] ?? Scenarios::getRandomScenario();
        }
        return $selectedScenarios;
    }

    /**
     * @param Player $player
     * @return Player|null
     */
    public function getScenarioVoted(Player $player)
    {
        return $this->players[$player->getName()] ?? null;
    }

    public function reload()
    {
        $this->votes = [];
    }
}