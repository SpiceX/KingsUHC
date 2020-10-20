<?php


namespace kings\uhc;


use kings\uhc\entities\Leaderboard;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\Player;

class UHCListener implements Listener
{
    /**
     * @param EntityDamageByEntityEvent $event
     */
    public function onDamage(EntityDamageByEntityEvent $event)
    {
        $player = $event->getDamager();
        $npc = $event->getEntity();
        if ($player instanceof Player && $npc instanceof Leaderboard) {
            $event->setCancelled(true);
        }
    }
}