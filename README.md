# yii2-redis-session
Based on yii2-redis session. Can remove user's sessions by user identity id. Can get a online users.

Redis Session implements a session component using [redis](http://redis.io/) as the storage medium.
 
Redis Session requires redis version 3.0.2 or higher to work properly.

It needs to be configured with a redis [[Connection]] that is also configured as an application component.

By default it will use the `redis` application component.

To use redis Session as the session application component, configure the application as follows,
```php
[
   'components' => [
       'session' => [
           'class' => 'bscheshirwork\redis\Session',
           // 'redis' => 'redis' // id of the connection application component
       ],
   ],
]
```

## Installation

Add 
```
    "bscheshirwork/yii2-redis-sesion": "*@dev",
```
into your `composer.json` `require` section.

## Functional

Get all online user's
```php
\Yii::$app->session->getOnlineUsers();
```

Get all active session by user
```php
\Yii::$app->session->getSessionsById($userIdentityId);
```

Remove all active session by user
```php
\Yii::$app->session->destroyUserSessions($userIdentityId);
```

Get all active sessions 
```php
\Yii::$app->session->getActiveSessions();
```
