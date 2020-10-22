<?php


namespace kings\uhc;


use kings\uhc\entities\Leaderboard;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player;

class UHCListener implements Listener
{

    /** @var KingsUHC */
    private $plugin;

    /**
     * UHCListener constructor.
     * @param KingsUHC $plugin
     */
    public function __construct(KingsUHC $plugin)
    {
        $this->plugin = $plugin;
    }

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

    public function onPlayerQuit(PlayerQuitEvent $event)
    {
        $player = $event->getPlayer();
        $this->plugin->getJoinGameQueue()->leaveQueue($player);
    }
}