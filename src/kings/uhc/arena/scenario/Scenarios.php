<?php

namespace kings\uhc\arena\scenario;

use ReflectionClass;

final class Scenarios
{
    public const CUTCLEAN = 'CutClean';
    public const DIAMONDLESS = 'Diamondless';
    public const GOLDLESS = 'Goldless';
    public const BLOOD_DIAMONDS = 'BloodDiamonds';
    public const TIME_BOMB = 'TimeBomb';
    public const BAREBONES = 'BareBones';
    public const SOUP = 'Soup';
    public const BOWLESS = 'Bowless';
    public const FIRELESS = 'Fireless';
    public const LIMITATIONS = 'Limitations';
    public const BED_BOMB = 'BedBomb';
    public const BLAST_MINING = 'BlastMining';
    public const CAT_EYES = 'CatEyes';
    public const GAMBLE = 'Gamble';
    public const DOUBLE_ORES = 'DoubleOres';
    public const TREE_CAPITATOR = 'TreeCapitator';

    /**
     * @return string
     */
    public static function getRandomScenario(): string
    {
        $oClass = new ReflectionClass(self::class);
        $constants = $oClass->getConstants();
        return $constants[array_rand($constants)];
    }

    /**
     * @return array
     */
    public static function getScenarios(): array
    {
        $scenarios = [];
        $oClass = new ReflectionClass(self::class);
        foreach ($oClass->getConstants() as $key => $constant) {
            $scenarios[] = $constant;
        }
        return $scenarios;
    }
}