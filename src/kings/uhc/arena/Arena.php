<?php

/**
 * Copyright 2020-2022 kings
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace kings\uhc\arena;

use Exception;
use kings\uhc\KingsUHC;
use kings\uhc\math\Vector3;
use kings\uhc\utils\BossBar;
use kings\uhc\utils\PluginUtils;
use kings\uhc\utils\Scoreboard;
use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\AxisAlignedBB;
use pocketmine\Player;


class Arena implements Listener
{

    const MSG_MESSAGE = 0;
    const MSG_TIP = 1;
    const MSG_POPUP = 2;
    const MSG_TITLE = 3;

    const PHASE_LOBBY = 0;
    const PHASE_GAME = 1;
    const PHASE_RESTART = 2;

    /** @var KingsUHC $plugin */
    public $plugin;
    /** @var int $maxX */
    public $maxX = 100;
    /** @var int $minX */
    public $minX = -1000;
    /** @var int $maxZ */
    public $maxZ = 1000;
    /** @var int $minZ */
    public $minZ = -1000;
    /** @var ArenaScheduler $scheduler */
    public $scheduler;
    /** @var MapReset $mapReset */
    public $mapReset;
    /** @var int $phase */
    public $phase = 0;
    /** @var bool $pvpEnabled */
    public $pvpEnabled = false;
    /** @var bool $deathmatch */
    public $deathmatch = false;
    /** @var array $data */
    public $data = [];
    /** @var bool $setting */
    public $setup = false;
    /** @var Player[] $players */
    public $players = [];
    /** @var Player[] $toRespawn */
    public $toRespawn = [];
    /** @var Level $level */
    public $level = null;
    /** @var BossBar */
    public $bossbar;
    /** @var array */
    private $scenarios = [];
    /** @var array */
    private $scenarioVotes = [];
    /** @var Scoreboard[] */
    private $scoreboards = [];

    /**
     * Arena constructor.
     * @param KingsUHC $plugin
     * @param array $arenaFileData
     */
    public function __construct(KingsUHC $plugin, array $arenaFileData)
    {
        $this->plugin = $plugin;
        $this->data = $arenaFileData;
        $this->setup = !$this->enable(false);
        $this->plugin->getScheduler()->scheduleRepeatingTask($this->scheduler = new ArenaScheduler($this), 20);
        $this->bossbar = new BossBar("§a...", 1, 1);
        if ($this->setup) {
            if (empty($this->data)) {
                $this->createBasicData();
            }
        } else {
            $this->loadArena();
        }
    }

    /**
     * @param bool $loadArena
     * @return bool $isEnabled
     */
    public function enable(bool $loadArena = true): bool
    {
        if (empty($this->data)) {
            return false;
        }
        if ($this->data["level"] == null) {
            return false;
        }
        if (!$this->plugin->getServer()->isLevelGenerated($this->data["level"])) {
            return false;
        }
        if (!is_int($this->data["slots"])) {
            return false;
        }
        $this->data["enabled"] = true;
        $this->setup = false;
        if ($loadArena) $this->loadArena();
        return true;
    }

    /**
     * @param bool $restart
     */
    public function loadArena(bool $restart = false)
    {
        if (!$this->data["enabled"]) {
            $this->plugin->getLogger()->error("Can not load arena: Arena is not enabled!");
            return;
        }

        if (!$this->mapReset instanceof MapReset) {
            $this->mapReset = new MapReset($this);
        }

        if (!$restart) {
            $this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);
        } else {
            $this->scheduler->reloadTimer();
            $this->level = $this->mapReset->loadMap($this->data["level"]);
        }

        if (!$this->level instanceof Level) {
            $level = $this->mapReset->loadMap($this->data["level"]);
            if (!$level instanceof Level) {
                $this->plugin->getLogger()->error("Arena level wasn't found. Try save level in setup mode.");
                $this->setup = true;
                return;
            }
            $this->level = $level;
        }


        $this->phase = static::PHASE_LOBBY;
        $this->players = [];
        $this->scoreboards = [];
        $this->scenarioVotes = [];
    }

    /**
     * Create the basic data for an arena
     */
    private function createBasicData()
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
     */
    public function joinToArena(Player $player)
    {
        if (!$this->data["enabled"]) {
            $player->sendMessage("§c§l» §7UHC Arena is under setup!");
            return;
        }

        if (count($this->players) >= $this->data["slots"]) {
            $player->sendMessage("§c§l» §7Arena is full!");
            return;
        }

        if ($this->inGame($player)) {
            $player->sendMessage("§c§l» §7You are already in game!");
            return;
        }

        if ($this->phase != self::PHASE_LOBBY) {
            $player->sendMessage("§c§l» §7This game has already started!");
            return;
        }

        $selected = false;
        for ($lS = 1; $lS <= $this->data["slots"]; $lS++) {
            if (!$selected) {
                if (!isset($this->players[$index = "spawn-{$lS}"])) {
                    $this->players[$index] = $player;
                    $selected = true;
                }
            }
        }
        $player->teleport(Position::fromObject(Vector3::fromString($this->data["center"]), $this->level)->add(0, 90, 0));
        //$player->teleport($this->level->getSafeSpawn());
        $this->scoreboards[$player->getName()] = new Scoreboard($player);

        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        $player->getArmorInventory()->setChestplate(Item::get(Item::ELYTRA));
        $player->setMotion($player->getDirectionVector()->multiply(0.5)->add(0, 3, 0));
        $player->setGamemode(Player::SURVIVAL);
        $player->setHealth(20);
        $player->setFood(20);

        $this->broadcastMessage("§6§l» §r§7Player §e{$player->getName()}§7 joined! §6(" . count($this->players) . "/{$this->data["slots"]})");
    }

    /**
     * @param Player $player
     * @return bool $isInGame
     */
    public function inGame(Player $player): bool
    {
        switch ($this->phase) {
            case self::PHASE_LOBBY:
                $inGame = false;
                foreach ($this->players as $players) {
                    if ($players->getId() == $player->getId()) {
                        $inGame = true;
                    }
                }
                return $inGame;
            default:
                return isset($this->players[$player->getName()]);
        }
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
                case self::MSG_MESSAGE:
                    $player->sendMessage($message);
                    break;
                case self::MSG_TIP:
                    $player->sendTip($message);
                    break;
                case self::MSG_POPUP:
                    $player->sendPopup($message);
                    break;
                case self::MSG_TITLE:
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
            if (!$scoreboard->isSpawned()){
                $scoreboard->spawn("§9§l");
            }
            $scoreboard->removeLines();
            foreach ($lines as $index => $line) {
                $scoreboard->setScoreLine($index, $line);
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
            $this->bossbar->showTo($player);
            $player->getInventory()->addItem(Item::get(Item::GOLD_AXE));
            $player->getInventory()->addItem(Item::get(Item::GOLD_PICKAXE));
            $player->getInventory()->addItem(Item::get(Item::GOLD_SHOVEL));
        }


        $this->players = $players;
        $this->phase = 1;
        $this->pvpEnabled = false;
        $this->broadcastMessage("§aGame Started!", self::MSG_TITLE);
        $this->broadcastMessage("§6§l» §r§aPvP will be enabled in 6 minutes.", self::MSG_MESSAGE);
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
            $this->phase = self::PHASE_RESTART;
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
        $this->phase = self::PHASE_RESTART;
    }

    /**
     * @param int $blocks
     */
    public function shrinkEdge(int $blocks)
    {
        $this->maxX = $this->maxX - $blocks;
        $this->minX = $this->minX - $blocks;
        $this->maxZ = $this->maxZ - $blocks;
        $this->minZ = $this->minZ - $blocks;
    }

    /**
     * @return bool $end
     */
    public function checkEnd(): bool
    {
        return count($this->players) <= 1;
    }

    /**
     * @param PlayerExhaustEvent $event
     */
    public function onExhaust(PlayerExhaustEvent $event)
    {
        $player = $event->getPlayer();

        if (!$player instanceof Player) return;

        if ($this->inGame($player) && $this->phase == self::PHASE_LOBBY) {
            $event->setCancelled(true);
        }
    }

    /**
     * @param PlayerDeathEvent $event
     */
    public function onDeath(PlayerDeathEvent $event)
    {
        $player = $event->getPlayer();

        if (!$this->inGame($player)) return;

        $event->setDrops([]);
        $this->toRespawn[$player->getName()] = $player;
        $this->disconnectPlayer($player, "", true);
        $this->broadcastMessage("§6§l» §r§7{$this->plugin->getServer()->getLanguage()->translate($event->getDeathMessage())} §7(" . count($this->players) . "/{$this->data["slots"]})");
        $event->setDeathMessage("");
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
                unset($this->players[$player->getName()]);
                break;
        }
        $player->removeAllEffects();
        $player->setGamemode($this->plugin->getServer()->getDefaultGamemode());
        $player->setHealth(20);
        $this->scoreboards[$player->getName()]->despawn();
        unset($this->scoreboards[$player->getName()]);
        $this->bossbar->hideFrom($player);
        $player->setFood(20);
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        $player->teleport($this->plugin->getServer()->getDefaultLevel()->getSpawnLocation());
        if (!$death) {
            $this->broadcastMessage("§6§l» §r§7 Player {$player->getName()} left the game. §7[" . count($this->players) . "/{$this->data["slots"]}]");
        }

        if ($quitMsg != "") {
            $player->sendMessage("§6§l» §r§7$quitMsg");
        }
    }

    /**
     * @param EntityDamageEvent $event
     */
    public function onDamage(EntityDamageEvent $event)
    {
        $player = $event->getEntity();
        if ($player instanceof Player) {
            if (!$this->inGame($player)) return;
        }
        switch ($this->phase) {
            case self::PHASE_LOBBY:
            case self::PHASE_RESTART:
                $event->setCancelled(true);
        }
        if ($event instanceof EntityDamageByEntityEvent) {
            if (!$this->pvpEnabled) {
                $event->setCancelled(true);
                return;
            }
            $damager = $event->getDamager();
            $victim = $event->getEntity();
            if ($event->getFinalDamage() > $victim->getHealth()) {
                if ($damager instanceof Player && $victim instanceof Player) {
                    $event->setCancelled(true);
                    foreach ($victim->getInventory()->getContents(false) as $item) {
                        if ($damager->getInventory()->canAddItem($item)) {
                            $damager->getInventory()->addItem($item);
                        } else {
                            $damager->getLevel()->dropItem($damager->asVector3(), $item);
                        }
                    }
                    $this->toRespawn[$victim->getName()] = $victim;
                    $this->disconnectPlayer($victim, "", true);
                    $this->broadcastMessage("§6uhc » §7{$victim->getName()} has dead §7[" . count($this->players) . "/{$this->data["slots"]}]");
                }
            }
        }
    }

    /**
     * @param PlayerRespawnEvent $event
     */
    public function onRespawn(PlayerRespawnEvent $event)
    {
        $player = $event->getPlayer();
        if (isset($this->toRespawn[$player->getName()])) {
            $event->setRespawnPosition($this->plugin->getServer()->getDefaultLevel()->getSpawnLocation());
            unset($this->toRespawn[$player->getName()]);
        }
    }

    /**
     * @param PlayerQuitEvent $event
     */
    public function onQuit(PlayerQuitEvent $event)
    {
        if ($this->inGame($event->getPlayer())) {
            $this->disconnectPlayer($event->getPlayer());
        }
    }

    /**
     * @param PlayerCommandPreprocessEvent $event
     */
    public function onPlayerCommand(PlayerCommandPreprocessEvent $event)
    {
        if ($this->inGame($event->getPlayer())) {
            $message = $event->getMessage();
            if ($message{0} === "/") {
                $event->setCancelled(true);
                $command = substr($message, 1);
                $args = explode(" ", $command);
                switch ($args[0]) {
                    case 'uhc':
                        if ($args[1] === 'exit') {
                            $this->disconnectPlayer($event->getPlayer(), "§aYou have successfully left the game!");
                        } else {
                            $event->getPlayer()->sendMessage("§cUse /uhr exit to leave the game.");
                        }
                        break;
                    case 'spawn':
                    case 'lobby':
                    case 'hub':
                    default:
                        $event->getPlayer()->sendMessage("§cUse /uhc exit to leave the game.");
                        break;
                }
            }
        }
    }

    /**
     * @param PlayerMoveEvent $event
     */
    public function onMove(PlayerMoveEvent $event)
    {
        $to = $event->getTo();
        if ($this->inGame($event->getPlayer())) {
            $aabb = new AxisAlignedBB($this->minX, 0, $this->minZ, $this->maxX, $to->getLevel()->getWorldHeight(), $this->maxZ);
            if (!$aabb->isVectorInXZ($to)) {
                $event->getPlayer()->sendPopup("§eOUT OF BORDER");
                $event->getPlayer()->sendTitle("§4Warning", "§eYou are out of the edge");
            }
        }
    }

    /**
     * @param BlockBreakEvent $event
     */
    public function onBlockBreak(BlockBreakEvent $event)
    {
        if ($event->isCancelled()) return;
        if (!$this->inGame($event->getPlayer())) return;
        $player = $event->getPlayer();
        $level = $event->getBlock()->getLevel();
        $block = $event->getBlock();
        switch ($block->getId()) {
            case Block::GRASS:
            case Block::GRASS_PATH:
            case Block::TALL_GRASS:
                $event->setCancelled();
                $level->setBlockIdAt($block->getX(), $block->getY(), $block->getZ(), 0);
                $level->setBlockDataAt($block->getX(), $block->getY(), $block->getZ(), 0);
                $level->dropItem($event->getBlock()->asVector3(), Item::get(Item::BREAD, 0, mt_rand(0, 2)));
                break;
            case Block::IRON_ORE:
                $event->setCancelled();
                $level->setBlockIdAt($block->getX(), $block->getY(), $block->getZ(), 0);
                $level->setBlockDataAt($block->getX(), $block->getY(), $block->getZ(), 0);
                if ($player->getInventory()->canAddItem(Item::get(Item::IRON_INGOT))) {
                    $player->getInventory()->addItem(Item::get(Item::IRON_INGOT, 0, mt_rand(1, 5)));
                }
                break;
            case Block::GOLD_ORE:
                $event->setCancelled();
                $level->setBlockIdAt($block->getX(), $block->getY(), $block->getZ(), 0);
                $level->setBlockDataAt($block->getX(), $block->getY(), $block->getZ(), 0);
                if ($player->getInventory()->canAddItem(Item::get(Item::GOLD_INGOT))) {
                    $player->getInventory()->addItem(Item::get(Item::GOLD_INGOT, 0, mt_rand(1, 2)));
                }
                break;
            case Block::DIAMOND_ORE:
                $event->setCancelled();
                $level->setBlockIdAt($block->getX(), $block->getY(), $block->getZ(), 0);
                $level->setBlockDataAt($block->getX(), $block->getY(), $block->getZ(), 0);
                if ($player->getInventory()->canAddItem(Item::get(Item::DIAMOND))) {
                    $player->getInventory()->addItem(Item::get(Item::DIAMOND, 0, mt_rand(1, 2)));
                }
                break;
            case Block::LEAVES:
            case Block::LEAVES2:
                $level->setBlockIdAt($block->getX(), $block->getY(), $block->getZ(), 0);
                $level->setBlockDataAt($block->getX(), $block->getY(), $block->getZ(), 0);
                if (mt_rand(0, 1)) {
                    $level->dropItem($event->getBlock()->asVector3(), Item::get(Item::STEAK, 0, mt_rand(0, 2)));
                } else {
                    $level->dropItem($event->getBlock()->asVector3(), Item::get(Item::APPLE, 0, mt_rand(0, 2)));
                }
                break;
            case Block::COAL_ORE:
                $level->setBlockIdAt($block->getX(), $block->getY(), $block->getZ(), 0);
                $level->setBlockDataAt($block->getX(), $block->getY(), $block->getZ(), 0);
                if ($player->getInventory()->canAddItem(Item::get(Item::TORCH))) {
                    $player->getInventory()->addItem(Item::get(Item::TORCH, 0, mt_rand(1, 4)));
                }
                break;
            case Block::RED_FLOWER:
            case Block::YELLOW_FLOWER:
            case Block::DANDELION:
                $event->setCancelled();
                $level->setBlockIdAt($block->getX(), $block->getY(), $block->getZ(), 0);
                $level->setBlockDataAt($block->getX(), $block->getY(), $block->getZ(), 0);
                if ($player->getInventory()->canAddItem(Item::get(Item::STRING))) {
                    $player->getInventory()->addItem(Item::get(Item::STRING, 0, mt_rand(1, 3)));
                }
                break;
            case Block::GRAVEL:
                $event->setCancelled();
                $level->setBlockIdAt($block->getX(), $block->getY(), $block->getZ(), 0);
                $level->setBlockDataAt($block->getX(), $block->getY(), $block->getZ(), 0);
                if ($player->getInventory()->canAddItem(Item::get(Item::ARROW))) {
                    $player->getInventory()->addItem(Item::get(Item::ARROW, 0, mt_rand(1, 4)));
                }
                break;
            case Block::LAPIS_ORE:
                $level->setBlockIdAt($block->getX(), $block->getY(), $block->getZ(), 0);
                $level->setBlockDataAt($block->getX(), $block->getY(), $block->getZ(), 0);
                if ($player->getInventory()->canAddItem(Item::get(Item::BOOK))) {
                    $player->getInventory()->addItem(Item::get(Item::BOOK, 0, mt_rand(1, 3)));
                }
                break;
            case Block::EMERALD_ORE:
                $event->setCancelled();
                $level->setBlockIdAt($block->getX(), $block->getY(), $block->getZ(), 0);
                $level->setBlockDataAt($block->getX(), $block->getY(), $block->getZ(), 0);
                if ($player->getInventory()->canAddItem(Item::get(Item::ENDER_PEARL))) {
                    $player->getInventory()->addItem(Item::get(Item::ENDER_PEARL, 0, mt_rand(1, 2)));
                }
                break;
        }
        switch ($event->getPlayer()->getInventory()->getItemInHand()->getId()) {
            case Item::WOODEN_AXE:
            case Item::STONE_AXE:
            case Item::GOLD_AXE:
            case Item::IRON_AXE:
            case Item::DIAMOND_AXE:
                $damage = PluginUtils::destroyTree($player, $event->getBlock());
                if ($damage) {
                    $hand = $player->getInventory()->getItemInHand();
                    $hand->setDamage($hand->getDamage());
                    $player->getInventory()->setItemInHand($hand);
                }
                break;
        }

    }


    public function __destruct()
    {
        unset($this->scheduler);
    }

    /**
     * @return array
     */
    public function getScenarios(): array
    {
        return $this->scenarios;
    }
}