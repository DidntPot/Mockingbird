<?php

namespace ethaniccc\Mockingbird\tasks;

use ethaniccc\Mockingbird\user\User;
use pocketmine\scheduler\Task;

class KickTask extends Task{

    private $user;
    private $message;

    public function __construct(User $user, string $message){
        $this->user = $user;
        $this->message = $message;
    }

    public function onRun(int $currentTick){
        $player = $this->user->player;
        $player->kick($this->message, false);
    }

}