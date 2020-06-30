<?php

/*


$$\      $$\                     $$\       $$\                     $$\       $$\                 $$\ 
$$$\    $$$ |                    $$ |      \__|                    $$ |      \__|                $$ |
$$$$\  $$$$ | $$$$$$\   $$$$$$$\ $$ |  $$\ $$\ $$$$$$$\   $$$$$$\  $$$$$$$\  $$\  $$$$$$\   $$$$$$$ |
$$\$$\$$ $$ |$$  __$$\ $$  _____|$$ | $$  |$$ |$$  __$$\ $$  __$$\ $$  __$$\ $$ |$$  __$$\ $$  __$$ |
$$ \$$$  $$ |$$ /  $$ |$$ /      $$$$$$  / $$ |$$ |  $$ |$$ /  $$ |$$ |  $$ |$$ |$$ |  \__|$$ /  $$ |
$$ |\$  /$$ |$$ |  $$ |$$ |      $$  _$$<  $$ |$$ |  $$ |$$ |  $$ |$$ |  $$ |$$ |$$ |      $$ |  $$ |
$$ | \_/ $$ |\$$$$$$  |\$$$$$$$\ $$ | \$$\ $$ |$$ |  $$ |\$$$$$$$ |$$$$$$$  |$$ |$$ |      \$$$$$$$ |
\__|     \__| \______/  \_______|\__|  \__|\__|\__|  \__| \____$$ |\_______/ \__|\__|       \_______|
                                                         $$\   $$ |                                  
                                                         \$$$$$$  |                                  
                                                          \______/      
~ Made by @ethaniccc idot </3
Github: https://www.github.com/ethaniccc                             
*/ 

namespace ethaniccc\Mockingbird\cheat;

use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\Server;
use ethaniccc\Mockingbird\Mockingbird;
use pocketmine\event\Event;
use pocketmine\utils\TextFormat;

class Cheat implements Listener{

    public static $instance;

    private $cheatName;
    private $cheatType;
    private $enabled;
    private $cheatsViolatedFor = [];
    private $notifyCooldown = [];

    private $plugin;

    public function __construct(Mockingbird $plugin, string $cheatName, string $cheatType, bool $enabled = true){
        $this->cheatName = $cheatName;
        $this->cheatType = $cheatType;
        $this->enabled = $enabled;
        $this->plugin = $plugin;
        self::$instance = $this;
    }

    public function getName() : string{
        return $this->cheatName;
    }

    public function getType() : string{
        return $this->cheatType;
    }

    public function isEnabled() : bool{
        return $this->enabled;
    }

    public function getCurrentViolations(string $name) : int{
        $database = $this->getPlugin()->getDatabase();
        $currentViolations = $database->query("SELECT * FROM cheatData WHERE playerName = '$name'");
        $result = $currentViolations->fetchArray(SQLITE3_ASSOC);
        if(empty($result)){
            $newData = $database->prepare("INSERT OR REPLACE INTO cheatData (playerName, violations) VALUES (:playerName, :violations);");
            $newData->bindValue(":playerName", $name);
            $newData->bindValue(":violations", 0);
            $newData->execute();
        }
        return empty($result) ? 0 : $result["violations"];
    }

    public function setViolations(string $name, $amount) : bool{
        $database = $this->getPlugin()->getDatabase();
        $currentViolations = $database->query("SELECT * FROM cheatData WHERE playerName = '$name'");
        $result = $currentViolations->fetchArray(SQLITE3_ASSOC);
        if(empty($result)) return false;
        $newData = $database->prepare("INSERT OR REPLACE INTO cheatData (playerName, violations) VALUES (:playerName, :violations);");
        $newData->bindValue(":playerName", $name);
        $newData->bindValue(":violations", $amount);
        $newData->execute();
        return true;
    }

    public function getPlugin() : Mockingbird{
        return $this->plugin;
    }

    protected function getServer() : Server{
        return Server::getInstance();
    }

    protected function genericAlertData(Player $player) : array{
        return ["VL" => $this->getCurrentViolations($player->getName()), "Ping" => $player->getPing()];
    }

    protected function addViolation(string $name) : void{
        if($this->isLowTPS()){
            $this->getServer()->getLogger()->debug("Violation was cancelled due to low TPS");
            return;
        }
        $database = $this->getPlugin()->getDatabase();
        $currentViolations = $this->getCurrentViolations($name);
        $currentViolations++;
        $newData = $database->prepare("INSERT OR REPLACE INTO cheatData (playerName, violations) VALUES (:playerName, :violations)");
        $newData->bindValue(":playerName", $name);
        $newData->bindValue(":violations", $currentViolations);
        $newData->execute();
    }

    protected function resetViolations(string $name) : void{
        $database = $this->getPlugin()->getDatabase();
        $newData = $database->prepare("INSERT OR REPLACE INTO cheatData (playerName, violations) VALUES (:playerName, :violations)");
        $newData->bindValue(":playerName", $name);
        $newData->bindValue(":violations", 0);
        $newData->execute();
    }

    protected function notifyStaff(string $name, string $cheat, array $data) : void{
        if($this->isLowTPS()){
            $this->getServer()->getLogger()->debug("Alert was cancelled due to low TPS");
            return;
        }
        $this->getPlugin()->addCheat($name, $cheat);
        if(!isset($this->notifyCooldown[$name])){
            $this->notifyCooldown[$name] = microtime(true);
        } else {
            if(microtime(true) - $this->notifyCooldown[$name] >= 0.1){
                $this->notifyCooldown[$name] = microtime(true);
            } else {
                return;
            }
        }
        foreach($this->getServer()->getOnlinePlayers() as $player){
            if($player->hasPermission($this->getPlugin()->getConfig()->get("alert_permission"))){
                $dataReport = TextFormat::DARK_RED . "[";
                foreach($data as $dataName => $info){
                    if(array_key_last($data) !== $dataName) $dataReport .= TextFormat::GRAY . $dataName . ": " . TextFormat::RED . $info . TextFormat::DARK_RED . " | ";
                    else $dataReport .= TextFormat::GRAY . $dataName . ": " . TextFormat::RED . $info;
                }
                $dataReport .= TextFormat::DARK_RED . "]";
                $player->sendMessage($this->getPlugin()->getPrefix() . TextFormat::RESET . TextFormat::RED . $name . TextFormat::GRAY . " has failed the check for " . TextFormat::RED . $cheat . TextFormat::RESET . " $dataReport");
            }
        }
        if($this->getCurrentViolations($name) >= $this->getPlugin()->getConfig()->get("max_violations")){
            $this->getPlugin()->blockPlayerTask($this->getServer()->getPlayerExact($name));
        }
    }

    private function isLowTPS() : bool{
        return $this->getServer()->getTicksPerSecond() <= $this->getPlugin()->getConfig()->get("stop_tps");
    }

}