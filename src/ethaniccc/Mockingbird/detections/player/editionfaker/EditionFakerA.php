<?php

namespace ethaniccc\Mockingbird\detections\player\editionfaker;

use ethaniccc\Mockingbird\detections\Detection;
use ethaniccc\Mockingbird\user\User;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
/**
 * Class EditionFakerA
 * @package ethaniccc\Mockingbird\detections\player\editionfaker
 * EditionFakerA checks if a Windows 10 user is faking their edition.
 */
class EditionFakerA extends Detection{

    public const WINDOWS_10 = '896928775';
    public const ANDROID = '1739947436';
    public const NINTENDO = '2047319603';
    public const IOS = '1810924247';
    public const XBOX = '1828326430';
    public const PLAYSTATION = '2044456598';

    private $givenOS;
    private $realOS;
    // private $realOS = ['win10' => '896928775', 'mobile' => '1739947436', 'Nintendo' => '2047319603'];

    public function __construct(string $name, ?array $settings){
        parent::__construct($name, $settings);
    }

    public function handleReceive(DataPacket $packet, User $user): void{
        if($packet instanceof LoginPacket){
            // finally the reign of using Horion's EditionFaker to fucking bypass some combat checks is finally over
            try{
                $data = $packet->chainData;
                $parts = explode(".", $data['chain'][2]);
                $jwt = json_decode(base64_decode($parts[1]), true);
                $titleId = $jwt['extraData']['titleId'];
            } catch(\Exception $e){
                return;
            }
            $this->realOS = [
                self::WINDOWS_10 => DeviceOS::WINDOWS_10,
                self::ANDROID => DeviceOS::ANDROID,
                self::NINTENDO => DeviceOS::NINTENDO,
                self::IOS => DeviceOS::IOS,
                self::XBOX => DeviceOS::XBOX,
                self::PLAYSTATION => DeviceOS::PLAYSTATION
            ][$titleId] ?? DeviceOS::UNKNOWN;

            $this->givenOS = $packet->clientData["DeviceOS"];
            if($this->realOS !== $this->givenOS){
                $this->fail($user, "givenOS={$this->givenOS} realOS={$this->realOS}");
            }
        }
    }

}