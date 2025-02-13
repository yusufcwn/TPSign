<?php

namespace TPSign;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\tile\Sign;
use pocketmine\level\Position;
use pocketmine\utils\TextFormat as TF;
use pocketmine\Player;
use jojoe77777\FormAPI\CustomForm;

class Main extends PluginBase implements Listener {
    
    private $signData = [];
    private $dataFile;
    
    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        if(!class_exists(CustomForm::class)){
            $this->getLogger()->error("FormAPI plugini bulunamadı! Lütfen jojoe77777'nin FormAPI pluginini yükleyin.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }
        
        $this->dataFile = $this->getDataFolder() . "signs.json";
        @mkdir($this->getDataFolder());
        $this->loadSigns();
    }
    
    public function onDisable() {
        $this->saveSigns();
    }
    
    private function loadSigns() {
        if(file_exists($this->dataFile)) {
            $content = file_get_contents($this->dataFile);
            $this->signData = json_decode($content, true) ?? [];
        }
    }
    
    private function saveSigns() {
        file_put_contents($this->dataFile, json_encode($this->signData));
    }

    public function onSignChange(SignChangeEvent $event) {
        $player = $event->getPlayer();
        $lines = $event->getLines();
        
        if(strtolower($lines[0]) === "[teleport]") {
            if(!$player->hasPermission("tpsign.create")) {
                $player->sendMessage(TF::RED . "Teleport tabelası oluşturma izniniz yok!");
                $event->setCancelled();
                return;
            }
            
            $event->setLine(0, TF::BLUE . "[Teleport]");
            $this->openTeleportForm($player, $event->getBlock()->asPosition());
        }
    }

    public function onInteract(PlayerInteractEvent $event) {
        $block = $event->getBlock();
        $player = $event->getPlayer();
        
        if($block->getId() === 63 || $block->getId() === 68) { // Tabela blokları
            $tile = $player->getLevel()->getTile($block);
            if($tile instanceof Sign) {
                $text = $tile->getText();
                if(trim($text[0]) === TF::BLUE . "[Teleport]") {
                    $pos = $block->asPosition();
                    $key = $pos->getX() . ":" . $pos->getY() . ":" . $pos->getZ() . ":" . $pos->getLevel()->getName();
                    
                    if(isset($this->signData[$key])) {
                        $data = $this->signData[$key];
                        $level = $this->getServer()->getLevelByName($data["world"]);
                        if($level) {
                            $teleportPos = new Position($data["x"], $data["y"], $data["z"], $level);
                            $player->teleport($teleportPos);
                            $player->sendMessage(TF::GREEN . "Başarıyla ışınlandınız!");
                        } else {
                            $player->sendMessage(TF::RED . "Hedef dünya bulunamadı!");
                        }
                    }
                    $event->setCancelled();
                }
            }
        }
    }

    public function openTeleportForm(Player $player, Position $signPos) {
        $form = new CustomForm(function(Player $player, ?array $data) use ($signPos) {
            if($data === null) return;
            
            if(empty($data[0]) || empty($data[1]) || empty($data[2]) || empty($data[3]) || empty($data[4])) {
                $player->sendMessage(TF::RED . "Lütfen tüm alanları doldurun!");
                return;
            }
            
            $world = $data[0];
            $x = (float) $data[1];
            $y = (float) $data[2];
            $z = (float) $data[3];
            $description = $data[4];
            
            if(!$this->getServer()->getLevelByName($world)) {
                $player->sendMessage(TF::RED . "Belirtilen dünya bulunamadı!");
                return;
            }
            
            $key = $signPos->getX() . ":" . $signPos->getY() . ":" . $signPos->getZ() . ":" . $signPos->getLevel()->getName();
            $this->signData[$key] = [
                "world" => $world,
                "x" => $x,
                "y" => $y,
                "z" => $z
            ];
            
            $tile = $player->getLevel()->getTile($signPos);
            if($tile instanceof Sign) {
                $tile->setText(
                    TF::BLUE . "[Teleport]",
                    TF::GREEN . $description,
                    TF::YELLOW . "Tıkla ve Işınlan!",
                    ""
                );
            }
            
            $player->sendMessage(TF::GREEN . "Teleport tabelası başarıyla ayarlandı!");
        });
        
        $form->setTitle("Teleport Noktası Ayarla");
        $form->addInput("Dünya Adı:", "world");
        $form->addInput("X Koordinatı:", "0");
        $form->addInput("Y Koordinatı:", "0");
        $form->addInput("Z Koordinatı:", "0");
        $form->addInput("Açıklama:", "Teleport Noktası");
        
        $player->sendForm($form);
    }
}
