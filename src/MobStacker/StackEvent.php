<?php

declare(strict_types=1);

namespace MobStacker;

use pocketmine\Player;
use pocketmine\entity\Living;
use pocketmine\entity\Item;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\event\entity\{EntityDamageEvent, EntitySpawnEvent, EntityDeathEvent, EntityMotionEvent};

use mobstacker\mobstacker;

class StackEvent implements Listener{

    /** @var Core  */
    private $plugin;

    public function __construct(Loader $plugin){
        $this->plugin = $plugin;
    }

    public function onDamage(EntityDamageEvent $e): void{
        $entity = $e->getEntity();
        if($e->getDamage() >= $entity->getHealth()){
            if($entity instanceof Living and StackFactory::isStack($entity)){
                $entity->setLastDamageCause($e);
                if(StackFactory::removeFromStack($entity)){
                    $e->setCancelled(true);
                    $entity->setHealth($entity->getMaxHealth());
                }
                StackFactory::recalculateStackName($entity);
            }
        }
    }
    public function onMotion(EntityMotionEvent  $e): void{
        $entity = $e->getEntity();
        if($entity instanceof Living && !$entity instanceof Player){
            $e->setCancelled(true);
        }
    }
    public function onSpawn(EntitySpawnEvent $e): void{
        $entity = $e->getEntity();
        if(!$entity instanceof Living && !$entity instanceof Player) return;
        StackFactory::addToClosestStack($entity, 16);
    }
 public static function isStack($entity) : bool{
        if(!$entity instanceof Player){
            return $entity instanceof Living and (!$entity instanceof Item) and isset($entity->namedtag->StackData);
        }
        return true;
    }

    public static function getStackSize(Living $entity){
        if(!$entity instanceof Player){
            if(isset($entity->namedtag->StackData->Amount) and $entity->namedtag->StackData->Amount instanceof IntTag){
                return $entity->namedtag->StackData["Amount"];
            }
            return 1;
        }
        return true;
    }

    public static function increaseStackSize(Living $entity, $amount = 1) : bool{
        if(!$entity instanceof Player){
            if(self::isStack($entity) and isset($entity->namedtag->StackData->Amount)){
                $entity->namedtag->StackData->Amount->setValue(self::getStackSize($entity) + $amount);
                return true;
            }
            return false;
        }
        return true;
    }

    public static function decreaseStackSize(Living $entity, $amount = 1) : bool{
        if(!$entity instanceof Player){
            if(self::isStack($entity) and isset($entity->namedtag->StackData->Amount)){
                $entity->namedtag->StackData->Amount->setValue(self::getStackSize($entity) - $amount);
                return true;
            }
            return false;
        }
        return true;
    }

    public static function createStack(Living $entity, $count = 1) : bool{
        if(!$entity instanceof Player){
            $entity->namedtag->StackData = new CompoundTag("StackData", [
                "Amount" => new IntTag("Amount", $count),
            ]);
        }
        return true;
    }

    public static function addToStack(Living $stack, Living $entity) : bool{
        if(!$entity instanceof Player){
            if(is_a($entity, get_class($stack)) and $stack !== $entity){
                if(self::increaseStackSize($stack, self::getStackSize($entity))){
                    $entity->close();
                    return true;
                }
            }
            return false;
        }
        return true;
    }

    public static function removeFromStack(Living $entity) : bool{
        if(!$entity instanceof Player){
            assert(self::isStack($entity));
            if(self::decreaseStackSize($entity)){
                if(self::getStackSize($entity) <= 0) return false;
                $level = $entity->getLevel();
                $pos = new Vector3($entity->x, $entity->y, $entity->z);
                $server = $level->getServer();
                $server->getPluginManager()->callEvent($ev = new EntityDeathEvent($entity, $entity->getDrops()));
                foreach($ev->getDrops() as $drops){
                    $level->dropItem($pos, $drops);
                }
                return true;
            }
            return false;
        }
        return true;
    }

    public static function recalculateStackName(Living $entity) : bool{
        if(!$entity instanceof Player){
            assert(self::isStack($entity));
            $count = self::getStackSize($entity);
            if($count < 0){
                $count = 0;
            }
            $entity->setNameTagVisible(true);
            $entity->setNameTag("§l§ex§a{$count} {$entity->getName()}");
        }
        return true;
    }

    public static function findNearbyStack(Living $entity, $range = 16){
        if(!$entity instanceof Player){
            $stack = null;
            $closest = $range;
            $bb = $entity->getBoundingBox();
            $bb = $bb->grow($range, $range, $range);
            foreach($entity->getLevel()->getCollidingEntities($bb) as $e){
                if(is_a($e, get_class($entity)) and $stack !== $entity){
                    $distance = $e->distance($entity);
                    if($distance < $closest){
                        if(!self::isStack($e) and self::isStack($stack)) continue;
                        $closest = $distance;
                        $stack = $e;
                    }
                }
            }
            return $stack;
        }
        return true;
    }

    public static function addToClosestStack(Living $entity, $range = 16) : bool{
        if(!$entity instanceof Player){
            $stack = self::findNearbyStack($entity, $range);
            if(self::isStack($stack)){
                if(self::addToStack($stack, $entity)){
                    self::recalculateStackName($stack);
                    return true;
                }
            }else{
                if($stack instanceof Living && !$stack instanceof Player){
                    self::createStack($stack);
                    self::addToStack($stack, $entity);
                    self::recalculateStackName($stack);
                    return true;
                }
            }
            return false;
        }
        return true;
    }
}

