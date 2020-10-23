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

use kings\uhc\arena\utils\KillsManager;
use kings\uhc\arena\utils\MapReset;
use kings\uhc\arena\utils\VoteManager;
use kings\uhc\KingsUHC;
use kings\uhc\utils\BossBar;
use kings\uhc\utils\PluginUtils;
use kings\uhc\utils\Scoreboard;
use pocketmine\block\Block;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Location;
use pocketmine\level\particle\RedstoneParticle;
use pocketmine\level\Position;
use pocketmine\math\AxisAlignedBB;
use pocketmine\Player;


class Arena extends Game implements Listener
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
    public $maxX = 1000;
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
    public $phase = self::PHASE_LOBBY;
    /** @var bool $pvpEnabled */
    public $pvpEnabled = false;
    /** @var array $data */
    public $data = [];
    /** @var bool $setting */
    public $setup = false;
    /** @var Player[] $players */
    public $players = [];
    /** @var Player[] $spectators */
    public $spectators = [];
    /** @var Level $level */
    public $level = null;
    /** @var array */
    public $scenarios = [];
    /** @var Scoreboard[] */
    protected $scoreboards = [];
    /** @var BossBar[] */
    protected $bossbars;
    /** @var KillsManager */
    public $killsManager;
    /** @var VoteManager */
    public $voteManager;
    /** @var array */
    private $previousBlocks;

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
        $this->killsManager = new KillsManager($this);
        $this->voteManager = new VoteManager($this);
        if ($this->setup) {
            if (empty($this->data)) {
                $this->createBasicData();
            }
        } else {
            $this->loadArena();
        }
        parent::__construct($plugin, $arenaFileData);
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

        $this->players[$player->getName()] = $player;
        $this->scoreboards[$player->getName()] = new Scoreboard($player);
        $this->bossbars[$player->getName()] = new BossBar($player);
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        $player->getArmorInventory()->setChestplate(Item::get(Item::ELYTRA));
        $player->knockBack($player, 0.0, $player->getDirectionVector()->getX(), $player->getDirectionVector()->getZ(), 0.5);
        $player->setGamemode(Player::SURVIVAL);
        $player->setHealth(20);
        $player->setFood(20);

        $this->broadcastMessage("§6§l» §r§7Player §e{$player->getName()}§7 joined! §6(" . count($this->players) . "/{$this->data["slots"]})");
    }

    /**
     * @param Player $player
     * @param bool $death
     */
    public function spectateToArena(Player $player, bool $death = false)
    {
        if ($this->inSpectate($player)) {
            $player->sendMessage("§c§l» §7You are already spectating a game!");
            return;
        }
        if ($death) {
            $player->addEffect(new EffectInstance(Effect::getEffect(Effect::BLINDNESS), 20));
            $player->sendTitle("§cGame Over", "§7Good luck next time");
        }
        $this->spectators[$player->getName()] = $player;
        $player->teleport($this->players[array_rand($this->players)]->asPosition());
        $this->scoreboards[$player->getName()] = new Scoreboard($player);
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        $player->getInventory()->setItem(4, Item::get(Item::COMPASS)->setCustomName('§9Players'));
        $player->setGamemode(Player::SPECTATOR);
        $player->setHealth(20);
        $player->setFood(20);
    }

    /**
     * @param int $blocks
     */
    public function shrinkEdge(int $blocks)
    {
        $this->maxX -= $blocks;
        $this->minX += $blocks;
        $this->maxZ -= $blocks;
        $this->minZ += $blocks;
    }

    public function checkPlayersInsideBorder()
    {
        foreach ($this->players as $player) {
            $aabb = new AxisAlignedBB($this->minX, 0, $this->minZ, $this->maxX, $this->level->getWorldHeight(), $this->maxZ);
            if (!$aabb->isVectorInXZ($player->getLocation())) {
                for ($i = 0; $i < 20; ++$i) {
                    $vector = PluginUtils::getRandomVector()->multiply(3);
                    $vector->y = abs($vector->getY());
                    $player->getLevel()->addParticle(new RedstoneParticle($player->getLocation()->add($vector->x, $vector->y, $vector->z)));
                    $player->getLocation()->add($vector->x, $vector->y, $vector->z);
                }
                $player->addEffect(new EffectInstance(Effect::getEffect(Effect::INSTANT_DAMAGE), 1, 0, false));
            }
        }
    }

    /**
     * @param Position $pos
     * @return bool
     */
    public function insideEdge(Position $pos): bool
    {
        if (
            $pos->getX() >= $this->minX && $pos->getX() <= $this->maxX &&
            $pos->getZ() >= $this->minZ && $pos->getZ() <= $this->maxZ &&
            $this->level->getFolderName() == $pos->getLevel()->getFolderName()
        ) {
            return true;
        }
        return false;
    }

    /**
     * @param Position $pos
     * @return bool
     */
    public function isPvpSurrounding(Position $pos): bool
    {
        for ($i = 0; $i <= 5; $i++) {
            if ($this->insideEdge($pos->getSide($i))) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param Player $player
     * @return array
     */
    private function getWallBlocks(Player $player): array
    {
        $locations = [];
        $radius = 4;
        $l = $player->getPosition();
        $loc1 = clone $l->add($radius, 0, $radius);
        $loc2 = clone $l->subtract($radius, 0, $radius);
        $maxBlockX = max($loc1->getFloorX(), $loc2->getFloorX());
        $minBlockX = min($loc1->getFloorX(), $loc2->getFloorX());
        $maxBlockZ = max($loc1->getFloorZ(), $loc2->getFloorZ());
        $minBlockZ = min($loc1->getFloorZ(), $loc2->getFloorZ());

        for ($x = $minBlockX; $x <= $maxBlockX; $x++) {
            for ($z = $minBlockZ; $z <= $maxBlockZ; $z++) {
                $location = new Position($x, $l->getFloorY(), $z, $l->getLevel());
                if ($this->insideEdge($location)) {
                    continue;
                }
                if (!$this->isPvpSurrounding($location)) {
                    continue;
                }
                for ($i = 0; $i <= $radius; $i++) {
                    $loc = clone $location;
                    $loc->setComponents($loc->getX(), $loc->getY() + $i, $loc->getZ());
                    if ($loc->getLevel()->getBlock($loc)->getId() !== Item::AIR) {
                        continue;
                    }
                    $locations[$loc->__toString()] = $loc;
                }
            }
        }
        return $locations;
    }

    public function onDamageEntity(EntityDamageEvent $event)
    {
        $entity = $event->getEntity();
        if ($entity instanceof Player && $this->inGame($entity)) {
            if ($entity->getArmorInventory()->getChestplate()->getId() === Item::ELYTRA) {
                $event->setCancelled();
            }
        }
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

    public function onInteractPlayer(PlayerInteractEvent $event)
    {
        $player = $event->getPlayer();
        if ($this->inSpectate($player)) {
            switch ($event->getItem()->getId()) {
                case Item::COMPASS:
                    if ($event->getItem()->getCustomName() === '§9Players') {
                        $this->plugin->getFormManager()->sendSpectatePlayer($player, $this->players);
                    }
                    break;
            }
        }
    }

    /**
     * @param PlayerDeathEvent $event
     */
    public function onDeath(PlayerDeathEvent $event)
    {
        $player = $event->getPlayer();

        if (!$this->inGame($player)) {
            return;
        }

        $event->setDrops([]);
        $this->spectateToArena($player);
        $this->broadcastMessage("§6§l» §r§7{$this->plugin->getServer()->getLanguage()->translate($event->getDeathMessage())} §7(" . count($this->players) . "/{$this->data["slots"]})");
        $event->setDeathMessage("");
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
            $damager = $event->getDamager();
            $victim = $event->getEntity();
            if (!$this->pvpEnabled) {
                if ($damager instanceof Player) {
                    $damager->sendMessage("§c§l»§r §7PvP is disabled!");
                }
                $event->setCancelled(true);
                return;
            }
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
                    $this->killsManager->addKill($damager);
                    $this->spectateToArena($victim, true);
                    $this->broadcastMessage("§6§l» §r§7{$victim->getName()} has been killed by {$damager->getName()} §7(" . count($this->players) . "/{$this->data["slots"]})");
                }
            }
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
                $command = substr($message, 1);
                $args = explode(" ", $command);
                switch ($args[0]) {
                    case 'uhc':
                        switch ($args[1]) {
                            case 'leave':
                                $this->disconnectPlayer($event->getPlayer(), "§a§l» §r§7You have successfully left the game!");
                                break;
                            case 'second':
                            case 'pvp':
                            case 'border':
                            case 'first':
                                $event->setCancelled(false);
                                break;
                            default:
                                $event->setCancelled(true);
                                $event->getPlayer()->sendMessage("§c§l» §r§7Use /uhc exit to leave the game.");
                        }
                        break;
                    case 'spawn':
                    case 'lobby':
                    case 'hub':
                    default:
                        $event->getPlayer()->sendMessage("§c§l» §r§7Use /uhc exit to leave the game.");
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
        $player = $event->getPlayer();
        $from = $event->getFrom();
        if ($this->inGame($player)) {
            $aabb = new AxisAlignedBB($this->minX, 0, $this->minZ, $this->maxX, $to->getLevel()->getWorldHeight(), $this->maxZ);
            if (!$aabb->isVectorInXZ($to)) {
                $event->getPlayer()->sendPopup("§eOUT OF BORDER");
                $event->getPlayer()->sendTitle("§4Warning", "§eYou are out of the edge");
            }

            if ($from->getX() === $to->getX() && $from->getY() === $to->getY() && $from->getZ() === $to->getZ()) {
                return;
            }

            $locations = $this->getWallBlocks($player);

            /** @var Location $location */
            foreach ($locations as $location) {
                $position = new Position((int)floor($location->getX()), (int)floor($location->getY()), (int)floor($location->getZ()), $player->getLevel());
                $player->getLevel()->addParticle(new RedstoneParticle($position->asVector3()));
            }
            $this->previousBlocks[$player->getName()] = $locations;
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

    /**
     * @param EntityRegainHealthEvent $event
     */
    public function onEntityRegainHealth(EntityRegainHealthEvent $event)
    {
        $player = $event->getEntity();
        if ($player instanceof Player && $this->inGame($player)) {
            $event->setCancelled();
        }
    }

    /**
     * @return array
     */
    public function getScenarios(): array
    {
        return $this->scenarios;
    }

    public function __destruct()
    {
        unset($this->scheduler);
    }
}