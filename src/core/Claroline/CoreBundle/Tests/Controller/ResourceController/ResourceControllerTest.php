<?php

namespace Claroline\CoreBundle\Controller\ResourceController;

use Claroline\CoreBundle\Library\Testing\FunctionalTestCase;
use Claroline\CoreBundle\Entity\Resource\Directory;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Tests\DataFixtures\LoadFileData;

class ResourceControllerTest extends FunctionalTestCase
{
    private $resourceRepository;
    private $upDir;
    private $pwr;

    public function setUp()
    {
        parent::setUp();
        $this->loadUserFixture(array('user', 'admin'));
        $this->client->followRedirects();
        $ds = DIRECTORY_SEPARATOR;
        $this->originalPath = __DIR__ . "{$ds}..{$ds}..{$ds}Stub{$ds}files{$ds}originalFile.txt";
        $this->copyPath = __DIR__ . "{$ds}..{$ds}..{$ds}Stub{$ds}files{$ds}copy.txt";
        $this->upDir = $this->client->getContainer()->getParameter('claroline.files.directory');
        $this->thumbsDir = $this->client->getContainer()->getParameter('claroline.thumbnails.directory');
        $this->resourceRepository = $this
            ->client
            ->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('Claroline\CoreBundle\Entity\Resource\AbstractResource');
        $this->pwr = $this->resourceRepository
            ->getRootForWorkspace($this->getFixtureReference('user/user')->getPersonalWorkspace());
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->cleanDirectory($this->upDir);
        $this->cleanDirectory($this->thumbsDir);
    }

    public function testDirectoryCreationFormCanBeDisplayed()
    {
        $this->logUser($this->getFixtureReference('user/user'));
        $crawler = $this->client->request('GET', 'resource/form/directory');
        $form = $crawler->filter('#directory_form');
        $this->assertEquals(count($form), 1);
    }

    public function testDirectoryFormErrorsAreDisplayed()
    {
        $this->logUser($this->getFixtureReference('user/user'));
        $crawler = $this->client->request(
            'POST',
            "/resource/create/directory/{$this->pwr->getId()}",
            array('directory_form' => array('name' => null, 'shareType' => 1))
        );

        $form = $crawler->filter('#directory_form');
        $this->assertEquals(count($form), 1);
    }

    public function testMove()
    {
        $this->logUser($this->getFixtureReference('user/user'));
        $user = $this->client->getContainer()->get('security.context')->getToken()->getUser();
        $theBigTree = $this->createBigTree($this->pwr, $user);
        $theLoneFile = $this->uploadFile($this->pwr, 'theLoneFile.txt', $user);
        $theContainer = $this->createFolder($this->pwr, 'container', $user);
        $this->client->request(
            'GET',
            "/resource/move/{$theContainer->getId()}?ids[]={$theBigTree[0]->getId()}&ids[]={$theLoneFile->getId()}"
        );
        $this->client->request('GET', "/resource/directory/{$theContainer->getId()}");
        $dir = json_decode($this->client->getResponse()->getContent());
        $this->assertObjectHasAttribute('resources', $dir);
        $this->assertEquals(2, count($dir->resources));
    }

    public function testCopy()
    {
        $this->logUser($this->getFixtureReference('user/user'));
        $user = $this->client->getContainer()->get('security.context')->getToken()->getUser();
        $theBigTree = $this->createBigTree($this->pwr, $user);
        $theLoneFile = $this->uploadFile($this->pwr, 'theLoneFile.txt', $user);
        $this->client->request(
            'GET',
            "/resource/copy/{$this->pwr->getId()}?ids[]={$theBigTree[0]->getId()}&ids[]={$theLoneFile->getId()}"
        );
        $this->client->request('GET', "/resource/directory/{$this->pwr->getId()}");
        $dir = json_decode($this->client->getResponse()->getContent());
        $this->assertObjectHasAttribute('resources', $dir);
        $this->assertEquals(4, count($dir->resources));
    }

    public function testGetEveryInstancesIdsFromExportArray()
    {
        $this->logUser($this->getFixtureReference('user/user'));
        $user = $this->client->getContainer()->get('security.context')->getToken()->getUser();
        $theBigTree = $this->createBigTree($this->pwr, $user);
        $toExport = $this->client
            ->getContainer()
            ->get('claroline.resource.exporter')
            ->expandResourceIds((array) $this->pwr->getId());
        $this->assertEquals(4, count($toExport));
        $theLoneFile = $this->uploadFile($this->pwr, 'theLoneFile.txt', $user);
        $toExport = $this->client
            ->getContainer()
            ->get('claroline.resource.exporter')
            ->expandResourceIds((array) $theLoneFile->getId());
        $this->assertEquals(1, count($toExport));
        $complexExportList = array();
        $complexExportList[] = $theBigTree[0]->getId();
        $complexExportList[] = $theLoneFile->getId();
        $toExport = $this->client
            ->getContainer()
            ->get('claroline.resource.exporter')
            ->expandResourceIds($complexExportList);
        $this->assertEquals(5, count($toExport));
    }

    public function testExport()
    {
        $this->markTestSkipped("streamedResponse broke this one");
        $this->logUser($this->getFixtureReference('user/user'));
        $this->client->request('GET', "/resource/export?0={$this->pwr->getId()}");
        $headers = $this->client->getResponse()->headers;
        $this->assertTrue($headers->contains('Content-Disposition', 'attachment; filename=archive'));
        //with a full dir
        $theBigTree = $this->createBigTree($this->pwr->getId());
        $theLoneFile = $this->uploadFile($this->pwr->getId(), 'theLoneFile.txt');
        $this->client->request('GET', "/resource/multiexport?0={$theBigTree[0]->id}&1={$theLoneFile->id}");
        $headers = $this->client->getResponse()->headers;
        $this->assertTrue($headers->contains('Content-Disposition', 'attachment; filename=archive'));
        $filename = $this->client
            ->getContainer()
            ->getParameter('claroline.files.directory') . DIRECTORY_SEPARATOR . "testMultiExportClassic.zip";
        ob_start(null);
        $this->client->getResponse()->send();
        $content = ob_get_contents();
        ob_clean();
        file_put_contents($filename, $content);
        // Check the archive content
        $zip = new \ZipArchive();
        $zip->open($filename);
        $neededFiles = array(
            "wsA - Workspace_A/rootDir/",
            "wsA - Workspace_A/theLoneFile.txt",
            "wsA - Workspace_A/rootDir/secondfile",
            "wsA - Workspace_A/rootDir/firstfile",
            "wsA - Workspace_A/rootDir/childDir/thirdFile");
        $foundFiles = array();
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            array_push($foundFiles, $stat['name']);
        }
        $this->assertEquals(0, count(array_diff($neededFiles, $foundFiles)));
    }

    public function testMultiExportThrowsAnExceptionWithoutParameters()
    {
        $this->logUser($this->getFixtureReference('user/user'));
        $crawler = $this->client->request('GET', "/resource/export");
        $this->assertEquals(500, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            1,
            count($crawler->filter('html:contains("You must select some resources to export.")'))
        );
    }

    public function testCustomActionThrowExceptionOnUknownAction()
    {
        $this->logUser($this->getFixtureReference('user/user'));
        $crawler = $this->client->request(
            'GET',
            "resource/custom/directory/thisactiondoesntexist/{$this->pwr->getId()}"
        );
        $this->assertEquals(500, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(1, count($crawler->filter('html:contains("return any Response")')));
    }

    /**
     * @todo Unskip this test, taking changes to the filter action into account
     * @todo Test the exception if the directory id parameter doesn't match any directory
     */
    public function testFilters()
    {
        $this->markTestSkipped();
        $this->logUser($this->getFixtureReference('user/user'));
        $user = $this->client->getContainer()->get('security.context')->getToken()->getUser();
        $this->createBigTree($this->pwr, $user);
        $this->logUser($this->getFixtureReference('user/admin'));
        $creationTimeAdminTreeOne = new \DateTime();
        $adminpwr = $this->resourceRepository
            ->getRootForWorkspace($this->getFixtureReference('user/admin')->getPersonalWorkspace());
        $this->createBigTree($adminpwr->getId());
        //sleep(2); // Pause to allow us to filter on creation date
        //$creationTimeAdminTreeTwo = new \DateTime();
        //$wsEroot = $this->resourceRepository->getRootForWorkspace($this->getFixtureReference('workspace/ws_e'));
        //$this->createBigTree($wsEroot->getId());
        //$now = new \DateTime();
        //filter by types (1)
        $crawler = $this->client->request('GET', '/resource/filter?types[]=file');
        $this->assertEquals(3, count(json_decode($this->client->getResponse()->getContent(), true)));

        //filter by types (2)
        $crawler = $this->client->request('GET', '/resource/filter?types[]=file&types[]=text');
        $this->assertEquals(3, count(json_decode($this->client->getResponse()->getContent(), true)));

        //filter by root (1)
        $crawler = $this->client->request('GET', "/resource/filter?roots[]={$adminpwr->getPath()}");
        $this->assertEquals(5, count(json_decode($this->client->getResponse()->getContent(), true)));

        //filter by datecreation
        $crawler = $this->client->request(
            'GET',
            "/resource/filter?dateFrom={$creationTimeAdminTreeOne->format('Y-m-d H:i:s')}"
        );
        $this->assertEquals(5, count(json_decode($this->client->getResponse()->getContent(), true)));

        //$crawler = $this->client->request('GET', "/resource/filter?dateTo={$now->format('Y-m-d H:i:s')}");
        //$this->assertEquals(6, count(json_decode($this->client->getResponse()->getContent(), true)));

        //$crawler = $this->client->request(
        //  'GET',
        //  "/resource/filter?dateFrom={$creationTimeAdminTreeTwo->format('Y-m-d H:i:s')}
        //  &dateTo={$now->format('Y-m-d H:i:s')}
        //");
        //$this->assertEquals(5, count(json_decode($this->client->getResponse()->getContent(), true)));

        //filter by name
        $crawler = $this->client->request('GET', "/resource/filter?name=firstFile");
        $this->assertEquals(1, count(json_decode($this->client->getResponse()->getContent())));

        //filter by mime
        /* This filter is not active for now (see ResourceController::filterAction's todo)
        $crawler = $this->client->request('GET', "/resource/filter?mimeTypes[]=text");
        $this->assertEquals(6, count(json_decode($this->client->getResponse()->getContent())));
        */
    }

    public function testDelete()
    {
        $this->logUser($this->getFixtureReference('user/user'));
        $user = $this->client->getContainer()->get('security.context')->getToken()->getUser();
        $theBigTree = $this->createBigTree($this->pwr, $user);
        $theLoneFile = $this->uploadFile($this->pwr, 'theLoneFile.txt', $user);
        $crawler = $this->client->request('GET', "/resource/directory/{$this->pwr->getId()}");
        $dir = json_decode($this->client->getResponse()->getContent());
        $this->assertObjectHasAttribute('resources', $dir);
        $this->assertEquals(2, count($dir->resources));
        $this->client->request(
            'GET', "/resource/delete?ids[]={$theBigTree[0]->getId()}&ids[]={$theLoneFile->getId()}"
        );
        $crawler = $this->client->request('GET', "/resource/directory/{$this->pwr->getId()}");
        $dir = json_decode($this->client->getResponse()->getContent());
        $this->assertObjectHasAttribute('resources', $dir);
        $this->assertEquals(0, count($dir->resources));
    }

    public function testDeleteRootThrowsAnException()
    {
        $this->logUser($this->getFixtureReference('user/user'));
        $crawler = $this->client->request('GET', "/resource/delete?ids[]={$this->pwr->getId()}");
        $this->assertEquals(500, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(1, count($crawler->filter('html:contains("Root directory cannot be removed")')));
    }

    public function testDeleteUserRemovesHisPersonnalDataTree()
    {
        $this->markTestSkipped("Can't make it work.");
        $this->logUser($this->getFixtureReference('user/user'));
        $theBigTree = $this->createBigTree($this->pwr->getId());
        $this->logUser($this->getFixtureReference('user/admin'));
        $this->client->request('GET', "admin/user/delete/{$this->getFixtureReference('user/user')->getId()}");
        $em = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $userRoot = $em->getRepository('ClarolineCoreBundle:Resource\ResourceInstance')
            ->find($this->pwr->getId());
        $this->assertEquals($userRoot, null);
        $tbg = $em->getRepository('ClarolineCoreBundle:Resource\ResourceInstance')
            ->find($theBigTree[0]->getId());
        $this->assertEquals($tbg, null);
    }

    public function testCustomActionLogsEvent()
    {
        $this->markTestSkipped('not custom action defined yet');
        $this->logUser($this->getFixtureReference('user/user'));
        $user = $this->client->getContainer()->get('security.context')->getToken()->getUser();
        $file = $this->uploadFile($this->pwr, 'txt.txt', $user);
        $preEvents = $this->client
            ->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('ClarolineCoreBundle:Logger\ResourceLog')
            ->findAll();
        $this->client->request('GET', "/resource/custom/file/open/{$file->getId()}");
        $postEvents = $this->client
            ->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('ClarolineCoreBundle:Logger\ResourceLog')
            ->findAll();
        $this->assertEquals(1, count($postEvents) - count($preEvents));
    }

    public function testOpenActionLogsEvent()
    {
        $this->logUser($this->getFixtureReference('user/user'));
        $user = $this->client->getContainer()->get('security.context')->getToken()->getUser();
        $file = $this->uploadFile($this->pwr, 'txt.txt', $user);
        $preEvents = $this->client
            ->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('ClarolineCoreBundle:Logger\ResourceLog')
            ->findAll();
        $this->client->request('GET', "/resource/open/file/{$file->getId()}");
        $postEvents = $this->client
            ->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('ClarolineCoreBundle:Logger\ResourceLog')
            ->findAll();
        $this->assertEquals(1, count($postEvents) - count($preEvents));
    }

    public function testCreateActionLogsEventWithResourceManager()
    {
        $this->logUser($this->getFixtureReference('user/user'));
        $user = $this->client->getContainer()->get('security.context')->getToken()->getUser();
        $preEvents = $this->client
            ->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('ClarolineCoreBundle:Logger\ResourceLog')
            ->findAll();
        $this->uploadFile($this->pwr, 'txt.txt', $user);
        $postEvents = $this->client
            ->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('ClarolineCoreBundle:Logger\ResourceLog')
            ->findAll();
        $this->assertEquals(1, count($postEvents) - count($preEvents));
    }

    public function testMultiDeleteActionLogsEvent()
    {
        $this->markTestSkipped("Doesn't work during the test (onLogResource method not fired during the delete)");
        $this->logUser($this->getFixtureReference('user/user'));
        $theBigTree = $this->createBigTree($this->pwr->getId());
        $theLoneFile = $this->uploadFile($this->pwr->getId(), 'theLoneFile.txt');
        $this->client->request('GET', "/resource/directory/{$this->pwr->getId()}");
        $dir = json_decode($this->client->getResponse()->getContent());
        $this->assertObjectHasAttribute('resources', $dir);
        $this->assertEquals(2, count($dir->resources));
        $preEvents = $this->client
            ->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('ClarolineCoreBundle:Logger\ResourceLog')
            ->findAll();
        $this->client->request(
            'GET', "/resource/delete?0={$theBigTree[0]->id}&1={$theLoneFile->id}"
        );
        $postEvents = $this->client
            ->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('ClarolineCoreBundle:Logger\ResourceLog')
            ->findAll();
        $this->assertEquals(2, count($postEvents) - count($preEvents));
    }

    public function testMultiMoveLogsEvent()
    {
        $this->logUser($this->getFixtureReference('user/user'));
        $user = $this->client->getContainer()->get('security.context')->getToken()->getUser();
        $theBigTree = $this->createBigTree($this->pwr, $user);
        $theLoneFile = $this->uploadFile($this->pwr, 'theLoneFile.txt', $user);
        $theContainer = $this->createFolder($this->pwr, 'container', $user);
        $preEvents = $this->client
            ->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('ClarolineCoreBundle:Logger\ResourceLog')
            ->findAll();
        $this->client->request(
            'GET',
            "/resource/move/{$theContainer->getId()}?ids[]={$theBigTree[0]->getId()}&ids[]={$theLoneFile->getId()}"
        );
        $postEvents = $this->client
            ->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('ClarolineCoreBundle:Logger\ResourceLog')
            ->findAll();
        $this->assertEquals(2, count($postEvents) - count($preEvents));
    }

    public function testMultiExportLogsEvent()
    {
        $this->logUser($this->getFixtureReference('user/user'));
        $user = $this->client->getContainer()->get('security.context')->getToken()->getUser();
        $theBigTree = $this->createBigTree($this->pwr, $user);
        $theLoneFile = $this->uploadFile($this->pwr, 'theLoneFile.txt', $user);
        $preEvents = $this->client
            ->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('ClarolineCoreBundle:Logger\ResourceLog')
            ->findAll();
        ob_start();
        $this->client->request(
            'GET',
            "/resource/export?ids[]={$theBigTree[0]->getId()}&ids[]={$theLoneFile->getId()}"
        );
        ob_clean();
        $postEvents = $this->client
            ->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository('ClarolineCoreBundle:Logger\ResourceLog')
            ->findAll();
        $this->assertEquals(5, count($postEvents) - count($preEvents));
    }

    public function testCreateShortcutAction()
    {
        $this->logUser($this->getFixtureReference('user/user'));
        $user = $this->client->getContainer()->get('security.context')->getToken()->getUser();
        $file = $this->uploadFile($this->pwr, 'file', $user);
        $this->client->request('GET', "/resource/shortcut/{$this->pwr->getId()}/create?ids[]={$file->getId()}");
        $this->client->request('GET', "/resource/directory/{$this->pwr->getId()}");
        $dir = json_decode($this->client->getResponse()->getContent());
        $this->assertObjectHasAttribute('resources', $dir);
        $this->assertEquals(2, count($dir->resources));
    }

    public function testOpenFileShortcut()
    {
        $this->logUser($this->getFixtureReference('user/user'));
        $user = $this->client->getContainer()->get('security.context')->getToken()->getUser();
        $file = $this->uploadFile($this->pwr, 'file', $user);
        $this->client->request('GET', "/resource/shortcut/{$this->pwr->getId()}/create?ids[]={$file->getId()}");
        $jsonResponse = json_decode($this->client->getResponse()->getContent());
        $this->client->request('GET', "/resource/open/file/{$file->getId()}");
        $openFile = $this->client->getResponse()->getContent();
        $this->client->request('GET', "/resource/open/file/{$jsonResponse[0]->id}");
        $openShortcut = $this->client->getResponse()->getContent();
        $this->assertEquals($openFile, $openShortcut);
    }

    public function testChildrenShortcut()
    {
        $this->logUser($this->getFixtureReference('user/user'));
        $user = $this->client->getContainer()->get('security.context')->getToken()->getUser();
        $roots = $this->createTree($this->pwr, $user);
        $this->client->request('GET', "/resource/shortcut/{$this->pwr->getId()}/create?ids[]={$roots[0]->getId()}");
        $jsonResponse = json_decode($this->client->getResponse()->getContent());
        $this->client->request('GET', "/resource/directory/{$jsonResponse[0]->id}");
        $openShortcut = $this->client->getResponse()->getContent();
        $this->client->request('GET', "/resource/directory/{$roots[0]->getId()}");
        $openDirectory = $this->client->getResponse()->getContent();
        $this->assertEquals($openDirectory, $openShortcut);
    }

    public function testDeleteShortcut()
    {
        $this->logUser($this->getFixtureReference('user/user'));
        $user = $this->client->getContainer()->get('security.context')->getToken()->getUser();
        $file = $this->uploadFile($this->pwr, 'file', $user);
        $this->client->request('GET', "/resource/shortcut/{$this->pwr->getId()}/create?ids[]={$file->getId()}");
        $jsonResponse = json_decode($this->client->getResponse()->getContent());
        $this->client->request('GET', "/resource/delete?ids[]={$jsonResponse[0]->id}");
        $this->client->request('GET', "/resource/directory/{$this->pwr->getId()}");
        $dir = json_decode($this->client->getResponse()->getContent());
        $this->assertObjectHasAttribute('resources', $dir);
        $this->assertEquals(1, count($dir->resources));
    }

    public function testDeleteShortcutTarget()
    {
        $this->logUser($this->getFixtureReference('user/user'));
        $user = $this->client->getContainer()->get('security.context')->getToken()->getUser();
        $file = $this->uploadFile($this->pwr, 'file', $user);
        $this->client->request('GET', "/resource/shortcut/{$this->pwr->getId()}/create?ids[]={$file->getId()}");
        $this->client->request('GET', "/resource/delete?ids[]={$file->getId()}");
        $this->client->request('GET', "/resource/directory/{$this->pwr->getId()}");
        $dir = json_decode($this->client->getResponse()->getContent());
        $this->assertObjectHasAttribute('resources', $dir);
        $this->assertEquals(0, count($dir->resources));
    }

    public function testOpenDirectoryAction()
    {
        $user = $this->getFixtureReference('user/user');
        $rootDir = $this->getEntityManager()
            ->getRepository('Claroline\CoreBundle\Entity\Resource\AbstractResource')
            ->getRootForWorkspace($user->getPersonalWorkspace());
        $fooDir = $this->createFolder($rootDir, 'Foo', $user);
        $barDir = $this->createFolder($fooDir, 'Bar', $user);
        $this->uploadFile($barDir, 'Baz', $user);
        $this->uploadFile($barDir, 'Bat', $user);
        $allVisibleResourceTypes = $this->getEntityManager()
            ->getRepository('Claroline\CoreBundle\Entity\Resource\ResourceType')
            ->findByIsVisible(true);

        $this->logUser($this->getFixtureReference('user/user'));
        $this->client->request('GET', "/resource/directory/{$barDir->getId()}");
        $jsonResponse = json_decode($this->client->getResponse()->getContent());
        $this->assertObjectHasAttribute('path', $jsonResponse);
        $this->assertObjectHasAttribute('creatableTypes', $jsonResponse);
        $this->assertObjectHasAttribute('resources', $jsonResponse);
        $this->assertEquals(3, count($jsonResponse->path));
        $this->assertEquals(count($allVisibleResourceTypes), count((array) $jsonResponse->creatableTypes));
        $this->assertEquals(2, count((array) $jsonResponse->resources));
    }

    public function testOpenDirectoryReturnsTheRootDirectoriesIfDirectoryIdIsZero()
    {
        $this->logUser($this->getFixtureReference('user/user'));
        $this->client->request('GET', "/resource/directory/0");
        $jsonResponse = json_decode($this->client->getResponse()->getContent());
        $this->assertObjectHasAttribute('path', $jsonResponse);
        $this->assertObjectHasAttribute('creatableTypes', $jsonResponse);
        $this->assertObjectHasAttribute('resources', $jsonResponse);
        $this->assertEquals(0, count($jsonResponse->path));
        $this->assertEquals(0, count((array) $jsonResponse->creatableTypes));
        $this->assertEquals(1, count((array) $jsonResponse->resources));
    }

    public function testOpenDirectoryThrowsAnExceptionIfDirectoryDoesntExist()
    {
        $this->logUser($this->getFixtureReference('user/user'));
        $this->client->request('GET', "/resource/directory/123456");
        $this->assertEquals(500, $this->client->getResponse()->getStatusCode());
    }

    public function testOpenDirectoryThrowsAnExceptionIfResourceIsNotADirectory()
    {
        $user = $this->getFixtureReference('user/user');
        $rootDir = $this->getEntityManager()
            ->getRepository('Claroline\CoreBundle\Entity\Resource\AbstractResource')
            ->getRootForWorkspace($user->getPersonalWorkspace());
        $file = $this->uploadFile($rootDir, 'Baz', $user);
        $this->logUser($this->getFixtureReference('user/user'));
        $this->client->request('GET', "/resource/directory/{$file->getId()}");
        $this->assertEquals(500, $this->client->getResponse()->getStatusCode());
    }


    private function createFolder($parent, $name, User $user)
    {
        $directory = new Directory();
        $directory->setName($name);
        $manager = $this->client->getContainer()->get('claroline.resource.manager');
        $manager->create($directory, $parent->getId(), 'directory', $user);

        return $directory;
    }

    private function uploadFile($parent, $name, User $user)
    {
        $fileData = new LoadFileData($name, $parent, $user, tempnam(sys_get_temp_dir(), 'FormTest'));
        $this->loadFixture($fileData);

        return $fileData->getLastFileCreated();
    }

    //DIR
    //private child
    //public child
    private function createTree($parent, User $user)
    {
        $arrCreated = array();
        $arrCreated[] = $rootDir = $this->createFolder($parent, 'rootDir', $user);
        $arrCreated[] = $this->uploadFile($rootDir, 'firstfile', $user);
        $arrCreated[] = $this->uploadFile($rootDir, 'secondfile', $user);

        return $arrCreated;
    }

    //DIR
    //private child
    //public child
    //private dir
    //private child
    private function createBigTree($parent, User $user)
    {
        $arrCreated = array();
        $arrCreated[] = $rootDir = $this->createFolder($parent, 'rootDir', $user);
        $arrCreated[] = $this->uploadFile($rootDir, 'firstfile', $user);
        $arrCreated[] = $this->uploadFile($rootDir, 'secondfile', $user);
        $arrCreated[] = $childDir = $this->createFolder($rootDir, 'childDir', $user);
        $arrCreated[] = $this->uploadFile($childDir, 'thirdFile', $user);

        return $arrCreated;
    }

    private function cleanDirectory($dir)
    {
        $iterator = new \DirectoryIterator($dir);

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() !== 'placeholder'
                && $file->getFilename() !== 'originalFile.txt'
                && $file->getFilename() !== 'originalZip.zip'
            ) {
                chmod($file->getPathname(), 0777);
                unlink($file->getPathname());
            }
        }
    }
}