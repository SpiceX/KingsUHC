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
use kings\uhc\arena\ArenaManager;
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

    public function sendSpectateArenasForm(Player $player)
    {
        $player->sendForm(new MenuForm("§l§b» §9Kings§fUHC §b«", "§7Select an arena to spectate: ",
            $this->getArenasButtons(), function (Player $player, Button $selected): void {
                /** @var Arena $arena */
                $arena = $this->getArenaManager()->getArena(explode("\n", $selected->getText())[0]);
                $arena->spectateToArena($player);
            }));
    }

    public function sendAvailableArenaNotFound(Player $player)
    {
        $form = new MenuForm("§l§b» §9Kings§fUHC §b«", "§fThe are not available uhc arenas, would you like spectate a game?",
            [
                new Button("§aSee games", new Image("https://vignette.wikia.nocookie.net/hypixelserver/images/c/c0/UHC.png", Image::TYPE_URL)),
                new Button("§cExit", new Image("https://img.pngio.com/x-icon-png-383653-free-icons-library-red-xpng-462_594.jpg", Image::TYPE_URL))
            ], function (Player $player, Button $selected): void {
                if ($selected->getValue() === 0) {
                    $this->sendSpectateArenasForm($player);
                }
            });
        $player->sendForm($form);
    }

    public function getArenasButtons()
    {
        $buttons = [];
        foreach ($this->getArenaManager()->getArenas() as $name => $arena) {
            $buttons[] = new Button($name . "\n§aPlaying: " . count($arena->players), new Image("https://mcgamer.net/img/logo/uhc.png", Image::TYPE_URL));
        }
        return $buttons;
    }

    /**
     * @return ArenaManager
     */
    private function getArenaManager(): ArenaManager
    {
        return $this->plugin->getArenaManager();
    }
}