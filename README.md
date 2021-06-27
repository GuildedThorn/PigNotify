# PigraidNotifications

This is a notification system designed for the now deleted Pigraid Network.

<h3>Some Important Notes</h3>

This plugin is **not** stable yet. Please do not use in any production server until further notices.
The README.md needs to be updated.

<h3>Known Issues</h3>

- Notifications won't unset.
- Notifications still show after closing them in the UI.

| **Feature**                 | **State** | 
| --------------------------- |:----------:|
| MultiThreaded System        | ✔️ |
| Notification Object         | ✔️ |
| Simple API                  | ✔️ |
| Translation System          | ✔️ |
| Command Customization       | ✔️ |
| Automated MySQL Constructor | ✔️ |

<h3>Prerequisites</h3>

- Working MySQL Server.
- FormAPI from Jojoe.

<h3>Introduction</h3>

This is a notification system working with MySQL and is multi-threaded. The plugin will fetch notifications from the MySQL server, look if they have been displayed and display the notifications to the users. The plugin contain a simple API if you want to create third-party addons. This is a part of the Pigraid Network System.

<h3>Notification Object</h3>

The notifications have few properties that you can play with. Here's the list:

| **Property** | **DataType** | **Description** |
| ------------ | :---------- | :------------- |
| $id          | Int          | Id of the notification. |
| $displayed   | Boolean      | If the notification has been displayed as message to the player. |
| $langKey     | String       | The message index from langKeys.ini |
| $varKeys     | Array        | Array of variables to change in the langKey. |
| $event       | String       | The event of the notification, like FRIEND_REQUEST |
| $player      | Player       | Target player (PMMP). |

You can get and set the properties of the notification whenver you want.

```php
# Note:
# NEVER use any ->set{property} AFTER creating a notification. This could lead to weird behavior. Only ->setDisplayed(true);.

# Get / Set Id.
$notification->setId($id);
$notification->getId(); # Returns Int.

# Get / Set Player.
$notification->setPlayer($player);
#notification->getPlayer(); # Returns pmmp/Player

# Get / Set Displayed.
$notification->setDisplayed(true|false);
$notification->hasBeenDisplayed(); # Returns Boolean.

# Get / Set langKey.
$notification->setLangKey($messageIndex);
$notification->getLangKey(); # Returns String.

# Get / Set varKeys.
$notification->setVarKeys(['target|CupidonSauce173','faction|Foo']);
$notification->getVarKeys(); # Returns Array.

# Get / Set Event.
$notification->setEvent($event);
$notification->getEvent($event); # Returns String.
```

<h3>Config File</h3>
 
The configuration file allows you to modify pretty much every aspects of the plugin. You can set the command you want and it's aliases, the permission and if the players needs the permission to use the command. You can also set the delays between checks from the database & if a notification has been displayed. Here's the list of settings you're allowed to change from the config file.

```yml

# MySQL information.
MySQL:
  database: notifications
  host: mysql_host
  username: mysql_username
  password: user_password
  port: 3306

# At how every x times the plugin will check for new notifications for the players (in seconds).
check-database-task: 2
# At how every x times the plugin will check if the notification has been displayed (if not, displays it to the player) (in seconds).
check-displayed-task: 2
# Prefix for the messages.
prefix: "Notify > "

# Set to false if you don't want to look if the user has the permission to use this command.
use-permission: false
# Permission to use this command.
permission: pig.notification.command
# Message if the player doesn't have the permission.
no-permission-message: You do not have the necessary permissions!
# Main command, /notification for example.
command-main: notification
# Aliases to use this command, /notif or /n for example.
command-aliases:
  - notif
  - n
```

<h3>API</h3>

The plugin offers a small and simple API that you can use along the notifications methods. Here are all the methods from the API.

First, you need to register the API. 

```php

public NotifLoader $api;

public function onEnable(){
   $this->api = $this->getServer()->getPluginManager()->getPlugin('PigraidNotifications');
}
```

Then, you can use the API like you wish.

```php

# List of methods in the API.

# Will create a new notification.
$api->createNotification($player, $langKey, $event, $varKeys);
$api->createNotification($player, $langKey, $event);
# Will return a list of notification objects.
$api->getPlayerNotifications($player);
# Will delete one notification, must pass a notification object.
$api->deleteNotification($notification);
# Will delete a list of notifications, must pass an array of notification objects.
$api->deleteNotifications($notifications);
# Will return a string of readable text from a messageIndex with / without langKeys.
$api->GetText($messageIndex, $langKeys);
$api->GetText($messageIndex);
# Will translate a notification using the GetText method to a readable message. 
$api->TranslateNotification($notification);


public function onDeath(PlayerDeathEvent $event){
   $player = $event->getPlayer();
   $notifCount = count($this->api->getPlayerNotifications($player));
   $player->sendMessage("Don't forget, you have $notifCount notifications!");
}

```

<h3>How it works?</h3>

First, when the server first boosts, it will check if it can establish a MySQL connection, if it can't, it will close the server. Otherwise, it will create (if the structure doesn't exists) the database and the table / columns. Then, a repeatingTask will be staarted to check at every x amount of seconds all the notifications releated to the LoggedInPlayers. It will exclude all already existing notifications in the server. Another repeatingTask is also ran to loop through all the notifications in the server and see if they have been displayed. If not, it will get the notification to a readable message and send a message to the target player and finally set the notification as "displayed". When the player disconnects from the server, all the notifications that are related to that player will be destroyed with the ->destruct() method.

<h3>How to create a notification</h3>

To create a new notification, you will need to call the $api->createNotification(); method. It won't directly show to the player that they received a notification, it will just create a new one in the database and will be waiting to get created in the server.

Basically:

```
server -> API (createNotification) -> Database <- Checknotifications Task -> server -> notificationCheckTask -> API (TranslateNotification) -> player.
```

<h3>Notes</h3>

- You don't need to handle the PlayerQuitEvent to destruct the notifications. 
- You don't need to handle the join event to look if there are notifications for the player. 
- it is **NOT** recommended to set a check value smaller than 2 seconds. This could lead to performances issues.
- Running your MySQL server in the same machine as your server is the best idea.
- **NEVER** set values in the notifications after they have been created, this could lead to weird behavior.
