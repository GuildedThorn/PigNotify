<?php


namespace CupidonSauce173\PigraidNotifications;


use CupidonSauce173\PigraidNotifications\Object\Notification;
use CupidonSauce173\PigraidNotifications\task\CheckDisplayed;
use CupidonSauce173\PigraidNotifications\task\CheckNotifications;
use CupidonSauce173\PigraidNotifications\Utils\API;
use CupidonSauce173\PigraidNotifications\Utils\DatabaseProvider;
use jojoe77777\FormAPI\FormAPI;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;

use Thread;
use Volatile;

use function explode;
use function file_exists;
use function array_map;
use function parse_ini_file;

class NotifLoader extends PluginBase implements Listener
{
    # Contains all notification objects by player 'CupidonSauce173' => [list of notifications objects].
    public array $notificationList = [];
    public FormAPI $form;

    private API $api;

    public array $config;
    public array $DBInfo;
    public array $langKeys;

    public static NotifLoader $instance;

    public Thread $thread;
    public Volatile $sharedStore;

    public function onEnable()
    {
        self::$instance = $this;
        if (!file_exists($this->getDataFolder() . 'config.yml')) {
            $this->saveResource('config.yml');
        }
        if (!file_exists($this->getDataFolder() . 'langKeys.ini')) {
            $this->saveResource('langKeys.ini');
        }
        $this->langKeys = array_map('\stripcslashes', parse_ini_file($this->getDataFolder() . 'langKeys.ini', false, INI_SCANNER_RAW));
        $this->api = new API();
        $config = new Config($this->getDataFolder() . 'config.yml', Config::YAML);
        $this->config = $config->getAll();
        $this->DBInfo = $this->config['MySQL'];

        new DatabaseProvider();

        $this->sharedStore = new Volatile();
        $this->thread = new CheckNotifications([], $this->DBInfo, $this->notificationList, $this->sharedStore);
        $this->thread->start();
        # Schedule Async data every check-time seconds.
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function (): void {
                if ($this->thread->isRunning() === false) {
                    $names = [];
                    foreach ($this->getServer()->getOnlinePlayers() as $player) {
                        $names[] = $player->getName();
                    }
                    $this->thread = new CheckNotifications($names, $this->DBInfo, $this->notificationList, $this->sharedStore);
                    $this->thread->start() && $this->thread->join();
                    foreach ($names as $name) {
                        if (!isset($this->sharedStore['notifications'][$name])) return;
                        foreach ($this->sharedStore['notifications'][$name] as $data) {
                            $notif = new Notification();
                            $notif->setId((int)$data['id']);
                            $notif->setPlayer($data['player']);
                            $notif->setEvent($data['event']);
                            $notif->setLangKey($data['langKey']);
                            $notif->setVarKeys(explode(',', $data['varKeys']));
                            $notif->setDisplayed((bool)$data['displayed']);
                            $this->notificationList[$data['player']][] = $notif;
                        }
                    }
                }
            }
        ), $this->config['check-database-task'] * 20);

        # Task to check if the notifications has been displayed to the player.
        $this->getScheduler()->scheduleRepeatingTask(new class extends Task {
            public function onRun(int $currentTick)
            {
                foreach (NotifLoader::getInstance()->getServer()->getOnlinePlayers() as $player) {
                    if (!isset(NotifLoader::getInstance()->notificationList[$player->getName()])) return;
                    /** @var Notification $notification */
                    foreach (NotifLoader::getInstance()->notificationList[$player->getName()] as $notification) {
                        if ($notification->hasBeenDisplayed() !== true) {
                            $player = NotifLoader::getInstance()->getServer()->getPlayer($notification->getPlayer());
                            $player->sendMessage(NotifLoader::getInstance()->TranslateNotification($notification));
                            $notification->setDisplayed(true);
                        }
                    }
                }
            }
        }, $this->config['check-displayed-task'] * 20);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->form = $this->getServer()->getPluginManager()->getPlugin('FormAPI');

        $this->getServer()->getCommandMap()->register('Notifications', new NotifCommand());
    }

    /**
     * @return NotifLoader
     */
    public static function getInstance(): self
    {
        return self::$instance;
    }

    public function onLoad()
    {
        self::$instance = $this;
    }

    # API Section

    public function createNotification(Player $player, string $langKey, string $event, array $varKeys = null): void
    {
        $this->api->createNotification($player, $langKey, $event, $varKeys);
    }

    public function getPlayerNotifications(string $player): array
    {
        if(!isset($this->notificationList[$player])) $this->notificationList[$player] = [];
        return $this->notificationList[$player];
    }

    public function deleteNotification(Notification $notification): void
    {
        $this->api->deleteNotification($notification);
    }

    public function deleteNotifications(array $notificationList): void
    {
        $this->api->deleteNotifications($notificationList);
    }

    public function GetText(string $messageKey, array $LangKeys = null): string
    {
        return $this->api->GetText($messageKey, $LangKeys);
    }

    public function TranslateNotification(Notification $notification): string
    {
        return $this->api->TranslateNotification($notification);
    }

    # Events Section

    public function onLeave(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();
        /** @var Notification $notification */
        if (!isset($this->notificationList[$player->getName()])) return;
        $this->deleteNotifications($this->getPlayerNotifications($player->getName()));
    }
}