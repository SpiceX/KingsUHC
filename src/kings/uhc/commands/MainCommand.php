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

namespace kings\uhc\commands;


use kings\uhc\arena\Arena;
use kings\uhc\arena\ArenaManager;
use kings\uhc\entities\types\EndCrystal;
use kings\uhc\entities\types\Leaderboard;
use kings\uhc\KingsUHC;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\entity\Entity;
use pocketmine\Player;

class MainCommand extends PluginCommand implements PluginIdentifiableCommand
{
    /**
     * @var KingsUHC
     */
    private $plugin;

    /**
     * MainCommand constructor.
     * @param KingsUHC $plugin
     */
    public function __construct(KingsUHC $plugin)
    {
        parent::__construct("uhc", $plugin);
        $this->setAliases(['uhc']);
        $this->setDescription('get uhc help for plugin');
        $this->setUsage('§c/uhc help');
        $this->setPermission("uhc.cmd");
        $this->plugin = $plugin;
    }

    /**
     * @param CommandSender $sender
     * @param string $commandLabel
     * @param array $args
     * @return bool|mixed|void
     */
    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if ($sender instanceof Player) {
            if (isset($args[0])) {
                switch ($args[0]) {
                    case 'first':
                        $arena = $this->getArenaManager()->getArenaByPlayer($sender);
                        if ($arena !== null) {
                            $arena->scheduler->gameTime = 46 * 60;
                        }
                        break;
                    case 'pvp':
                        $arena = $this->getArenaManager()->getArenaByPlayer($sender);
                        if ($arena !== null) {
                            $arena->scheduler->gameTime = 55 * 60;
                        }
                        break;
                    case 'second':
                        $arena = $this->getArenaManager()->getArenaByPlayer($sender);
                        if ($arena !== null) {
                            $arena->scheduler->gameTime = 30 * 60;
                        }
                        break;
                    case 'border':
                        $arena = $this->getArenaManager()->getArenaByPlayer($sender);
                        if ($arena !== null && $args[1] !== null) {
                            $arena->shrinkEdge((int)$args[1]);
                        }
                        break;
                    case 'help':
                        if (!$sender->isOp()) {
                            $sender->sendMessage("§8---------(§6Setup Mode§8)---------.\n" .
                                "§6/uhc join §7- Join a UHC Game\n" .
                                "§6/uhc help §7- Help for UHC\n"
                            );
                        } else {
                            $sender->sendMessage("§8---------(§6Setup Mode§8)---------.\n" .
                                "§6/uhc join §7- Join a UHC Game\n" .
                                "§6/uhc help §7- Help for UHC\n" .
                                "§6/uhc tops §7- Place the leaderboard\n" .
                                "§6/uhc create §7- Create UHC Arenas\n" .
                                "§6/uhc set §7- Configure an UHC Arena\n" .
                                "§6/uhc remove §7- Delete permanently an UHC Arena\n" .
                                "§6/uhc arenas §7- See the available arena list\n"
                            );
                        }
                        break;
                    case 'join':
                        if ($this->plugin->getJoinGameQueue()->inQueue($sender)) {
                            $sender->sendMessage("§c§l» §r§7You are in a queue");
                            return;
                        }
                        /** @var Arena $arena */
                        $arena = $this->getArenaManager()->getAvailableArena();
                        if ($arena !== null) {
                            $this->plugin->getJoinGameQueue()->joinToQueue($sender, $arena);
                        } else {
                            $this->plugin->getFormManager()->sendAvailableArenaNotFound($sender);
                        }
                        break;
                    case 'npc':
                        if ($sender->isOp()) {
                            foreach ($sender->getLevel()->getEntities() as $entity) {
                                if ($entity instanceof EndCrystal) {
                                    $entity->close();
                                }
                            }
                            $nbt = Entity::createBaseNBT($sender->asVector3());
                            $crystal = Entity::createEntity("EnderCrystalUHC", $sender->getLevel(), $nbt);
                            if ($crystal instanceof EndCrystal) {
                                $crystal->spawnToAll();
                            }
                        }
                        break;
                    case 'tops':
                        if ($sender->isOp()) {
                            foreach ($sender->getLevel()->getEntities() as $entity) {
                                if ($entity instanceof Leaderboard) {
                                    $entity->close();
                                }
                            }
                            $nbt = Entity::createBaseNBT($sender->asVector3());
                            $nbt->setTag(clone $sender->namedtag->getCompoundTag('Skin'));
                            $npc = new Leaderboard($sender->getLevel(), $nbt);
                            $npc->spawnToAll();
                            $sender->sendMessage("§a§l» §r§7Leaderboard has been placed.");
                        }
                        break;
                    case "set":
                        if (!$sender->hasPermission("uhc.cmd.set")) {
                            $sender->sendMessage("§c§l» §r§7You have not permissions to use this command!");
                            break;
                        }
                        if (!$sender instanceof Player) {
                            $sender->sendMessage("§c§l» §r§7 This command can be used only in-game!");
                            break;
                        }
                        if (!isset($args[1])) {
                            $sender->sendMessage("§cUsage: §7/uhc set <arenaName>");
                            break;
                        }
                        if (isset($this->plugin->setters[$sender->getName()])) {
                            $sender->sendMessage("§c§l» §r§7You are already in setup mode!");
                            break;
                        }
                        if (!isset($this->getArenaManager()->getArenas()[$args[1]])) {
                            $sender->sendMessage("§c§l» §r§7Arena $args[1] does not found!");
                            break;
                        }
                        $sender->sendMessage("§8---------(§6Setup Mode§8)---------.\n" .
                            "§6- §7use §l§ehelp §rto display available commands\n" .
                            "§6- §7or §l§edone §rto leave setup mode");
                        $this->getArenaManager()->setters[$sender->getName()] = $this->getArenaManager()->getArenas()[$args[1]];
                        break;
                    case "create":
                        if (!$sender->hasPermission("uhc.cmd.create")) {
                            $sender->sendMessage("§c§l» §r§7You have not permissions to use this command!");
                            break;
                        }
                        if (!isset($args[1])) {
                            $sender->sendMessage("§cUsage: §7/uhc create <arenaName>");
                            break;
                        }
                        if ($this->getArenaManager()->getArena($args[1]) !== null) {
                            $sender->sendMessage("§c§l» §r§7 Arena $args[1] already exists!");
                            break;
                        }
                        $this->getArenaManager()->registerArena($args[1], new Arena($this->plugin, []));
                        $sender->sendMessage("§a§l» §r§7 Arena $args[1] created!");
                        break;
                    case "remove":
                        if (!$sender->hasPermission("uhc.cmd.remove")) {
                            $sender->sendMessage("§c§l» §r§7You have not permissions to use this command!");
                            break;
                        }
                        if (!isset($args[1])) {
                            $sender->sendMessage("§cUsage: §7/uhr remove <arenaName>");
                            break;
                        }
                        if ($this->getArenaManager()->getArena($args[1]) === null) {
                            $sender->sendMessage("§c§l» §r§7Arena $args[1] was not found!");
                            break;
                        }

                        $arena = $this->getArenaManager()->removeArena($args[1]);

                        foreach ($arena->players as $player) {
                            $player->teleport($this->plugin->getServer()->getDefaultLevel()->getSpawnLocation());
                        }

                        if (is_file($file = $this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $args[1] . ".yml")) {
                            @unlink($file);
                        }

                        $sender->sendMessage("§a§l» §rArena removed!");
                        break;
                    case "arenas":
                        if (!$sender->hasPermission("uhc.cmd.arenas")) {
                            $sender->sendMessage("§c§l» §r§7You have not permissions to use this command!");
                            break;
                        }
                        if (count($this->getArenaManager()->getArenas()) === 0) {
                            $sender->sendMessage("§c§l» §r§7There are 0 arenas.");
                            break;
                        }
                        $list = "§8-------(§6Arenas§8)-------:\n";
                        foreach ($this->getArenaManager()->getArenas() as $name => $arena) {
                            if ($arena->setup) {
                                $list .= "§7- $name : §cdisabled\n";
                            } else {
                                $list .= "§7- $name : §aenabled\n";
                            }
                        }
                        $sender->sendMessage($list);
                        break;
                    default:
                        if (!$sender->hasPermission("uhc.cmd.help")) {
                            $sender->sendMessage("§c§l» §r§7You have not permissions to use this command!");
                            break;
                        }
                        $sender->sendMessage("§c§l» §r§7/uhc help");
                        break;
                }
            }
        }
    }


    /**
     * @return ArenaManager
     */
    public function getArenaManager(): ArenaManager
    {
        return $this->plugin->getArenaManager();
    }
}