<?php

/**
 * Copyright 2020-2022 KingsUHC
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
use kings\uhc\arena\scenario\LimitationsStorage;
use kings\uhc\arena\scenario\Scenarios;
use kings\uhc\arena\scenario\TimeBombTask;
use kings\uhc\arena\utils\KillsManager;
use kings\uhc\arena\utils\MapReset;
use kings\uhc\arena\utils\VoteManager;
use kings\uhc\entities\types\Creeper;
use kings\uhc\KingsUHC;
use kings\uhc\math\Vector3;
use kings\uhc\utils\BossBar;
use kings\uhc\utils\PluginUtils;
use kings\uhc\utils\Scoreboard;
use pocketmine\block\Block;
use pocketmine\block\BlockIds;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\entity\Entity;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\inventory\CraftItemEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\inventory\ChestInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\Item;
use pocketmine\lang\TextContainer;
use pocketmine\level\Explosion;
use pocketmine\level\Level;
use pocketmine\level\Location;
use pocketmine\level\particle\RedstoneParticle;
use pocketmine\level\Position;
use pocketmine\math\AxisAlignedBB;
use pocketmine\network\mcpe\protocol\SetSpawnPositionPacket;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
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
    /** @var int $border */
    public $border = 1000;
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
    /** @var Player[] */
    private $toRespawn;
    /** @var LimitationsStorage */
    public $limitationsStorage;

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
        $this->limitationsStorage = new LimitationsStorage($this);
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

        $center = Vector3::fromString($this->data['center']);
        $this->maxX = $center->add($this->border)->getX();
        $this->minX = $center->subtract($this->border)->getX();
        $this->maxZ = $center->add(0, 0, $this->border)->getZ();
        $this->minZ = $center->subtract(0, 0, $this->border)->getZ();
        $this->phase = static::PHASE_LOBBY;
        $this->players = [];
        $this->spectators = [];
        $this->scoreboards = [];
        $this->bossbars = [];
    }

    public function onInventoryTransaction(InventoryTransactionEvent $event)
    {
        $player = $event->getTransaction()->getSource();
        if ($this->inGame($player) && $event->getTransaction()->hasExecuted()) {
            $transaction = $event->getTransaction();
            $inventories = $transaction->getInventories();
            foreach ($inventories as $inventory) {
                if ($inventory instanceof ChestInventory || $inventory instanceof PlayerInventory) {
                    foreach ($inventory->getContents() as $content) {
                        if ($content->getId() === Item::ELYTRA) {
                            $inventory->removeItem($content);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param EntityDeathEvent $event
     */
    public function onEntityDeath(EntityDeathEvent $event)
    {
        $entity = $event->getEntity();
        if ($this->level->getFolderName() === $entity->getLevel()->getFolderName()) {
            if (in_array(Scenarios::CUTCLEAN, $this->scenarios, true)) {
                switch ($entity->getName()) {
                    case "Cow":
                        $event->setDrops([Item::get(Item::STEAK)]);
                        break;
                    case "Chicken":
                        $event->setDrops([Item::get(Item::COOKED_CHICKEN)]);
                        break;
                    case "Fish":
                        $event->setDrops([Item::get(Item::COOKED_FISH)]);
                        break;
                    case "Sheep":
                        $event->setDrops([Item::get(Item::COOKED_MUTTON)]);
                        break;
                    case "Pig":
                    case "Piggy":
                        $event->setDrops([Item::get(Item::COOKED_PORKCHOP)]);
                        break;
                    case "Rabbit":
                        $event->setDrops([Item::get(Item::COOKED_RABBIT)]);
                        break;
                    case "Salmon":
                        $event->setDrops([Item::get(Item::COOKED_SALMON)]);
                        break;
                }
            }
        }
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
        $center = Vector3::fromString($this->data['center']);
        $pk = new SetSpawnPositionPacket();
        $pk->spawnType = SetSpawnPositionPacket::TYPE_WORLD_SPAWN;
        $pk->x = $pk->x2 = $center->getX();
        $pk->y = $pk->y2 = $center->getY();
        $pk->z = $pk->z2 = $center->getZ();
        $pk->dimension = DimensionIds::OVERWORLD;
        $pk->dimension = DimensionIds::OVERWORLD;
        $player->sendDataPacket($pk);
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
        if ($death) {
            $player->addEffect(new EffectInstance(Effect::getEffect(Effect::BLINDNESS), 20));
            $player->sendTitle("§cGame Over", "§7Good luck next time");
        } else {
            if ($this->inSpectate($player)) {
                $player->sendMessage("§c§l» §7You are already spectating a game!");
                return;
            }
        }
        unset($this->players[$player->getName()]);
        $this->spectators[$player->getName()] = $player;
        $this->scoreboards[$player->getName()] = new Scoreboard($player);
        $player->getInventory()->clearAll();
        $player->getInventory()->setItem(4, Item::get(Item::COMPASS)->setCustomName('§9Players'));
        $player->setGamemode(Player::SPECTATOR);
    }

    /**
     * @param int $blocks
     */
    public function shrinkEdge(int $blocks)
    {
        $this->border -= $blocks;
        $this->maxX -= $blocks;
        $this->minX += $blocks;
        $this->maxZ -= $blocks;
        $this->minZ += $blocks;
    }

    public function checkPlayersInsideBorder()
    {
        foreach ($this->players as $player) {
            $aabb = new AxisAlignedBB($this->minX, 0, $this->minZ, $this->maxX, 256, $this->maxZ);
            if (!$aabb->isVectorInXZ($player->getLocation())) {
                for ($i = 0; $i < 20; ++$i) {
                    $vector = PluginUtils::getRandomVector()->multiply(3);
                    $vector->y = abs($vector->getY());
                    $player->getLevel()->addParticle(new RedstoneParticle($player->getLocation()->add($vector->x, $vector->y, $vector->z)));
                }
                $player->attack(new EntityDamageEvent($player, EntityDamageEvent::CAUSE_MAGIC, 0.4));
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

    public function onInventoryPickupItem(InventoryPickupItemEvent $event)
    {
        $players = $event->getInventory()->getViewers();
        foreach ($players as $player) {
            if ($this->inGame($player)) {
                if ($event->getItem()->getItem()->getId() === Item::ELYTRA) {
                    $event->getItem()->close();
                    $event->setCancelled(true);
                }
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
        if ($this->inGame($player)) {
            if (in_array(Scenarios::BED_BOMB, $this->scenarios)) {
                if ($event->getBlock()->getId() === BlockIds::BED_BLOCK) {
                    $event->setCancelled();
                    $explosion = new Explosion($event->getBlock()->asPosition(), 5);
                    $explosion->explodeA();
                    $explosion->explodeB();
                }
            }
        }
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
    public function onPlayerDeath(PlayerDeathEvent $event)
    {
        $player = $event->getPlayer();
        if (!$this->inGame($player)) {
            return;
        }
        $this->toRespawn[$player->getName()] = $player;
        if (in_array(Scenarios::TIME_BOMB, $this->scenarios)) {
            TimeBombTask::createChest($player, $event->getDrops());
            $this->plugin->getScheduler()->scheduleRepeatingTask(new TimeBombTask($this, $player->asPosition()), 20);
            $event->setDrops([]);
        }
        /** DIAMONDLESS SCENARIO */
        if (in_array(Scenarios::DIAMONDLESS, $this->scenarios)) {
            $player->getLevel()->dropItem($player->asVector3(), Item::get(Item::GOLD_INGOT, 0, 4));
            $player->getLevel()->dropItem($player->asVector3(), Item::get(Item::DIAMOND));
        } elseif (in_array(Scenarios::GOLDLESS, $this->scenarios)) {
            /** GOLDLESS SCENARIO */
            $player->getLevel()->dropItem($player->asVector3(), Item::get(Item::GOLD_INGOT, 0, 8));
            $player->getLevel()->dropItem($player->asVector3(), Item::get(Item::GOLDEN_APPLE));
        } elseif (in_array(Scenarios::BAREBONES, $this->scenarios)) {
            $player->getLevel()->dropItem($player->asVector3(), Item::get(Item::DIAMOND));
            $player->getLevel()->dropItem($player->asVector3(), Item::get(Item::GOLDEN_APPLE));
            $player->getLevel()->dropItem($player->asVector3(), Item::get(Item::ARROW, 0, 32));
            $player->getLevel()->dropItem($player->asVector3(), Item::get(Item::STRING, 0, 2));
        }
        $deathMessage = $event->getDeathMessage();
        $this->spectateToArena($player, true);
        if ($deathMessage === null) {
            $this->broadcastMessage("§6§l» §r§7 {$player->getName()} died. §6(" . count($this->players) . "/{$this->data["slots"]})");
        } else {
            if ($deathMessage instanceof TextContainer) {
                $this->broadcastMessage("§6§l» §r§7 {$this->plugin->getServer()->getLanguage()->translate($deathMessage)} §6(" . count($this->players) . "/{$this->data["slots"]})");
            }
        }
        $event->setDeathMessage("");
    }

    /**
     * @param EntityDamageByEntityEvent $event
     * @priority HIGH
     */
    public function onEntityDamageByEntity(EntityDamageByEntityEvent $event)
    {
        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if ($damager instanceof Player && $victim instanceof Player) {
            if (!$this->inGame($damager)) {
                return;
            }
            if (!$this->pvpEnabled) {
                $event->setCancelled();
                $damager->sendMessage("§c§l»§r §7PvP is disabled!");
                return;
            }
            if ($event->getFinalDamage() >= $victim->getHealth()) {
                $this->killsManager->addKill($damager);
            }
        }
    }

    /**
     * @param EntityDamageEvent $event
     */
    public function onDamage(EntityDamageEvent $event)
    {
        $player = $event->getEntity();
        if ($player instanceof Player) {
            if (!$this->inGame($player)) {
                return;
            }
            switch ($this->phase) {
                case self::PHASE_LOBBY:
                case self::PHASE_RESTART:
                    $event->setCancelled(true);
                    break;
                case self::PHASE_GAME:
                    if ($player->getArmorInventory()->getChestplate()->getId() === Item::ELYTRA) {
                        $event->setCancelled();
                        return;
                    }
            }
        }
    }

    public function onCraftEvent(CraftItemEvent $event)
    {
        $player = $event->getPlayer();
        if (!$this->inGame($player)) {
            return;
        }
        if (in_array(Scenarios::BAREBONES, $this->scenarios)) {
            foreach ($event->getRecipe()->getResults() as $results) {
                if ($results->getId() === Item::APPLE_ENCHANTED) {
                    $event->setCancelled(true);
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
     * @param PlayerRespawnEvent $event
     */
    public function onRespawn(PlayerRespawnEvent $event)
    {
        $player = $event->getPlayer();
        if (isset($this->toRespawn[$player->getName()])) {
            $event->setRespawnPosition(Position::fromObject(Vector3::fromString($this->data['center']), $this->level));
            unset($this->toRespawn[$player->getName()]);
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
                            case 'exit':
                                $this->disconnectPlayer($event->getPlayer(), "§a§l» §r§7You have successfully left the game!");
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
     * @throws Exception
     */
    public function onBlockBreak(BlockBreakEvent $event)
    {
        if ($event->isCancelled()) {
            return;
        }
        if (!$this->inGame($event->getPlayer())) {
            return;
        }
        $player = $event->getPlayer();
        $block = $event->getBlock();
        switch ($block->getId()) {
            case Block::LEAVES:
            case Block::LEAVES2:
                if (random_int(1, 3) === 2) {
                    $event->setDrops([Item::get(Item::APPLE)]);
                }
                if (random_int(1, 4) === 2) {
                    $event->setDrops([Item::get(Item::MUSHROOM_STEW)]);
                }
                break;
            case Block::RED_FLOWER:
            case Block::YELLOW_FLOWER:
            case Block::DANDELION:
                if (random_int(1, 3) === 2) {
                    $event->setDrops([Item::get(Item::STRING)]);
                }
                break;
            case Block::GRAVEL:
                if (random_int(1, 5) === 3) {
                    $event->setDrops([Item::get(Item::ARROW)]);
                }
                break;
            case Block::LAPIS_ORE:
                if (random_int(1, 9) === 4) {
                    $event->setDrops([Item::get(Item::ENCHANTED_BOOK, 9)]);
                }
                break;
            case Block::EMERALD_ORE:
                if (random_int(1, 6) === 4) {
                    $event->setDrops([Item::get(Item::ENDER_PEARL)]);
                }
                break;
        }
        if (in_array(Scenarios::CUTCLEAN, $this->scenarios)) {
            switch ($block->getId()) {
                case Block::IRON_ORE:
                    $event->setDrops([Item::get(Item::IRON_INGOT)]);
                    break;
                case Block::GOLD_ORE:
                    $event->setDrops([Item::get(Item::GOLD_INGOT)]);
                    break;
                case Block::DIAMOND_ORE:
                    $event->setDrops([Item::get(Item::DIAMOND, random_int(1, 2))]);
                    break;
                case Block::COAL_ORE:
                    $event->setDrops([Item::get(Item::COAL, random_int(1, 2)), Item::get(Item::TORCH, random_int(1, 2))]);
                    break;
            }
        } elseif (in_array(Scenarios::DIAMONDLESS, $this->scenarios)) {
            switch ($block->getId()) {
                case Block::DIAMOND_ORE:
                    if (random_int(1, 5) === 3) {
                        $event->setDrops([Item::get(Item::GOLD_INGOT)]);
                    } else {
                        $event->setDrops([]);
                    }
                    break;
            }
        } elseif (in_array(Scenarios::GOLDLESS, $this->scenarios)) {
            switch ($block->getId()) {
                case Block::GOLD_ORE:
                    $event->setDrops([]);
                    break;
            }
        } elseif (in_array(Scenarios::BAREBONES, $this->scenarios)) {
            switch ($block->getId()) {
                case Block::GOLD_ORE:
                    $event->setDrops([Item::get(Item::IRON_INGOT, random_int(1, 2))]);
                    break;
                case Block::DIAMOND_ORE:
                    $event->setDrops([Item::get(Item::IRON_INGOT, random_int(1, 3))]);
                    break;
                case Block::COAL_ORE:
                    $event->setDrops([Item::get(Item::IRON_INGOT)]);
                    break;
            }
        } elseif (in_array(Scenarios::DOUBLE_ORES, $this->scenarios)) {
            switch ($block->getId()) {
                case Block::GOLD_ORE:
                    $event->setDrops([Item::get(Item::GOLD_INGOT, 0, 2)]);
                    break;
                case Block::DIAMOND_ORE:
                    $event->setDrops([Item::get(Item::DIAMOND, 0, 2)]);
                    break;
                case Block::IRON_ORE:
                    $event->setDrops([Item::get(Item::IRON_INGOT, 0, 2)]);
                    break;
                case Block::COAL_ORE:
                    $event->setDrops([Item::get(Item::COAL, 0, 4)]);
                    break;
                case Block::EMERALD_ORE:
                    $event->setDrops([Item::get(Item::EMERALD, 0, 2)]);
                    break;
                case Block::REDSTONE_ORE:
                    $event->setDrops([Item::get(Item::REDSTONE, 0, 12)]);
                    break;
                case Block::LAPIS_ORE:
                    $event->setDrops([Item::get(Item::LAPIS_ORE, 0, 12)]);
                    break;
                case Block::NETHER_QUARTZ_ORE:
                    $event->setDrops([Item::get(Item::NETHER_QUARTZ, 0, 6)]);
                    break;
            }
        }
        if (in_array(Scenarios::BLOOD_DIAMONDS, $this->scenarios)) {
            switch ($block->getId()) {
                case Block::DIAMOND_ORE:
                    $player->attack(new EntityDamageEvent($player, EntityDamageEvent::CAUSE_BLOCK_EXPLOSION, 0.5));
                    break;
            }
        }
        if (in_array(Scenarios::TREE_CAPITATOR, $this->scenarios)) {
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
        if (in_array(Scenarios::LIMITATIONS, $this->scenarios)) {
            switch ($block->getId()) {
                case Block::GOLD_ORE:
                    if (!$this->limitationsStorage->canBreakOre($player, LimitationsStorage::GOLD_TYPE)) {
                        $player->sendMessage("§c§l» §r§7You have reached the limit of gold ingots");
                        $event->setCancelled(true);
                        return;
                    }
                    $player->sendMessage("§e§l» §r§7Gold ingots count: §e(" . $this->limitationsStorage->getOreCount($player, LimitationsStorage::GOLD_TYPE) . "/32)");
                    $this->limitationsStorage->addOreCount($player, LimitationsStorage::GOLD_TYPE);
                    break;
                case Block::DIAMOND_ORE:
                    if (!$this->limitationsStorage->canBreakOre($player, LimitationsStorage::DIAMOND_TYPE)) {
                        $player->sendMessage("§c§l» §r§7You have reached the limit of diamonds");
                        $event->setCancelled(true);
                        return;
                    }
                    $player->sendMessage("§e§l» §r§7Diamonds count: §e(" . $this->limitationsStorage->getOreCount($player, LimitationsStorage::DIAMOND_TYPE) . "/16)");
                    $this->limitationsStorage->addOreCount($player, LimitationsStorage::DIAMOND_TYPE);
                    break;
                case Block::IRON_ORE:
                    if (!$this->limitationsStorage->canBreakOre($player, LimitationsStorage::IRON_TYPE)) {
                        $player->sendMessage("§c§l» §r§7You have reached the limit of iron ingots");
                        $event->setCancelled(true);
                        return;
                    }
                    $player->sendMessage("§e§l» §r§7Iron ingots count: §e(" . $this->limitationsStorage->getOreCount($player, LimitationsStorage::IRON_TYPE) . "/64)");
                    $this->limitationsStorage->addOreCount($player, LimitationsStorage::IRON_TYPE);
                    break;
            }
        }
        if (in_array(Scenarios::GAMBLE, $this->scenarios)) {
            switch ($block->getId()) {
                case Block::GOLD_ORE:
                case Block::DIAMOND_ORE:
                case Block::IRON_ORE:
                case Block::COAL_ORE:
                case Block::EMERALD_ORE:
                case Block::REDSTONE_ORE:
                case Block::LAPIS_ORE:
                case Block::NETHER_QUARTZ_ORE:
                    if (random_int(1, 10) === 5) {
                        $effects = [Effect::getEffect(Effect::REGENERATION), Effect::getEffect(Effect::SPEED),
                            Effect::getEffect(Effect::FIRE_RESISTANCE), Effect::getEffect(Effect::DAMAGE_RESISTANCE),
                            Effect::getEffect(Effect::ABSORPTION), Effect::getEffect(Effect::CONDUIT_POWER),
                            Effect::getEffect(Effect::HASTE), Effect::getEffect(Effect::JUMP_BOOST)];
                        $player->addEffect(new EffectInstance($effects[array_rand($effects)], random_int(100, 200)));
                    } elseif (random_int(1, 10) === 3) {
                        $event->setDrops([Item::get($block->getId(), 0, random_int(4, 9))]);
                    }
                    break;
            }
        }

        if (in_array(Scenarios::BLAST_MINING, $this->scenarios)) {
            $nbt = Entity::createBaseNBT(new \pocketmine\math\Vector3($block->getX(), $block->getY() + 1, $block->getZ()));
            switch ($block->getId()) {
                case Block::GOLD_ORE:
                case Block::DIAMOND_ORE:
                case Block::IRON_ORE:
                case Block::COAL_ORE:
                case Block::EMERALD_ORE:
                case Block::REDSTONE_ORE:
                case Block::LAPIS_ORE:
                case Block::NETHER_QUARTZ_ORE:
                    if (random_int(1, 20) === 11) {
                        $tnt = Entity::createEntity('PrimedTNT', $block->getLevel(), $nbt);
                        $tnt->spawnToAll();
                    } elseif (random_int(1, 20) === 13) {
                        /** @var Creeper $creeper */
                        $creeper = Entity::createEntity('minecraft:creeper', $block->getLevel(), $nbt);
                        $creeper->spawnToAll();
                        $creeper->setIgnited(true);
                    }
                    break;
            }
        }
    }

    public function onPlayerItemConsume(PlayerItemConsumeEvent $event)
    {
        $player = $event->getPlayer();
        if (!$this->inGame($player)) {
            return;
        }
        if (in_array(Scenarios::SOUP, $this->scenarios)) {
            if ($event->getItem()->getId() === Item::MUSHROOM_STEW) {
                $player->setHealth($player->getHealth() + 4);
                $player->getInventory()->setItemInHand(Item::get(Item::BOWL));
            }
        }
    }

    public function onEntityShootBow(EntityShootBowEvent $event)
    {
        $player = $event->getEntity();
        if ($player instanceof Player && $this->inGame($player)) {
            if (in_array(Scenarios::BOWLESS, $this->scenarios)) {
                $player->sendMessage("§cBowless scenario is enabled!");
                $event->setForce(0);
                $event->getProjectile()->close();
            }
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