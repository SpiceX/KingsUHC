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
namespace kings\uhc\commands;


use kings\uhc\arena\Arena;
use kings\uhc\entities\MainEntity;
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
		$this->setAliases(['uhc', 'uhc', 'uhr']);
		$this->setDescription('uhc command');
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
					case 'join':
						/** @var Arena $arena */
						$arena = $this->plugin->arenas[$args[1]];
						$arena->joinToArena($sender);
					case 'npc':
						if ($sender->isOp()) {
							foreach ($sender->getLevel()->getEntities() as $entity) {
								if ($entity instanceof MainEntity) {
									$entity->close();
								}
							}
							$nbt = Entity::createBaseNBT($sender->asVector3());
							$nbt->setTag(clone $sender->namedtag->getCompoundTag('Skin'));
							$npc = new MainEntity($sender->getLevel(), $nbt);
							$npc->spawnToAll();
						}
						break;
					case "set":
						if (!$sender->hasPermission("uhc.cmd.set")) {
							$sender->sendMessage("§6uhc » §7You have not permissions to use this command!");
							break;
						}
						if (!$sender instanceof Player) {
							$sender->sendMessage("§6uhc » §7 This command can be used only in-game!");
							break;
						}
						if (!isset($args[1])) {
							$sender->sendMessage("§cUsage: §7/uhr set <arenaName>");
							break;
						}
						if (isset($this->plugin->setters[$sender->getName()])) {
							$sender->sendMessage("§6uhc » §7You are already in setup mode!");
							break;
						}
						if (!isset($this->plugin->arenas[$args[1]])) {
							$sender->sendMessage("§6uhc » §7Arena $args[1] does not found!");
							break;
						}
						$sender->sendMessage("§6> You are joined setup mode.\n" .
							"§7- use §lhelp §r§7to display available commands\n" .
							"§7- or §ldone §r§7to leave setup mode");
						$this->plugin->setters[$sender->getName()] = $this->plugin->arenas[$args[1]];
						break;
					case "create":
						if (!$sender->hasPermission("uhc.cmd.create")) {
							$sender->sendMessage("§6uhc » §7You have not permissions to use this command!");
							break;
						}
						if (!isset($args[1])) {
							$sender->sendMessage("§cUsage: §7/uhc create <arenaName>");
							break;
						}
						if (isset($this->plugin->arenas[$args[1]])) {
							$sender->sendMessage("§6uhc » §7 Arena $args[1] already exists!");
							break;
						}
						$this->plugin->arenas[$args[1]] = new Arena($this->plugin, []);
						$sender->sendMessage("§6uhc » §7 Arena $args[1] created!");
						break;
					case "remove":
						if (!$sender->hasPermission("uhc.cmd.remove")) {
							$sender->sendMessage("§6uhc » §7You have not permissions to use this command!");
							break;
						}
						if (!isset($args[1])) {
							$sender->sendMessage("§cUsage: §7/uhr remove <arenaName>");
							break;
						}
						if (!isset($this->plugin->arenas[$args[1]])) {
							$sender->sendMessage("§6uhc » §7Arena $args[1] was not found!");
							break;
						}

						/** @var Arena $arena */
						$arena = $this->plugin->arenas[$args[1]];

						foreach ($arena->players as $player) {
							$player->teleport($this->plugin->getServer()->getDefaultLevel()->getSpawnLocation());
						}

						if (is_file($file = $this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $args[1] . ".yml")) unlink($file);
						unset($this->plugin->arenas[$args[1]]);

						$sender->sendMessage("§6uhc » Arena removed!");
						break;
					case "arenas":
						if (!$sender->hasPermission("uhc.cmd.arenas")) {
							$sender->sendMessage("§cYou have not permissions to use this command!");
							break;
						}
						if (count($this->plugin->arenas) === 0) {
							$sender->sendMessage("§6> There are 0 arenas.");
							break;
						}
						$list = "§7> Arenas:\n";
						foreach ($this->plugin->arenas as $name => $arena) {
							if ($arena->setup) {
								$list .= "§7- $name : §cdisabled\n";
							} else {
								$list .= "§7- $name : §aenabled\n";
							}
						}
						$sender->sendMessage($list);
						break;
					case 'info':
						$sender->sendMessage("§6uhc 1.0-beta.");
						$sender->sendMessage("§b> Plugin made by @kings_");
						break;
					default:
						if (!$sender->hasPermission("uhc.cmd.help")) {
							$sender->sendMessage("§cYou have not permissions to use this command!");
							break;
						}
						$sender->sendMessage("§cUsage: §7/uhc help");
						break;
				}
			}
		}
	}
}