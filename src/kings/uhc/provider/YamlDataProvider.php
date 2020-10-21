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
namespace kings\uhc\provider;

use kings\uhc\KingsUHC;


class YamlDataProvider extends Provider
{

	/** @var KingsUHC $plugin */
	private $plugin;
	/** @var int */
    private $maxArenas;
    /** @var int */
    private $maxScenarios;

    /**
	 * YamlDataProvider constructor.
	 * @param KingsUHC $plugin
	 */
	public function __construct(KingsUHC $plugin)
	{
		$this->plugin = $plugin;
		$this->init();
	}

	public function init()
	{
		if (!is_dir($this->getDataFolder())) {
			@mkdir($this->getDataFolder());
		}
		if (!is_dir($this->getDataFolder() . "arenas")) {
			@mkdir($this->getDataFolder() . "arenas");
		}
		if (!is_dir($this->getDataFolder() . "saves")) {
			@mkdir($this->getDataFolder() . "saves");
		}
		$this->maxArenas = (int)$this->plugin->getConfig()->get('max_arenas', 1);
		$this->maxScenarios = (int)$this->plugin->getConfig()->get('max_scenarios', 2);
	}

	public function getMaxArenas(){
	    return $this->maxArenas;
    }

	/**
	 * @return string $dataFolder
	 */
	private function getDataFolder(): string
	{
		return $this->plugin->getDataFolder();
	}

    /**
     * @return int
     */
    public function getMaxScenarios(): int
    {
        return $this->maxScenarios;
    }
}
