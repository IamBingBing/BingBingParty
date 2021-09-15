<?php
namespace bingbing;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\Player;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerJoinEvent;

class BingBingParty extends PluginBase implements Listener
{

    public $player = [];

    static $instance;

    public function onEnable()
    {
        $this->getServer()
            ->getPluginManager()
            ->registerEvents($this, $this);
        @mkdir($this->getDataFolder());
        $this->data = new Config($this->getDataFolder() . "data.json", Config::JSON, [

            "party" => [],
            "player" => []
        ]);
        $this->db = $this->data->getAll();
        self::$instance = $this;
    }

    public function join(PlayerJoinEvent $event)
    {
        $this->db["player"][strtolower($event->getPlayer()->getName())] = [
            "초대" => "",
            "파티" => ""
        ];
    }

    public static function getInstance(): BingBingParty
    {
        return self::$instance;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if ($command == "파티" && $sender instanceof Player) {
            $pk = new ModalFormRequestPacket();
            $pk->formId = 20200227;
            $pk->formData = $this->partyhomeUI();
            $sender->dataPacket($pk);
            return true;
        }
        if ($command == "초대수락") {
            if ($this->db["player"][strtolower($sender->getName())]["초대"] != "") {
                array_push($this->db["party"][$this->db["player"][strtolower($sender->getName())]["초대"]], strtolower($sender->getName()));
                $this->db["player"][strtolower($sender->getName())]["초대"] = "";

                $this->db["player"][strtolower($sender->getName())]["파티"] = $this->db["player"][strtolower($sender->getName())]["초대"];
                return true;
                $sender->sendMessage("초대를 수락했습니다");
            }
        }
        if ($command == "초대거절") {
            if ($this->db["player"][strtolower($sender->getName())]["초대"] != "") {
                $this->db["player"][strtolower($sender->getName())]["초대"] = "";
                $this->db["player"][strtolower($sender->getName())]["party"] = "";
                return true;
                $sender->sendMessage("초대를 거절했습니다");
            }
        }
    }

    public function getpartyarray(string $partyname): array
    {
        return $this->db["party"][strtolower($partyname)];
    }

    public function save()
    {
        $this->data->setAll($this->db);

        $this->data->save();
    }

    public function partyhomeUI()
    {
        $value = [
            "type" => "form",
            "title" => "빙빙파티 UI",
            "content" => "파티 관련 UI",
            "buttons" => [
                [

                    'text' => '파티 생성'
                ],
                [

                    'text' => '파티 삭제'
                ],
                [

                    'text' => '파티 탈퇴'
                ],
                [

                    "text" => "파티 정보"
                ],
                [
                    'text' => '파티 채팅'
                ],
                [
                    'text' => '파티 초대'
                ]
            ]
        ];

        return json_encode($value);
    }

    public function partylist($play)
    {
        $player = [];
        foreach ($this->db["party"][strtolower($play)] as $p) {

            array_push($player, [
                "type" => "label",
                'text' => $p
            ]);
        }
        $value = [
            "type" => "custom_form",
            "title" => "빙빙파티 UI",
            "content" => $player
        ];

        return json_encode($value);
    }

    public function getParty(string $player): string
    {
        return $this->db["player"][strtolower($player)]["파티"];
    }

    public function addplayer($party, $name)
    {
        if ($this->getServer()->getPlayer($name) != null) {
            if (! $this->is_party($name)) {
                $this->getServer()
                    ->getPlayer($name)
                    ->sendMessage($party . " 님의 파티에서 초대가 왔습니다. /초대수락 /초대거절 ");
                $this->db["player"][$name] = [
                    "초대" => $party
                ];
            }
        } else {
            $this->getServer()
                ->getPlayer($party)
                ->sendMessage("존재하지 않거나 이미 파티에 가입되어 있는 플레이어");
        }
    }

    public function DataRerecieve(DataPacketReceiveEvent $event)
    {
        $pk = $event->getPacket();
        $player = $event->getPlayer();
        if ($pk instanceof ModalFormResponsePacket && $player instanceof Player) {
            if ($pk->formId == 20200227) {
                $result = json_decode($pk->formData, true);
                if ($result === 0) {
                    if (! $this->is_party(strtolower($player->getName()))) {
                        $this->db["party"][strtolower($player->getName())] = [
                            strtolower($player->getName())
                        ];
                        $this->db["player"][strtolower($player->getName())]["파티"] = strtolower($player->getName());
                        $player->sendMessage("파티 생성완료");
                        return true;
                    } else {
                        $player->sendMessage("이미 파티에 가입되어있습니다");
                        return true;
                    }
                } 
                else if ($result === 1) {
                    if ($this->is_party(strtolower($player->getName()))) {
                        // ㅠ파티 삭제
                        if (! empty($this->db["party"][strtolower($player->getName())])) {
                            foreach ($this->db["party"][strtolower($player->getName())] as $user) {
                                $this->db["player"][$user]["파티"] = "";
                            }
                            unset($this->db["party"][strtolower($player->getName())]);
                            foreach ($this->db["party"] as $key => $value) {
                                if (! empty($value)) {
                                    $this->db["party"][$key] = $value;
                                }
                            }
                            $player->sendMessage("파티 제거 완료 ");
                            return true;
                        } else {
                            $player->sendMessage("파티장이어야합니다. ");
                            return true;
                        }
                    }
                } else if ($result === 2) {
                    if ($this->is_party(strtolower($player->getName())) && empty($this->db["party"][strtolower($player->getName())])) {
                        foreach ($this->db["party"][$this->getParty(strtolower($player->getName()))] as $party) {
                            if ($party == $pk->getName()) {
                                unset($party);
                                $this->db["party"][$this->getParty(strtolower($player->getName()))] = array_values($party);
                                $this->db["player"][strtolower(strtolower($player->getName()))]["파티"] = "";
                                $player->sendMessage("파티 탈퇴 완료 ");
                                return true;
                            }
                        }
                        return true;
                    }
                } else if ($result === 3) {
                    if ($this->is_party(strtolower($player->getName()))) {
                        $pk = new ModalFormRequestPacket();
                        $pk->formId = 202002271;
                        $pk->formData = $this->partylist($this->db["player"][strtolower($player->getName())]["파티"]);
                        $player->dataPacket($pk);
                        return true;
                    }
                } else if ($result === 4) {
                    if ($this->is_party(strtolower($player->getName()))) {
                        $pk = new ModalFormRequestPacket(); // chat
                        $pk->formId = 202002272;
                        $pk->formData = $this->PartyChatUI();
                        $player->dataPacket($pk);
                        return true;
                    }
                } else if ($result === 5) {
                    if ($this->is_party(strtolower($player->getName()))) {
                        $pk = new ModalFormRequestPacket();
                        $pk->formId = 202002273;
                        $pk->formData = $this->PartyAddPlayer();
                        $player->dataPacket($pk);
                        return true;
                    }
                }
            } 
            else if ($pk->formId == 202002272) {
                $result = json_decode($pk->formData, true);
                $this->sendPartyChat($player, $result[0]);
            } else if ($pk->formId == 202002273) {
                $result = json_decode($pk->formData, true);
                $this->addplayer(strtolower($player->getName()), $result[0]);
            }
        }
    }

    public function PartyChatUI()
    {
        $value = [

            "type" => "custom_form",
            "title" => "파티 UI",
            "content" => [

                [
                    'type' => 'input',
                    'text' => '파티 채팅',
                    'default' => '채팅을 처주세요'
                ]
            ]
        ];
        return json_encode($value);
    }

    public function PartyAddPlayer()
    {
        $value = [
            "type" => "custom_form",
            "title" => "파티 UI",
            "content" => [
                [
                    'type' => 'input',
                    'text' => '파티 초대',
                    'default' => '마크 닉네임'
                ]
            ]
        ];
        return json_encode($value);
    }

    public function sendPartyChat(Player $player, $chat)
    {
        $party = $this->getParty(strtolower($player->getName()));
        foreach ($this->db["party"][$party] as $name) {
            $this->getServer()
                ->getPlayer($name)
                ->sendMessage("§f[ §a파티채팅 §f] " . $chat);
        }
    }

    public function is_party(string $name): bool
    {
        if ($this->db["player"][strtolower($name)]["파티"] != "") {
            return true;
        } else {
            return false;
        }
    }
}


















