<?php


namespace kings\uhc\arena;


use Exception;
use kings\uhc\utils\BossBar;
use kings\uhc\utils\Padding;
use pocketmine\item\Item;
use pocketmine\Player;

abstract class Game
{

    /**
     * Create the basic data for an arena
     */
    protected function createBasicData()
    {
        $this->data = [
            "level" => null,
            "slots" => 30,
            "center" => null,
            "enabled" => false,
        ];
    }

    /**
     * @param Player $player
     * @return bool $isInGame
     */
    public function inGame(Player $player): bool
    {
        return isset($this->players[$player->getName()]);
    }

    /**
     * @param Player $player
     * @return bool $isInSpectate
     */
    public function inSpectate(Player $player): bool
    {
        return isset($this->spectators[$player->getName()]);
    }

    /**
     * @param string $message
     * @param int $id
     * @param string $subMessage
     */
    public function broadcastMessage(string $message, int $id = 0, string $subMessage = "")
    {
        foreach ($this->players as $player) {
            switch ($id) {
                case Arena::MSG_MESSAGE:
                    $player->sendMessage($message);
                    break;
                case Arena::MSG_TIP:
                    $player->sendTip($message);
                    break;
                case Arena::MSG_POPUP:
                    $player->sendPopup($message);
                    break;
                case Arena::MSG_TITLE:
                    $player->sendTitle($message, $subMessage);
                    break;
            }
        }
    }

    /**
     * @param array $lines
     * @throws Exception
     */
    public function updateScoreboard(array $lines)
    {
        foreach ($this->scoreboards as $scoreboard) {
            if (!$scoreboard->isSpawned()) {
                $scoreboard->spawn("§9§l");
            }
            $scoreboard->removeLines();
            foreach ($lines as $index => $line) {
                $scoreboard->setScoreLine($index, $line);
            }
        }
    }

    /**
     * @param string $message
     * @param int $padding
     * @param int $life
     */
    public function updateBossbar(string $message, int $padding = 0, int $life = 1)
    {
        foreach ($this->bossbars as $bossbar) {
            /** @var BossBar $bossbar */
            switch ($padding) {
                case Padding::PADDING_CENTER:
                    $bossbar->update(Padding::centerText($message), $life);
                    break;
                case Padding::PADDING_LINE:
                default:
                    $bossbar->update(Padding::centerLine($message), $life);
            }

        }
    }

    /**
     * Starts the game
     */
    public function startGame()
    {
        $players = [];
        foreach ($this->players as $player) {
            $players[$player->getName()] = $player;
            $player->setGamemode(Player::SURVIVAL);
            foreach ($this->bossbars as $bossbar) {
                $bossbar->spawn();
            }
            $player->getInventory()->addItem(Item::get(Item::GOLD_AXE));
            $player->getInventory()->addItem(Item::get(Item::GOLD_PICKAXE));
            $player->getInventory()->addItem(Item::get(Item::GOLD_SHOVEL));
        }

        $this->players = $players;
        $this->phase = 1;
        $this->pvpEnabled = false;
        $this->broadcastMessage("§aGame Started!", Arena::MSG_TITLE);
        $this->broadcastMessage("§6§l» §r§aPvP will be enabled in 6 minutes.", Arena::MSG_MESSAGE);
    }

    /**
     * @param bool $winner
     */
    public function startRestart(bool $winner = true)
    {
        $player = null;
        foreach ($this->players as $p) {
            $player = $p;
        }

        if ($player === null || (!$player instanceof Player) || (!$player->isOnline())) {
            $this->phase = Arena::PHASE_RESTART;
            return;
        }
        if ($winner) {
            $player->sendTitle("§aYOU WON!");
            $this->plugin->getServer()->broadcastMessage("§6uhc » §7Player §6{$player->getName()}§7 won the game at {$this->level->getFolderName()}!");
        } else {
            foreach ($this->players as $player) {
                $player->sendTitle("§cGAME OVER!");
            }
            $this->plugin->getServer()->broadcastMessage("§6§l» §r§7No winners at {$this->level->getFolderName()}!");
        }
        $this->phase = Arena::PHASE_RESTART;
    }

    /**
     * @param Player $player
     * @param string $quitMsg
     * @param bool $death
     */
    public function disconnectPlayer(Player $player, string $quitMsg = "", bool $death = false)
    {
        switch ($this->phase) {
            case Arena::PHASE_LOBBY:
                $index = "";
                foreach ($this->players as $i => $p) {
                    if ($p->getId() == $player->getId()) {
                        $index = $i;
                    }
                }
                if ($index != "") {
                    unset($this->players[$index]);
                }
                break;
            default:
                if (isset($this->players[$player->getName()])) {
                    unset($this->players[$player->getName()]);
                }
                break;
        }
        $player->removeAllEffects();
        $player->setGamemode($this->plugin->getServer()->getDefaultGamemode());
        $player->setHealth(20);
        $this->scoreboards[$player->getName()]->despawn();
        unset($this->scoreboards[$player->getName()]);
        foreach ($this->bossbars as $bossbar) {
            $bossbar->despawn();
        }
        unset($this->bossbars[$player->getName()]);
        if (isset($this->spectators[$player->getName()])) {
            unset($this->spectators[$player->getName()]);
        }
        $player->setFood(20);
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        $player->teleport($this->plugin->getServer()->getDefaultLevel()->getSpawnLocation());
        if (!$death) {
            if (!isset($this->spectators[$player->getName()])) {
                $this->broadcastMessage("§6§l» §r§7 Player {$player->getName()} left the game. §7[" . count($this->players) . "/{$this->data["slots"]}]");
            }
        }

        if ($quitMsg !== "") {
            $player->sendMessage("§6§l» §r§7$quitMsg");
        }
    }

    /**
     * @return bool $end
     */
    public function checkEnd(): bool
    {
        return count($this->players) <= 1;
    }
}