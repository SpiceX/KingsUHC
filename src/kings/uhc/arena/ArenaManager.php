<?php

namespace kings\uhc\arena;

use Exception;
use kings\uhc\arena\utils\MapReset;
use kings\uhc\KingsUHC;
use kings\uhc\math\Vector3;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\utils\Config;

class ArenaManager implements Listener
{
    /** @var Arena[] */
    public $setters = [];
    /** @var array */
    private $setupData = [];
    /** @var Arena[] */
    public $arenas = [];
    /** @var KingsUHC */
    private $plugin;

    /**
     * ArenaManager constructor.
     * @param KingsUHC $plugin
     */
    public function __construct(KingsUHC $plugin)
    {
        $this->plugin = $plugin;
        $this->loadArenas();
        $plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
    }

    public function loadArenas()
    {
        foreach (glob($this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . "*.yml") as $arenaFile) {
            $config = new Config($arenaFile, Config::YAML);
            $this->arenas[basename($arenaFile, ".yml")] = new Arena($this->plugin, $config->getAll(false));
            $this->plugin->getJoinGameQueue()->arenas[basename($arenaFile, '.yml')] = [];
            $this->plugin->getJoinGameQueue()->startingTimes[basename($arenaFile, '.yml')] = 10;
        }
    }

    public function saveArenas()
    {
        foreach ($this->arenas as $fileName => $arena) {
            if ($arena->level instanceof Level) {
                foreach ($arena->players as $player) {
                    $player->teleport($player->getServer()->getDefaultLevel()->getSpawnLocation());
                }
                // must be reseted

                $arena->mapReset->loadMap($arena->level->getFolderName(), true);
            }
            $config = new Config($this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $fileName . ".yml", Config::YAML);
            $config->setAll($arena->data);
            $config->save();
        }
    }

    /**
     * @param string $identifier
     * @return Arena|null
     */
    public function getArena(string $identifier): ?Arena
    {
        return $this->arenas[$identifier] ?? null;
    }

    /**
     * @param Player $player
     * @return Arena|null
     */
    public function getArenaByPlayer(Player $player): ?Arena
    {
        foreach ($this->arenas as $arena) {
            foreach ($arena->players as $players) {
                if ($player->getId() === $players->getId()){
                    return $arena;
                }
            }
        }
        return null;
    }

    /**
     * @param string $identifier
     * @param Arena $arena
     */
    public function registerArena(string $identifier, Arena $arena): void
    {
        $this->arenas[$identifier] = $arena;
    }

    /**
     * @param string $identifier
     */
    public function removeArena(string $identifier)
    {
        if (isset($this->arenas[$identifier])) {
            unset($this->arenas[$identifier]);
        }
    }


    /**
     * @param PlayerChatEvent $event
     */
    public function onChat(PlayerChatEvent $event)
    {
        $player = $event->getPlayer();

        if (!isset($this->setters[$player->getName()])) {
            return;
        }

        $event->setCancelled(true);
        $args = explode(" ", $event->getMessage());

        $arena = $this->setters[$player->getName()];

        switch ($args[0]) {
            case "help":
                $player->sendMessage("§8-------(§6Setup Help §7(§e1§7/§e1§7)§8)-------\n" .
                    "§ehelp : §7Displays list of available setup commands\n" .
                    "§eslots : §7Updates arena slots\n" .
                    "§elevel : §7Sets arena level\n" .
                    "§ecenter : §7Sets arena center\n" .
                    "§esavelevel : §7Saves the arena level\n" .
                    "§eenable : §7Enables the arena");
                break;
            case "slots":
                if (!isset($args[1])) {
                    $player->sendMessage("§c§l» §r§7Usage: §7slots <int: slots>");
                    break;
                }
                $arena->data["slots"] = (int)$args[1];
                $player->sendMessage("§a§l» §r§7Slots updated to $args[1]!");
                break;
            case "level":
                if (!isset($args[1])) {
                    $player->sendMessage("§cUsage: §7level <levelName>");
                    break;
                }
                if (!$this->plugin->getServer()->isLevelGenerated($args[1])) {
                    $player->sendMessage("§c§l» §r§7Level $args[1] does not found!");
                    break;
                }
                $player->sendMessage("§a§l» §r§7Arena level updated to $args[1]!");
                $arena->data["level"] = $args[1];
                break;
            case "center":
                $arena->data["center"] = (new Vector3($player->getX(), $player->getY(), $player->getZ()))->__toString();
                $player->sendMessage("§a§l» §r§7Center set to X: " . (string)round($player->getX()) . " Y: " . (string)round($player->getY()) . " Z: " . (string)round($player->getZ()));
                break;
            case "savelevel":
                if (!$arena->level instanceof Level) {
                    $levelName = $arena->data["level"];
                    if (!is_string($levelName) || !$this->plugin->getServer()->isLevelGenerated($levelName)) {
                        errorMessage:
                        $player->sendMessage("§c§l» §r§7Error while saving the level: world not found.");
                        if ($arena->setup) {
                            $player->sendMessage("§6§l» §r§7Try save level after enabling the arena.");
                        }
                        return;
                    }
                    if (!$this->plugin->getServer()->isLevelLoaded($levelName)) {
                        $this->plugin->getServer()->loadLevel($levelName);
                    }

                    try {
                        if (!$arena->mapReset instanceof MapReset) {
                            goto errorMessage;
                        }
                        $arena->mapReset->saveMap($this->plugin->getServer()->getLevelByName($levelName));
                        $player->sendMessage("§a§l» §r§7Level saved!");
                    } catch (Exception $exception) {
                        goto errorMessage;
                    }
                    break;
                }
                break;
            case "enable":
                if (!$arena->setup) {
                    $player->sendMessage("§6§l» §r§7Arena is already enabled!");
                    break;
                }

                if (!$arena->enable(false)) {
                    $player->sendMessage("§c§l» §r§7Could not load arena, there are missing information!");
                    break;
                }

                if ($this->plugin->getServer()->isLevelGenerated($arena->data["level"])) {
                    if (!$this->plugin->getServer()->isLevelLoaded($arena->data["level"]))
                        $this->plugin->getServer()->loadLevel($arena->data["level"]);
                    if (!$arena->mapReset instanceof MapReset)
                        $arena->mapReset = new MapReset($arena);
                    $arena->mapReset->saveMap($this->plugin->getServer()->getLevelByName($arena->data["level"]));
                }

                $arena->loadArena(false);
                $player->sendMessage("§a§l» §r§7Arena enabled!");
                break;
            case "done":
                $player->sendMessage("§a§l» §r§7You have successfully left setup mode!");
                unset($this->setters[$player->getName()]);
                if (isset($this->setupData[$player->getName()])) {
                    unset($this->setupData[$player->getName()]);
                }
                break;
            default:
                $player->sendMessage("§8---------(§6Setup Mode§8)---------.\n" .
                    "§6- §7use §l§ehelp §rto display available commands\n" .
                    "§6- §7or §l§edone §rto leave setup mode");
                break;
        }
    }

    /**
     * @return Arena|null
     */
    public function getAvailableArena(): ?Arena
    {
        foreach ($this->arenas as $arena) {
            if ($arena->phase === Arena::PHASE_LOBBY && count($arena->players) < (int)$arena->data['slots']){
                return $arena;
            }
        }
        return null;
    }

    /**
     * @return Arena[]
     */
    public function getArenas(): array
    {
        return $this->arenas;
    }
}