<?php

namespace bscheshirwork\redis\tests\unit;


use yii\web\IdentityInterface;

class SessionTest extends TestCase
{

    private function mockWithUser($userIdentity)
    {
        $mockUser = $this->getMockBuilder(\yii\web\User::class)
            ->setMethods(['init', 'getIdentity'])
            ->getMock();
        $mockUser->expects($this->once())->method('getIdentity')->will($this->returnValue($userIdentity));

        $this->mockWebApplication([
            'components' => [
                'user' => $mockUser,
            ],
        ]);
    }

    /**
     * Get user identity object for mockUser
     * @param string|int $id
     * @return IdentityInterface
     */
    private function getIdentityObject($id)
    {
        $identityObject = new class implements IdentityInterface
        {
            public $id;
            public $authKey;

            public static function findIdentity($id)
            {
            }

            public static function findIdentityByAccessToken($token, $type = null)
            {
            }

            public function getId()
            {
                return $this->id;
            }

            public function getAuthKey()
            {
                return $this->authKey;
            }

            public function validateAuthKey($authKey)
            {
                return $this->authKey === $authKey;
            }
        };
        $identityObject->id = $id;

        return $identityObject;
    }

    /**
     * @return array
     */
    public function sessionDataProvider()
    {
        return [
            [$this->getIdentityObject(1), 'sessionId_1', 'sessionData_1'],
            [null, 'sessionId_2', 'sessionData_2'],
            [$this->getIdentityObject(3), 'sessionId_3', 'sessionData_3'],
        ];
    }

    /**
     * Test write session
     * @dataProvider sessionDataProvider
     * @param null|IdentityInterface $userIdentity
     * @param string $sessionId
     * @param string $sessionData
     */
    public function testWriteSession(?IdentityInterface $userIdentity, string $sessionId, string $sessionData)
    {
        $this->mockWithUser($userIdentity);
        /** @var \bscheshirwork\redis\Session $session */
        $session = \Yii::$app->session;
        $this->assertTrue($session->writeSession($sessionId, $sessionData));
    }

    /**
     * Test destroy session
     * @dataProvider sessionDataProvider
     * @param null|IdentityInterface $userIdentity
     * @param string $sessionId
     * @param string $sessionData
     */
    public function testDestroySession(?IdentityInterface $userIdentity, string $sessionId, string $sessionData)
    {
        $this->mockWebApplication();
        /** @var \bscheshirwork\redis\Session $session */
        $session = \Yii::$app->session;
        $this->assertTrue($session->destroySession($sessionId));
    }

    /**
     * Test destroy user session
     * @dataProvider sessionDataProvider
     * @param null|IdentityInterface $userIdentity
     * @param string $sessionId
     * @param string $sessionData
     */
    public function testDestroyUserSessions(?IdentityInterface $userIdentity, string $sessionId, string $sessionData)
    {
        $this->mockWithUser($userIdentity);
        /** @var \bscheshirwork\redis\Session $session */
        $session = \Yii::$app->session;
        $this->assertTrue($session->writeSession($sessionId, $sessionData));
        if ($userIdentity ?? false && $userIdentity instanceof IdentityInterface) {
            $this->assertTrue($session->destroyUserSessions($userIdentity->getId()));
        }
    }

    public function testRemoveExpired()
    {
        $this->mockWebApplication();
        $session = \Yii::$app->session;
        $this->assertTrue($session->removeExpired());
    }

    public function testGetOnlineUsers()
    {
        $time = [];
        foreach ($this->sessionDataProvider() as [$userIdentity, $sessionId, $sessionData]) {
            if (($userIdentity ?? false) && ($userIdentity instanceof IdentityInterface) && ($key = $userIdentity->getId())) {
                $this->mockWithUser($userIdentity);
                /** @var \bscheshirwork\redis\Session $session */
                $session = \Yii::$app->session;
                $this->assertTrue($session->writeSession($sessionId, $sessionData));
                $time[$key] = time();
            }
        }

        $this->mockWebApplication();
        /** @var \bscheshirwork\redis\Session $session */
        $session = \Yii::$app->session;
        $this->assertEquals([1 => (string)$time[1], 3 => (string)$time[3]], $session->getOnlineUsers());
    }

    public function testGetSessionsById()
    {
        $this->mockWebApplication();
        /** @var \bscheshirwork\redis\Session $session */
        $session = \Yii::$app->session;

        $this->assertEquals(['sessionData_1'], $session->getSessionsById(1));
    }

    public function testGetActiveSessions()
    {
        $this->mockWebApplication();
        /** @var \bscheshirwork\redis\Session $session */
        $session = \Yii::$app->session;

        $this->assertEquals(['sessionData_1', 'sessionData_3'], $session->getActiveSessions());
    }

}
