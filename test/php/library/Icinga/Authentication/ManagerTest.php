<?php
// {{{ICINGA_LICENSE_HEADER}}}
// {{{ICINGA_LICENSE_HEADER}}}

namespace Tests\Icinga\Authentication;

use Icinga\Test\BaseTestCase;

use \Zend_Config;
use Icinga\Web\Session;
use Icinga\Authentication\Manager as AuthManager;
use Icinga\Authentication\Credential;
use Icinga\Test\Authentication\ErrorProneBackendMock;
use Icinga\Test\Authentication\SessionMock;
use Icinga\Test\Authentication\BackendMock;

/**
 * @backupStaticAttributes enabled
 * @runTestsInSeparateProcesses
 */
class ManagerTest extends BaseTestCase
{
    public function getTestCredentials()
    {
        return array(
            new Credential("jdoe", "passjdoe"),
            new Credential("root", "passroot"),
            new Credential("test", "passtest")
        );
    }

    public function getManagerInstance(
        &$session = null,
        $write = false,
        $nobackend = false,
        Zend_Config $managerConfig = null
    ) {
        if ($session == null) {
            $session = new SessionMock();
        }

        if ($managerConfig === null) {
            $managerConfig = new Zend_Config(array());
        }

        Session::create($session);
        $manager = AuthManager::getInstance($managerConfig);

        if ($nobackend === false) {
            $backend = new BackendMock();
            $backend->allowedCredentials = $this->getTestCredentials();
            $manager->addUserBackend($backend);
        }

        return $manager;
    }

    public function testManagerInstanciation()
    {
        $this->markTestSkipped('ErrorProneBackendMock, SessionMock and BackendMock are faulty');
        $authMgr = $this->getManagerInstance();
        $this->assertSame($authMgr, AuthManager::getInstance());
    }

    public function testManagerProducingDependencies()
    {
        $this->markTestSkipped('ErrorProneBackendMock, SessionMock and BackendMock are faulty');
        $authMgr = $this->getManagerInstance($session, true);
        $this->assertSame($authMgr, AuthManager::getInstance());

        $backend = new BackendMock();
        $backend->setCredentials($this->getTestCredentials());

        $authMgr->addUserBackend($backend);

        $this->assertTrue(
            $authMgr->authenticate(
                new Credential('jdoe', 'passjdoe')
            )
        );

        $this->assertInstanceOf('Icinga\User', $authMgr->getUser());
        $this->assertSame('Username', $authMgr->getUser()->getUsername());

        $session->isOpen = true;
        $authMgr->removeAuthorization();

        $this->assertNull($authMgr->getUser());
    }

    public function testAuthentication()
    {
        $this->markTestSkipped('ErrorProneBackendMock, SessionMock and BackendMock are faulty');
        $auth = $this->getManagerInstance();
        $this->assertFalse(
            $auth->authenticate(
                new Credential("jhoe", "passjdoe"),
                false
            )
        );
        $this->assertFalse(
            $auth->authenticate(
                new Credential("joe", "passjhoe"),
                false
            )
        );
        $this->assertTrue(
            $auth->authenticate(
                new Credential("jdoe", "passjdoe"),
                false
            )
        );
    }

    /**
     * @expectedException Icinga\Exception\ConfigurationError
     * @expectedExceptionMessage No authentication backend set
     */
    public function testErrorProneBackendsFromConfigurationWhenInitiate()
    {
        $this->markTestSkipped('ErrorProneBackendMock, SessionMock and BackendMock are faulty');
        $managerConfig = new Zend_Config(
            array(
                'provider1' => array(
                    'class' => 'Icinga\Test\Authentication\ErrorProneBackendMock'
                )
            ),
            true
        );

        ErrorProneBackendMock::$throwOnCreate = true;

        $authManager = $this->getManagerInstance($session, true, true, $managerConfig);

        $this->assertNull(
            $authManager->getUserBackend('provider1')
        );

        $authManager->authenticate(
            new Credential('jdoe', 'passjdoe')
        );
    }

    /**
     * @expectedException Icinga\Exception\ConfigurationError
     * @expectedExceptionMessage No working backend found. Unable to authenticate any
     */
    public function testErrorProneBackendsFromConfigurationWhenAuthenticate()
    {
        $this->markTestSkipped('ErrorProneBackendMock, SessionMock and BackendMock are faulty');
        $managerConfig = new Zend_Config(
            array(
                'provider1' => array(
                    'class' => 'Icinga\Test\Authentication\ErrorProneBackendMock'
                ),
                'provider2' => array(
                    'class' => 'Icinga\Test\Authentication\ErrorProneBackendMock'
                )
            ),
            true
        );

        ErrorProneBackendMock::$throwOnCreate = false;

        $authManager = $this->getManagerInstance($session, false, true, $managerConfig);

        $this->assertInstanceOf(
            'Icinga\Test\Authentication\ErrorProneBackendMock',
            $authManager->getUserBackend('provider1')
        );

        $this->assertInstanceOf(
            'Icinga\Test\Authentication\ErrorProneBackendMock',
            $authManager->getUserBackend('provider2')
        );

        $authManager->authenticate(
            new Credential('jdoe', 'passjdoe')
        );
    }

    public function testAuthenticationChainWithGoodProviders()
    {
        $this->markTestSkipped('ErrorProneBackendMock, SessionMock and BackendMock are faulty');
        $managerConfig = new Zend_Config(
            array(
                'provider1' => array(
                    'name'  => 'provider1',
                    'class' => 'Icinga\Test\Authentication\BackendMock'
                ),
                'provider2' => array(
                    'name'  => 'provider2',
                    'class' => 'Icinga\Test\Authentication\BackendMock'
                )
            ),
            true
        );

        $authManager = $this->getManagerInstance($session, true, true, $managerConfig);

        $authManager->getUserBackend('provider1')->setCredentials(
            array(
                new Credential('p1-user1', 'p1-passwd1'),
                new Credential('p1-user2', 'p1-passwd2')
            )
        );

        $authManager->getUserBackend('provider2')->setCredentials(
            array(
                new Credential('p2-user1', 'p2-passwd1'),
                new Credential('p2-user2', 'p2-passwd2')
            )
        );

        $this->assertTrue(
            $authManager->authenticate(new Credential('p2-user2', 'p2-passwd2'))
        );
    }

    public function testAuthenticationChainWithBadProviders()
    {
        $this->markTestSkipped('ErrorProneBackendMock, SessionMock and BackendMock are faulty');
        $managerConfig = new Zend_Config(
            array(
                'provider1' => array(
                    'class' => 'Icinga\Test\Authentication\ErrorProneBackendMock'
                ),
                'provider2' => array(
                    'class' => 'Icinga\Test\Authentication\ErrorProneBackendMock'
                ),
                'provider3' => array(
                    'class' => 'Icinga\Test\Authentication\ErrorProneBackendMock'
                ),
                'provider4' => array(
                    'class' => 'Icinga\Test\Authentication\BackendMock'
                )
            ),
            true
        );

        $authManager = $this->getManagerInstance($session, false, true, $managerConfig);

        $this->assertInstanceOf(
            'Icinga\Test\Authentication\ErrorProneBackendMock',
            $authManager->getUserBackend('provider1')
        );

        $this->assertInstanceOf(
            'Icinga\Test\Authentication\BackendMock',
            $authManager->getUserBackend('provider4')
        );

        $authManager->getUserBackend('provider4')->setCredentials(
            array(
                new Credential('p4-user1', 'p4-passwd1'),
                new Credential('p4-user2', 'p4-passwd2')
            )
        );

        $session->isOpen = true;

        $this->assertTrue(
            $authManager->authenticate(new Credential('p4-user2', 'p4-passwd2'))
        );

        $session->isOpen = true;

        $this->assertTrue(
            $authManager->authenticate(new Credential('p4-user1', 'p4-passwd1'))
        );

        $session->isOpen = true;

        $this->assertFalse(
            $authManager->authenticate(new Credential('p4-user2', 'p4-passwd1-WRONG123123'))
        );
    }

    public function testErrorConditionsInConfiguration()
    {
        $this->markTestSkipped('ErrorProneBackendMock, SessionMock and BackendMock are faulty');
        $managerConfig = new Zend_Config(
            array(
                'provider1' => array(
                    'backend' => 'db'
                ),
                'provider2' => array(
                    'target' => 'user'
                ),
                'provider3' => array(
                    'class' => 'Uhh\Ahh\WeDoNotCare123'
                )
            ),
            true
        );

        $authManager = $this->getManagerInstance($session, true, true, $managerConfig);

        $this->assertNull($authManager->getUserBackend('provider1'));
        $this->assertNull($authManager->getUserBackend('provider2'));
        $this->assertNull($authManager->getUserBackend('provider3'));
    }
}
