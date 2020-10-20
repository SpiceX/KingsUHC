<?php


namespace kings\uhc\arena;


use Exception;
use kings\uhc\KingsUHC;
use kings\uhc\math\Vector3;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\level\Level;

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
                $player->sendMessage("§a> SkyWars setup help (1/1):\n" .
                    "§7help : Displays list of available setup commands\n" .
                    "§7slots : Updates arena slots\n" .
                    "§7level : Sets arena level\n" .
                    "§7deathmatch : Sets arena deathmatch\n" .
                    "§7savelevel : Saves the arena level\n" .
                    "§7enable : Enables the arena");
                break;
            case "slots":
                if (!isset($args[1])) {
                    $player->sendMessage("§cUsage: §7slots <int: slots>");
                    break;
                }
                $arena->data["slots"] = (int)$args[1];
                $player->sendMessage("§a> Slots updated to $args[1]!");
                break;
            case "level":
                if (!isset($args[1])) {
                    $player->sendMessage("§cUsage: §7level <levelName>");
                    break;
                }
                if (!$this->plugin->getServer()->isLevelGenerated($args[1])) {
                    $player->sendMessage("§c> Level $args[1] does not found!");
                    break;
                }
                $player->sendMessage("§a> Arena level updated to $args[1]!");
                $arena->data["level"] = $args[1];
                break;
            case "center":
                $arena->data["center"] = (new Vector3($player->getX(), $player->getY(), $player->getZ()))->__toString();
                $player->sendMessage("§a> Center set to X: " . (string)round($player->getX()) . " Y: " . (string)round($player->getY()) . " Z: " . (string)round($player->getZ()));
                break;
            case "savelevel":
                if (!$arena->level instanceof Level) {
                    $levelName = $arena->data["level"];
                    if (!is_string($levelName) || !$this->plugin->getServer()->isLevelGenerated($levelName)) {
                        errorMessage:
                        $player->sendMessage("§c> Error while saving the level: world not found.");
                        if ($arena->setup) {
                            $player->sendMessage("§6> Try save level after enabling the arena.");
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
                        $player->sendMessage("§a> Level saved!");
                    } catch (Exception $exception) {
                        goto errorMessage;
                    }
                    break;
                }
                break;
            case "enable":
                if (!$arena->setup) {
                    $player->sendMessage("§6> Arena is already enabled!");
                    break;
                }

                if (!$arena->enable(false)) {
                    $player->sendMessage("§c> Could not load arena, there are missing information!");
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
                $player->sendMessage("§a> Arena enabled!");
                break;
            case "done":
                $player->sendMessage("§a> You have successfully left setup mode!");
                unset($this->setters[$player->getName()]);
                if (isset($this->setupData[$player->getName()])) {
                    unset($this->setupData[$player->getName()]);
                }
                break;
            default:
                $player->sendMessage("§6> You are in setup mode.\n" .
                    "§7- use §lhelp §r§7to display available commands\n" .
                    "§7- or §ldone §r§7to leave setup mode");
                break;
        }
    }
}