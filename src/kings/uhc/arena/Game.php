<?php

namespace kings\uhc\arena;

use Exception;
use kings\uhc\arena\scenario\Scenarios;
use kings\uhc\KingsUHC;
use kings\uhc\math\Vector3;
use kings\uhc\utils\BossBar;
use kings\uhc\utils\Padding;
use kings\uhc\utils\PluginUtils;
use kings\uhc\utils\Scoreboard;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\entity\object\ItemEntity;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;
use pocketmine\network\mcpe\protocol\SetSpawnPositionPacket;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\tile\Chest;

abstract class Game
{
    /** @var KingsUHC */
    private $plugin;
    /**
     * @var array
     */
    private $gameFileData;

    /**
     * Game constructor.
     * @param KingsUHC $plugin
     * @param array $gameFileData
     */
    public function __construct(KingsUHC $plugin, array $gameFileData)
    {
        $this->plugin = $plugin;
        $this->gameFileData = $gameFileData;
    }

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
        foreach ($this->spectators as $spectator) {
            switch ($id) {
                case Arena::MSG_MESSAGE:
                    $spectator->sendMessage($message);
                    break;
                case Arena::MSG_TIP:
                    $spectator->sendTip($message);
                    break;
                case Arena::MSG_POPUP:
                    $spectator->sendPopup($message);
                    break;
                case Arena::MSG_TITLE:
                    $spectator->sendTitle($message, $subMessage);
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
        $lastIndex = count($lines);
        foreach ($this->scenarios as $selectedScenario) {
            ++$lastIndex;
            $lines[$lastIndex] = "§b- §7$selectedScenario";
        }
        foreach ($this->scoreboards as $scoreboard) {
            /** @var Scoreboard $scoreboard */
            if (!$scoreboard->isSpawned()) {
                $scoreboard->spawn("§l§3§k||§r §l§9Kings§fUHC §3§k||§r");
            }
            $scoreboard->removeLines();
            foreach ($lines as $index => $line) {
                $scoreboard->setScoreLine($index, str_replace(['{KILLS}', '{DISTANCE}'], [$this->killsManager->getKills($scoreboard->getOwner()), round($scoreboard->getOwner()->distance(Vector3::fromString($this->data["center"])), 1)], $line));
            }
        }
    }

    /**
     * @param string $message
     * @param int $padding
     * @param int $life
     */
    public function updateBossbar(string $message, int $padding = 1, int $life = 1)
    {
        foreach ($this->bossbars as $bossbar) {
            /** @var BossBar $bossbar */
            switch ($padding) {
                case Padding::PADDING_CENTER:
                    $bossbar->update(Padding::centerText($message . " | §3" . PluginUtils::getCompassDirection($bossbar->getPlayer()->getYaw() - 90)), $life);
                    break;
                case Padding::PADDING_LINE:
                default:
                    $bossbar->update(Padding::centerLine($message . " | §3" . PluginUtils::getCompassDirection($bossbar->getPlayer()->getYaw() - 90)), $life);
            }

        }
    }

    public function enablePvP(): void
    {
        $this->pvpEnabled = true;
    }

    public function disablePvP(): void
    {
        $this->pvpEnabled = false;
    }

    /**
     * Starts the game
     */
    public function startGame()
    {
        $this->scenarios = $this->voteManager->getSelectedScenarios();
        foreach ($this->scoreboards as $scoreboard) {
            /** @var Scoreboard $scoreboard */
            $scoreboard->spawn("§l§3§k||§r §l§9Kings§fUHC §3§k||§r");
        }
        foreach ($this->bossbars as $bossbar) {
            /** @var BossBar $bossbar */
            $bossbar->spawn();
        }
        foreach ($this->players as $player) {
            /** @var Player $player */
            if (in_array(Scenarios::CAT_EYES, $this->scenarios)) {
                $player->addEffect(new EffectInstance(Effect::getEffect(Effect::NIGHT_VISION), 72000));
            }
            $pk = new GameRulesChangedPacket();
            $pk->gameRules = [
                "showcoordinates" => [
                    1,
                    true
                ]
            ];
            $player->sendDataPacket($pk);
            $player->setGamemode(Player::SURVIVAL);
            $player->teleport(Position::fromObject(Vector3::fromString($this->data["center"]), $this->level));
            $player->teleport($player->asVector3()->add(0, 90, 0));
            $player->getInventory()->addItem(Item::get(Item::GOLD_AXE));
            $player->getInventory()->addItem(Item::get(Item::GOLD_PICKAXE));
            $player->getInventory()->addItem(Item::get(Item::GOLD_SHOVEL));
        }
        $this->plugin->getScheduler()->scheduleRepeatingTask(new class($this->players) extends Task {

            /** @var Player[] */
            private $players;
            /** @var int */
            private $seconds = 0;

            /**
             *  constructor.
             * @param Player[] $players
             */
            public function __construct(array $players)
            {
                $this->players = $players;
            }

            public function onRun(int $currentTick)
            {
                foreach ($this->players as $player) {
                    $progress = "§a";
                    for ($i = 0; $i < 40; $i++) {
                        $progress .= ($i === $this->seconds ? "§7||" : "||");
                    }
                    $player->sendPopup("§aElytra Power: " . $progress);
                }
                $this->seconds++;
                if ($this->seconds === 40) {
                    foreach ($this->players as $player) {
                        if (!$player instanceof Player) {
                            continue;
                        }
                        if ($player->getArmorInventory()->getChestplate()->getId() === Item::ELYTRA) {
                            $player->getArmorInventory()->setChestplate(Item::get(Item::AIR));
                        }
                        $player->getCraftingGrid()->clearAll();
                        foreach ($player->getInventory()->getContents() as $item) {
                            if ($item->getId() === Item::ELYTRA) {
                                $player->getInventory()->removeItem($item);
                            }
                        }
                        foreach ($player->getCursorInventory()->getContents() as $item) {
                            if ($item->getId() === Item::ELYTRA) {
                                $player->getInventory()->removeItem($item);
                            }
                        }
                        foreach ($player->getLevel()->getEntities() as $entity) {
                            if ($entity instanceof ItemEntity && $entity->getItem()->getId() === Item::ELYTRA) {
                                $entity->close();
                            }
                        }
                        foreach ($player->getLevel()->getTiles() as $tile) {
                            if ($tile instanceof Chest) {
                                foreach ($tile->getInventory()->getContents() as $item) {
                                    if ($item->getId() === Item::ELYTRA) {
                                        $tile->getInventory()->removeItem($item);
                                    }
                                }
                            }
                        }
                        $player->sendMessage("§eElytra power has gone!");
                    }
                    KingsUHC::getInstance()->getScheduler()->cancelTask($this->getTaskId());
                }
            }
        }, 20);
        $this->phase = Arena::PHASE_GAME;
        $this->pvpEnabled = false;
        $this->broadcastMessage("§aGame Started!", Arena::MSG_TITLE);
        $this->broadcastMessage("§6§l» §r§aPvP will be enabled in 5 minutes.", Arena::MSG_MESSAGE);
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
            $player->sendTitle("§aVictory!", "§7You are the winner");
            $this->plugin->getSqliteProvider()->addWin($player);
            $this->broadcastMessage("§6==================\n" .
                "  §6Winner: §7{$player->getName()}\n" .
                "  §6Kills: §7" . $this->killsManager->getKills($player) . "\n" .
                "  §6==================\n"
            );
            $this->plugin->getServer()->broadcastMessage("§6§l» §r§7Player §6{$player->getName()}§7 won the game at {$this->level->getFolderName()}!");
        } else {
            foreach ($this->players as $player) {
                $player->sendTitle("§cGAME OVER!", "§7Good luck next time");
                $this->plugin->getSqliteProvider()->addLose($player);
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
        $pk = new GameRulesChangedPacket();
        $pk->gameRules = [
            "showcoordinates" => [
                0,
                false
            ]
        ];
        $player->sendDataPacket($pk);
        $player->removeAllEffects();
        $player->setGamemode($this->plugin->getServer()->getDefaultGamemode());
        $player->setHealth(20);
        $this->scoreboards[$player->getName()]->despawn();
        unset($this->scoreboards[$player->getName()]);
        unset($this->bossbars[$player->getName()]);
        if (isset($this->spectators[$player->getName()])) {
            unset($this->spectators[$player->getName()]);
        }
        $player->setFood(20);
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        $pk = new SetSpawnPositionPacket();
        $pk->spawnType = SetSpawnPositionPacket::TYPE_WORLD_SPAWN;
        $pk->x = $pk->x2 = Server::getInstance()->getDefaultLevel()->getSpawnLocation()->getX();
        $pk->y = $pk->y2 = Server::getInstance()->getDefaultLevel()->getSpawnLocation()->getY();
        $pk->z = $pk->z2 = Server::getInstance()->getDefaultLevel()->getSpawnLocation()->getZ();
        $pk->dimension = DimensionIds::OVERWORLD;
        $player->sendDataPacket($pk);
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