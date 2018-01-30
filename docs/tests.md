# Тестирование модулей и расширений для Yii2

Инструкция по применению. Для примера будем использовать `Travis CI` и пробник `PHPStorm`а, так как для него я нашёл статью о 
тестировании с помощью `Docker`а.  
Последнее оказалось полезным при создании первичного набора тестов с поиском ошибок в самих тестах. 
Окружение для докера было взято такое же, как то, в котором будет работать код.

## Теория: PHPUnit

Используем `phpunit.xml.dist` для определения поведения `PHPUnit` - как в контейнере докера так и на `travis-ci.org`

```xml
<?xml version="1.0" encoding="utf-8"?>
<phpunit bootstrap="./tests/bootstrap.php"
		colors="true"
		convertErrorsToExceptions="true"
		convertNoticesToExceptions="true"
		convertWarningsToExceptions="true"
		stopOnFailure="false">
		<testsuites>
			<testsuite name="Test Suite">
				<directory>./tests</directory>
			</testsuite>
		</testsuites>
</phpunit>
```
Также в `.gitignore` добавлен `phpunit.xml` в котором локально можно переопределить некоторые моменты, если понадобится.

Указаны следующие моменты - начальная загрузка `./tests/bootstrap.php` и рабочая папка `./tests`

### Что мы получили

С указанным выше `phpunit.xml.dist` будут просмотрены все файлы, заканчивающиеся на `*Test.php`, и использованы все 
найденные в этих файлах классы, названия которых заканчиваются на `*Test`.

Примерно оценить список классов, которые будут задействованы, можно так:
```sh
find tests/ -type f -name *Test.php -print0 |
  xargs -0 grep --color 'class .*Test '
```

Самый простой вид теста представляет собой...

    Метод класса, начинающийся на `test`,  
    в классе, заканчивающемся на `Test`,  
    и наследующем от `\PHPUnit\Framework\TestCase`.  

```php
<?php
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase {
    public function testExample() {
        $this->assertTrue(true);
    }
}
```

[источник](https://www.alexeykopytko.com/2016/phpunit/)


## Тесты - структура

Воспользумся примером официльных расширений, например `yiisoft/yii2-redis`. Скопируем и изменим следующие файлы:

- `tests/bootstrap.php` - начальная загрузка. Тут определяем все константы окружения тестов, 
подключаем автозагрузчик `composer`а и вызываем "главный файл" фреймворка;  
добавляем несколько алиасов: на папку `src` соотносим алиас с именем расширения - как если бы использовалось из приложения;  
добавляем алиас соответствующий неймспейсу для тестов, например `@bscheshirwork/redis/tests/unit` (В оригинале 
используется `yiisoftunit/extensions/gii` для классов тестов):
```
Yii::setAlias('@yiiunit/extensions/gii', __DIR__); // для тестов
Yii::setAlias('@yii/gii', dirname(__DIR__)); // алиас такой же, как после парсера вендоров
```

- `tests/compatibility.php` - Нужен для обратной совместимости PHPUnit (в 6й версии поломали BC) 

- `tests/TestCase.php` - Основной класс, определяющий как будут создаватся `yii2` приложения.  
От него наследуются тесты как непосредственно, так и с использованием ещё одного уровня вложения.

Для `TestCase` не забываем использовать выбраный для тестов неймспейс.

В качестве примера послужит тест соединения: `tests/RedisConnectionTest.php` - его также помещаем в выбраный немспейс для тестов.

Конфиурация представлена двумя файлами `tests/data/config.php` а также добавленного в `.gitignore`
`tests/data/config.local.php`, переопределяющего элементы для использования в локальном тестировании посредством `Docker`а
```php
<?php
$config['databases']['redis']['hostname'] = 'redis';
```


## Travis CI

Выжимка из документации, адаптированная, тезисная.

1. Необходимо завести аккаунт на `https://travis-ci.org/` (так как у нас `open source`). 
Аккаунт создаём, естественно, используя github oauth, так как будем использовать интеграцию. При таком методе создания
все хуки создаются автоматически.
 
2. Указать какой нужен репозиторий, например `bscheshirwork/yii2-resis-session`

3. Добавить инструкции в файл для тревиса в файле `.travis.yml`.  
Данный файл был взят от родительского `yiisoft/yii2-кувшы`, т.к. тестироватся будет (в том числе и) надстройки над gii

```yml
language: php

php:
- 7.1
- 7.2

sudo: false

services:
  - redis-server

# cache vendor dirs
cache:
  directories:
    - $HOME/.composer/cache
    - $HOME/.bin

install:
  - travis_retry composer self-update && composer --version
  - export PATH="$HOME/.composer/vendor/bin:$PATH"
  - travis_retry composer install --prefer-dist --no-interaction

script:
  - phpunit --verbose $PHPUNIT_FLAGS
```

Отличия: тесты на php 7 и выше; Удалено всё лешнее.  

Настройки через файл закончены, можно проверить.

4. Первый запуск тестов необходимо провести обязательно `push`ем (требования `travis-ci.org`). 

5. Профит.  
При настройках по умолчанию репозитория на `travis-ci.org` будут проверятся как главная так и остальные ветки.  
Эту и другие настройки можно поменять на сайте, выбрав шестерёнку около репозитория. 


Вы можете просматривать все билды с своей страницы travis-ci.org. 
Вывод будет наподобие такого:

```
bscheshirwork / yii2-redis-session
build:passed

    Current
    Branches
    Build History
    Pull Requests

master some fix

    Commit 0975aa6
    Compare bb8dd6d..0975aa6
    Branch master

bscheshirwork authored and committed
#2 passed

    Ran for 48 sec
    Total time 1 min 30 sec
    about 6 hours ago
```


Отлично, но что делать, если тесты не запускаются с полпинка? Лучше, конечно, иметь возможность проверить сначала локально.

## Docker + PHPStorm

По [этой](https://blog.jetbrains.com/phpstorm/2016/11/docker-remote-interpreters/) ссылке размещена инструкция для владельцев
PHPStorm, попробуйте её.  
Вольный перевод далее (некоторые места в новых версиях отличаются от опубликованого)

Пока что же пример файла композиции (с комментариями), который будет использоватся: 
```yml
# Local run unit tests
# for docker-compose CLI like this manual https://blog.jetbrains.com/phpstorm/2016/11/docker-remote-interpreters/
# run directly:
# docker-compose -f ./tests/docker-compose.yml run --rm --entrypoint bash php
# and run `composer install` before first time use
version: '2'
services:
  php:
    image: bscheshir/codeception:php7.2.1-fpm-alpine-yii2 #contain phpunit
    volumes:
      - ..:/var/www/html #src and tests shared to container
      - ~/.composer/cache:/root/.composer/cache
    environment:
      TZ: Europe/Moscow
      XDEBUG_CONFIG: "remote_host=192.168.0.83 remote_port=9002 remote_enable=On"
      PHP_IDE_CONFIG: "serverName=codeception"
    depends_on:
      - redis
  redis:
    image: redis:4.0.7-alpine
    restart: always
    ports:
      - "6379"
```

### Удалённая интерпритация с помощью `Docker`а

1. Вы можете добавить новый интерпритатор, выбрав в настройках (File->Settings...)
`Languages & Frameworks`, главную ветку `PHP` (не раворачивая), на этой странице кликните кнопку […] после выпадающего списка `CLI interpreter`.  
![default](https://user-images.githubusercontent.com/5769211/30320405-2c3c3bf2-97bb-11e7-96ac-649beb63d8fa.png)
После чего нажмите зелёный плюс [+] и выбирете удалённый (From Docker, Vagrat, VM, Remote).  
![default](https://user-images.githubusercontent.com/5769211/30320451-53d58a56-97bb-11e7-8db0-1247ca145f98.png)
На данной странице выберете `Docker` либо `Docker-compose`  
![default](https://user-images.githubusercontent.com/5769211/30320664-fea2d984-97bb-11e7-8623-f1b2326a159a.png)

В появившемия меню, во-первых, выберете подключение к службе Docker, к примеру это будет `unix socket`.   
> Уже после этого будет появлятся встроенные инструменты для работы с докером (по умолчанию - внизу, возле отладки, контроля версий, терминала)

Тепрь выберете образ, который будет использоватся для создания контейнера с PHP и, при необходимости, путь к установке PHP
Для образа, основаного на официальном docker/php это было не нужно (просто использовать `php`).  
Вместо выбора образа удобно использовать файл композиции (приведён выше, добавлен в старших относительно инструкции версиях),
так как его можно собрать из аналогичного - для работы с приложениями на основе шаблонов yii2, к примеру от 
[bscheshirwork/docker-yii2-app-advanced-redis](https://github.com/bscheshirwork/docker-yii2-app-advanced-redis/blob/master/docker-codeception-run/docker-compose.yml)  
Указываем расположение `./tests/docker-compose.yml` - будет взято относительно корня проекта.

Нажатие стрелочек "обновить" рядом с `PHP executable` = `php` даст информацию о версии php. Применяем изменения и следуем далее.
![default](https://user-images.githubusercontent.com/5769211/30320717-28622e14-97bc-11e7-9e29-f6ad481c9913.png)

`PHPStorm` создаст свой собственный контейнер на основании предоставленных данных, названый набодобие `/phpstorm_helpers_PS-172.3968.35`
Для проверок также будут созданы контейнеры `/tests_php_run_1`..`/tests_php_run_n` либо `/c178145a759e_tests_php_1` которые, как не странно, копятся.
Это отлично видно в этом самом встроенном инструменте контроля докера.


2. Далее в опциях (File->Settings...) находим фреймворки тестирования `Languages & Frameworks`, `PHP` ветка `Test Frameworks`  
В ней добавляем `PHPUnit by remote interpreter`, используя кнопку зелёного плюса [+] и выпадающее меню.  
![default](https://user-images.githubusercontent.com/5769211/30320887-c172d270-97bc-11e7-9896-6b8941ae6724.png)
Указываем тип, созданный в предыдущем пункте, переходем к следующим настройкам:  
![default](https://user-images.githubusercontent.com/5769211/30320944-fbe5061c-97bc-11e7-93df-c26784c5ec75.png)

Важно! Указываем путь ВНУТРИ контейнера к тому месту, где установлен PHPUnit либо к автозагрузчику `composer`а, 
его содержащего. Для используемого образа этот путь равен `/repo/vendor/autoload.php`

Применяем изменения и возвращаемся в главное окно IDE.


3. Настройка запуска осуществляется через меню (Run->Edit configurations...)  

Вы можете добавить новую конфигурацию используя кнопку плюс [+] и выбрав PHPUnit (в конце списка).

![default](https://user-images.githubusercontent.com/5769211/30321020-41c3e4be-97bd-11e7-93a4-e076ccd66008.png)

В данном меню выберем необходимый нам файл конфигурации для тестов. Выберем использование альтернативного файла 
конфигурации соответствующей галочкой В данном меню выбор происходит относительно машины разработчика, 
не относительно контейнера. 
![default](https://user-images.githubusercontent.com/5769211/30321097-88a4a904-97bd-11e7-89fe-fcfdf6bf7aaf.png)

Применяем изменения и возвращаемся в главное окно IDE.

Наконец-то можно подтвердить изменения и запустить тесты с помощью кнопки с изображением зелёного треугольника...

Но сначала нужно установить зависимости того кода, который мы будем тестировать. Для установки зависимостей `composer`а 
воспользуемся всё тем же образом и композицеей, которые использовали для создания образа тестов.
Запустим в контейнере `composer update`:
```sh
$ docker-compose -f ./tests/docker-compose.yml run --rm --entrypoint composer php update -vvv
```

Напоминаю, что ими будет считатся методы, начинающиеся на `test` классов-наследников `\PHPUnit\Framework\TestCase` в файлах `*Test.php` папки `tests`.

Все они будут выполнены по порядку.

Например, вывод может выглядить так:
```sh
Testing started at 13:10 ...
docker-compose://[/home/dev/projects/yii2-redis-session/tests/docker-compose.yml]:php/php /repo/vendor/phpunit/phpunit/phpunit --configuration /var/www/html/phpunit.xml.dist --teamcity
PHPUnit 6.5.1 by Sebastian Bergmann and contributors.



Time: 108 ms, Memory: 8.00MB

OK (26 tests, 52 assertions)

Process finished with exit code 0
``` 