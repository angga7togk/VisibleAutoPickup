<?php

/**
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author       PresentKim (debe3721@gmail.com)
 * @link         https://github.com/PresentKim
 * @license      https://www.gnu.org/licenses/lgpl-3.0 LGPL-3.0 License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace kim\present\visibleautopickup;

use onebone\economyland\EconomyLand;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\World;

final class Main extends PluginBase implements Listener
{

    private bool $economyLandIsEnabled = false;
    protected function onEnable(): void
    {
        if ($this->getServer()->getPluginManager()->getPlugin("EconomyLand") !== null) {
            $this->economyLandIsEnabled = true;
        }
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    /** @priority LOWEST */
    public function onBlockBreakEvent(BlockBreakEvent $event): void
    {
        $player = $event->getPlayer();
        if (!$player->hasFiniteResources()) {
            return;
        }
        if($this->economyLandIsEnabled) {
            if(!EconomyLand::getInstance()->canTouch($event->getBlock()->getPosition(), $player)){
                return;
            }
        }

        $world = $player->getWorld();
        $dropVec = $event->getBlock()->getPosition()->add(0.5, 0, 0.5);

        $this->dropItems($player, $world, $dropVec, $event->getDrops());
        $event->setDrops([]);

        $this->dropXpOrbs($player, $world, $dropVec, $event->getXpDropAmount());
        $event->setXpDropAmount(0);
    }

    /** @priority LOWEST */
    public function onEntityDeathEvent(EntityDeathEvent $event): void
    {
        $entity = $event->getEntity();
        $lastDamageEvent = $entity->getLastDamageCause();
        if (!($lastDamageEvent instanceof EntityDamageByEntityEvent)) {
            return;
        }

        $player = $lastDamageEvent->getDamager();
        if (!($player instanceof Player)) {
            return;
        }

        $world = $player->getWorld();
        $dropVec = $entity->getPosition()->asVector3();

        $this->dropItems($player, $world, $dropVec, $event->getDrops());
        $event->setDrops([]);

        $this->dropXpOrbs($player, $world, $dropVec, $event->getXpDropAmount() ?: 60);
        $event->setXpDropAmount(0);
    }

    /** @param Item[] $items */
    private function dropItems(Player $owner, World $world, Vector3 $dropVec, array $items): void
    {
        foreach ($items as $dropItem) {
            $itemEntity = $world->dropItem($dropVec, $dropItem, null, 20);
            if ($itemEntity === null) {
                continue;
            }

            $this->getScheduler()->scheduleDelayedTask(new ClosureTask(
                function () use ($owner, $itemEntity): void {
                    if ($itemEntity->isClosed() || $owner->isClosed()) {
                        return;
                    }

                    $itemEntity->setPickupDelay(0);
                    $itemEntity->onCollideWithPlayer($owner);
                }
            ), 10);
        }
    }

    private function dropXpOrbs(Player $owner, World $world, Vector3 $dropVec, int $xp): void
    {
        foreach ($world->dropExperience($dropVec, $xp) as $xpEntity) {
            $xpEntity->setTargetPlayer($owner);
            $this->getScheduler()->scheduleDelayedTask(new ClosureTask(
                function () use ($owner, $xpEntity): void {
                    if ($xpEntity->isClosed()) {
                        return;
                    }

                    $xpEntity->setTargetPlayer($owner);
                }
            ), 20);
        }
    }
}
