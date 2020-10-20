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
namespace kings\uhc\forms;


use kings\uhc\arena\Arena;
use kings\uhc\forms\elements\Button;
use kings\uhc\forms\elements\Image;
use kings\uhc\forms\types\MenuForm;
use kings\uhc\KingsUHC;
use pocketmine\Player;

class FormManager
{

	/**
	 * @var KingsUHC
	 */
	private $plugin;

	public function __construct(KingsUHC $plugin)
	{
		$this->plugin = $plugin;
	}

	public function sendUHCPanel(Player $player)
	{
		$player->sendForm(new MenuForm("§c§lUHC RUN PANEL", "§7Select an option: ",
			[
				new Button("§6UHC RUN [Select Maps]", new Image("textures/items/book_written", Image::TYPE_PATH))
			], function (Player $player, Button $selected): void {
				$this->sendArenasForm($player);
			}));
	}

	public function sendArenasForm(Player $player)
	{
		$player->sendForm(new MenuForm("§0uhc", "§7Available arenas for uhc run: ",
			$this->getArenasButtons(), function (Player $player, Button $selected): void {
				/** @var Arena $arena */
				$arena = $this->plugin->arenas[explode("\n", $selected->getText())[0]];
				$arena->joinToArena($player);
			}));
	}

	public function getArenasButtons()
	{
		$buttons = [];
		foreach ($this->plugin->arenas as $name => $arena) {
			/** @var Arena $arena */
			$buttons[] = new Button($name . "\n§aPlaying: " . count($arena->players));
		}
		return $buttons;
	}
}