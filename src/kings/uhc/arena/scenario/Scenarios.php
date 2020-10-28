<?php

namespace kings\uhc\arena\scenario;

use ReflectionClass;

final class Scenarios
{
    public const CUTCLEAN = 'CutClean'; // ok
    public const DIAMONDLESS = 'Diamondless'; // ok
    public const GOLDLESS = 'Goldless'; // ok
    public const BLOOD_DIAMONDS = 'BloodDiamonds'; // ok
    public const TIME_BOMB = 'TimeBomb'; // ok
    public const BAREBONES = 'BareBones'; // ok
    public const SOUP = 'Soup'; // ok
    public const BOWLESS = 'Bowless'; // ok
    public const FIRELESS = 'Fireless'; // ok
    public const LIMITATIONS = 'Limitations'; // ok
    public const BED_BOMB = 'BedBomb'; // ok
    public const BLAST_MINING = 'BlastMining';
    public const CAT_EYES = 'CatEyes'; // ok
    public const GAMBLE = 'Gamble'; // ok
    public const DOUBLE_ORES = 'DoubleOres'; // ok
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