<?php

namespace kings\uhc;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Item;

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

    public function onPlayerQuit(PlayerQuitEvent $event)
    {
        $player = $event->getPlayer();
        $this->plugin->getJoinGameQueue()->leaveQueue($player);
    }

    public function onPlayerInteract(PlayerInteractEvent $event)
    {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $arena = $this->plugin->getJoinGameQueue()->getArenaByPlayer($player);
        if ($arena !== null) {
            switch ($item->getId()) {
                case Item::REDSTONE:
                    if ($item->getCustomName() === '§cLeave Queue') {
                        $this->plugin->getJoinGameQueue()->leaveQueue($player);
                    }
                    break;
                case Item::ENCHANTED_BOOK:
                    if ($item->getCustomName() === '§9Vote') {
                        $this->plugin->getFormManager()->sendVoteForm($player);
                    }
                    break;
            }
        }
    }

    /**
     * @param PlayerDropItemEvent $event
     */
    public function onPlayerDropItem(PlayerDropItemEvent $event)
    {
        $player = $event->getPlayer();
        if ($this->plugin->getJoinGameQueue()->inQueue($player)){
            $event->setCancelled();
        }
    }
}