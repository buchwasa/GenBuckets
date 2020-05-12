<?php
declare(strict_types=1);

namespace gb;

use FactionsPro\FactionMain;
use jojoe77777\FormAPI\SimpleForm;
use onebone\economyapi\EconomyAPI;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIds;
use pocketmine\block\Solid;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class Loader extends PluginBase implements Listener{

	/** @var Config */
	private $config;
	/** @var FactionMain */
	private $fPro;

	public function onEnable(){
		$this->getConfig()->save();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->config = $this->getConfig()->getAll();
		$this->fPro = $this->getServer()->getPluginManager()->getPlugin("FactionsPro");
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(!$sender->hasPermission("gb.cmd")){
			return false;
		}
		switch($command->getName()){
			case "gbucket":
				if($sender instanceof Player){
					$form = new SimpleForm(function(Player $player, $data){
						switch($data){
							case 0:
								$money = $this->config["item-genbucket-price"];
								if(EconomyAPI::getInstance()->myMoney($player) >= $money){
									$genBucket = ItemFactory::fromString($this->config["item-genbucket"]);
									$genBucket->setCount(1);
									$genBucket->getNamedTag()->setInt("genbucket", 1);
									$genBucket->setCustomName($this->config["item-genbucket-name"]);
									$player->getInventory()->addItem($genBucket);
									EconomyAPI::getInstance()->reduceMoney($player, $money);
								}else{
									$player->sendMessage($this->config["error-cannot-afford"]);
								}
								break;
							case 1:
								$money = $this->config["item-chunkbuster-price"];
								if(EconomyAPI::getInstance()->myMoney($player) >= $money){
									$chunkBuster = ItemFactory::fromString($this->config["item-chunkbuster"]);
									$chunkBuster->setCount(1);
									$chunkBuster->getNamedTag()->setInt("chunkbuster", 1);
									$chunkBuster->setCustomName($this->config["item-chunkbuster-name"]);
									$player->getInventory()->addItem($chunkBuster);
									EconomyAPI::getInstance()->reduceMoney($player, $money);
								}else{
									$player->sendMessage($this->config["error-cannot-afford"]);
								}
								break;
						}
					});

					$form->setTitle($this->config["form-title"]);
					$form->addButton($this->config["form-genbucket"]);
					$form->addButton($this->config["form-chunkbuster"]);

					$sender->sendForm($form);
				}
				break;
		}
		return true;
	}

	public function handleInteract(PlayerInteractEvent $ev){
		$player = $ev->getPlayer();
		$item = $player->getInventory()->getItemInHand();

		if(!$ev->isCancelled()){
			if($ev->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK){
				if($item->getNamedTag()->hasTag("genbucket")){
					if($this->fPro->isInFaction($player->getName()) && $this->fPro->inOwnPlot($player)){
						$this->callGenBucket($ev->getBlock(), Item::fromString($this->config["item-genbucket-placed"])->getId(), $ev->getFace());
						$item->pop();
						$player->getInventory()->setItemInHand($item);
					}else{
						$player->sendMessage($this->config["error-claim"]);
					}
				}elseif($item->getNamedTag()->hasTag("chunkbuster")){
					if($this->fPro->isInFaction($player->getName()) && $this->fPro->inOwnPlot($player)){
						$this->callChunkBuster($ev->getBlock(), $player, (int) $this->config["item-chunkbuster-radius"]);
						$item->pop();
						$player->getInventory()->setItemInHand($item);
					}else{
						$player->sendMessage($this->config["error-claim"]);
					}
				}
			}
		}
	}

	private function callGenBucket(Block $block, int $id, int $face){
		$pos = $block->asPosition();
		$x = $pos->getFloorX();
		$y = $pos->getFloorY();
		$z = $pos->getFloorZ();

		switch($face){
			case Vector3::SIDE_DOWN:
				$y--;
				break;
			case Vector3::SIDE_UP:
				$y++;
				break;
			case Vector3::SIDE_NORTH:
				$z--;
				break;
			case Vector3::SIDE_SOUTH:
				$z++;
				break;
			case Vector3::SIDE_WEST:
				$x--;
				break;
			case Vector3::SIDE_EAST:
				$x++;
				break;
		}


		while($y > 1){
			if(!($pos->getLevel()->getBlock(new Vector3($x, $y, $z))) instanceof Solid){
				$pos->getLevel()->setBlock(new Vector3($x, $y, $z), Block::get($id), false, false);
				$y--;
			}else{
				break;
			}
		}
	}

	private function callChunkBuster(Block $block, Player $player, int $radius){
		$pos = $block->asPosition();
		$posX = $pos->getFloorX();
		$posY = $pos->getFloorY();
		$posZ = $pos->getFloorZ();

		for($x = $posX - $radius; $x <= $posX + $radius; $x++){
			for($y = $posY - $radius; $y <= $posY; $y++){
				for($z = $posZ - $radius; $z <= $posZ + $radius; $z++){
					if($this->fPro->inOwnPlot($player)){ //Prevents blocks that are outside of the plot from being deleted.
						if($pos->getLevel()->getBlockIdAt($x, $y, $z) !== BlockIds::BEDROCK){
							$pos->getLevel()->setBlock(new Vector3($x, $y, $z), Block::get(BlockIds::AIR), false, false);
						}
					}
				}
			}
		}
	}
}