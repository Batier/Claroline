<?php

namespace Claroline\CommonBundle\Service\ORM\DynamicInheritance\Listener;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\Events;
use Claroline\CommonBundle\Tests\Stub\Entity\ValidHierarchy\Ancestor;
use Claroline\CommonBundle\Tests\Stub\Entity\ValidHierarchy\FirstChild;
use Claroline\CommonBundle\Tests\Stub\Entity\ValidHierarchy\SecondChild;
use Claroline\CommonBundle\Tests\Stub\Entity\ValidHierarchy\FirstDescendant;
use Claroline\CommonBundle\Tests\Stub\Entity\ValidHierarchy\SecondDescendant;
use Claroline\CommonBundle\Tests\Stub\Entity\NodeHierarchy\TreeAncestor;
use Claroline\CommonBundle\Tests\Stub\Entity\NodeHierarchy\FirstChild as TreeFirstChild;
use Claroline\CommonBundle\Tests\Stub\Entity\NodeHierarchy\SecondChild as TreeSecondChild;

class ExtendableListenerTest extends WebTestCase
{
    /** @var \Claroline\CommonBundle\Service\Testing\TransactionalTestClient */
    private $client;

    /** @var \Doctrine\ORM\EntityManager */
    private $em;

    public function setUp()
    {
        $this->client = self::createClient();
        $this->client->beginTransaction();
        $this->em = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $this->client->beginTransaction();
    }
    
    public function tearDown()
    {
        $this->client->rollback();
    }
    
    public function testExtendableListenerIsSubscribed()
    {
        $listeners = $this->em->getEventManager()->getListeners(Events::loadClassMetadata);
        
        foreach ($listeners as $listener)
        {
            if ($listener instanceof ExtendableListener)
            {
                return;
            }
        }
        
        $this->fail('The ExtendableListener is not attached to the default EntityManager.');
    }

    /**
     * @dataProvider conflictualMappingEntityProvider
     */
    public function testLoadingAnEntityWithBothExtendableAndDoctrineInheritanceAnnotationsThrowsAnException($entityFqcn)
    {
        // Corresponding (invalid) entities are also disabled
        $this->markTestSkipped();
        
        $this->setExpectedException('Claroline\CommonBundle\Exception\ClarolineException');
        
        $entity = new $entityFqcn();
        $this->em->persist($entity);
    }
    
    /**
     * @dataProvider invalidDiscriminatorColumnNameEntityProvider
     */
    public function testLoadingAnExtendableEntityWithInvalidDiscriminatorColumnNameThrowsAnException($entityFqcn)
    {
        // Corresponding (invalid) entities are also disabled
        $this->markTestSkipped();
        
        $this->setExpectedException('Claroline\CommonBundle\Exception\ClarolineException');
        
        $entity = new $entityFqcn();
        $this->em->persist($entity);
    }
    
    public function testEntityManagerCanLoadEntitiesWithValidExtendableAnnotations()
    {
        $ancestor = new Ancestor();
        $firstChild = new FirstChild();
        $secondChild = new SecondChild();
        $firstDescendant = new FirstDescendant();
        $secondDescendant = new SecondDescendant();
        
        $this->em->persist($ancestor);
        $this->em->persist($firstChild);
        $this->em->persist($secondChild);
        $this->em->persist($firstDescendant);
        $this->em->persist($secondDescendant);
    }
    
    public function testEntityManagerCanPersistAndFindEntitiesThatAreChildrenOfExtendableEntities()
    {
        $firstDescendant = new FirstDescendant();
        $secondDescendant = new SecondDescendant();
        
        $firstDescendant->setAncestorField('FirstDescendant Ancestor field');
        $firstDescendant->setFirstChildField('FirstDescendant FirstChild field');
        $firstDescendant->setFirstDescendantField('FirstDescendant own field');
        $secondDescendant->setAncestorField('SecondDescendant Ancestor field');
        $secondDescendant->setFirstChildField('SecondDescendant FirstChild field');
        $secondDescendant->setSecondDescendantField('SecondDescendant own field');
        
        $this->em->persist($firstDescendant);
        $this->em->persist($secondDescendant);
        $this->em->flush();
        
        $firstLoaded = $this->em->createQueryBuilder()
            ->select('f')
            ->from('Claroline\CommonBundle\Tests\Stub\Entity\ValidHierarchy\FirstDescendant', 'f')
            ->getQuery()
            ->getSingleResult();
        
        $secondLoaded = $this->em->createQueryBuilder()
            ->select('s')
            ->from('Claroline\CommonBundle\Tests\Stub\Entity\ValidHierarchy\SecondDescendant', 's')
            ->getQuery()
            ->getSingleResult();
        
        $this->assertEquals('FirstDescendant Ancestor field', $firstLoaded->getAncestorField());
        $this->assertEquals('SecondDescendant Ancestor field', $secondLoaded->getAncestorField());
        $this->assertNotEquals($firstLoaded->getId(), $secondLoaded->getId());        
    }
    
    public function testRetrievingParentsBringsBackParentsAndChildren()
    {
        $this->flushTheWholeValidHierarchy();
        
        $ancestors = $this->em
            ->getRepository('Claroline\CommonBundle\Tests\Stub\Entity\ValidHierarchy\Ancestor')
            ->findAll();
        
        $firstChildren = $this->em
            ->getRepository('Claroline\CommonBundle\Tests\Stub\Entity\ValidHierarchy\FirstChild')
            ->findAll();
        
        $secondChildren = $this->em
            ->getRepository('Claroline\CommonBundle\Tests\Stub\Entity\ValidHierarchy\SecondChild')
            ->findAll();
        
        $this->assertEquals(5, count($ancestors));
        $this->assertEquals(3, count($firstChildren));
        $this->assertEquals(1, count($secondChildren));
    }
    
    public function testRetrievingParentsWithExactClassTypeDoesntBringBackTheirChildren()
    {
        $this->flushTheWholeValidHierarchy();
        
        $ancestors = $this->requestExactType('ValidHierarchy\Ancestor');
        $firstChildren = $this->requestExactType('ValidHierarchy\FirstChild');
        $secondChildren = $this->requestExactType('ValidHierarchy\SecondChild');
        $firstDescendants = $this->requestExactType('ValidHierarchy\FirstDescendant');
        $secondDescendants = $this->requestExactType('ValidHierarchy\SecondDescendant');
        
        $this->assertEquals(1, count($ancestors));
        $this->assertEquals(1, count($firstChildren));
        $this->assertEquals(1, count($secondChildren));
        $this->assertEquals(1, count($firstDescendants));
        $this->assertEquals(1, count($secondDescendants));
        
        $this->assertInstanceOf('Claroline\CommonBundle\Tests\Stub\Entity\ValidHierarchy\Ancestor', $ancestors[0]);
        $this->assertInstanceOf('Claroline\CommonBundle\Tests\Stub\Entity\ValidHierarchy\FirstChild', $firstChildren[0]);
        $this->assertInstanceOf('Claroline\CommonBundle\Tests\Stub\Entity\ValidHierarchy\SecondChild', $secondChildren[0]);
        $this->assertInstanceOf('Claroline\CommonBundle\Tests\Stub\Entity\ValidHierarchy\FirstDescendant', $firstDescendants[0]);
        $this->assertInstanceOf('Claroline\CommonBundle\Tests\Stub\Entity\ValidHierarchy\SecondDescendant', $secondDescendants[0]);
    }
    
    public function testExtendableMappingDoesntConflictWithDoctrineTreeExtension()
    {
        $firstTreeAncestor = new TreeAncestor(); // will be a root node (no parent)
        $secondTreeAncestor = new TreeAncestor();
        $firstFirstChild = new TreeFirstChild();
        $secondFirstChild = new TreeFirstChild(); // will be a root node (no parent)
        $secondChild = new TreeSecondChild();
        
        $firstTreeAncestor->setTreeAncestorField('FTA');
        $secondTreeAncestor->setTreeAncestorField('STA');
        $secondTreeAncestor->setParent($firstTreeAncestor); // child of firstTreeAncestor
        $firstFirstChild->setTreeAncestorField('FFCA');
        $firstFirstChild->setFirstChildField('FFC');
        $firstFirstChild->setParent($firstTreeAncestor); // child of firstTreeAncestor
        $secondFirstChild->setTreeAncestorField('SFCA');
        $secondFirstChild->setFirstChildField('SFC');
        $secondChild->setTreeAncestorField('SCA');
        $secondChild->setSecondChildField('SC');
        $secondChild->setParent($secondTreeAncestor); // child of second-firstTreeAncestor
        
        $this->em->persist($firstTreeAncestor);
        $this->em->persist($secondTreeAncestor);
        $this->em->persist($firstFirstChild);
        $this->em->persist($secondFirstChild);
        $this->em->persist($secondChild);       
        $this->em->flush();
        
        $ancestorRepo = $this->em->getRepository('Claroline\CommonBundle\Tests\Stub\Entity\NodeHierarchy\TreeAncestor');
        
        $ancestors = $ancestorRepo->findAll();        
        $rootAncestor = $ancestorRepo->findOneByTreeAncestorField('FTA');
        $rootAncestorChildNodes = $ancestorRepo->children($rootAncestor);
        $rootFirstChild = $ancestorRepo->findOneByTreeAncestorField('SFCA');
        $rootFirstChildChildNodes = $ancestorRepo->children($rootFirstChild);
        
        $this->assertEquals(5, count($ancestors));
        $this->assertEquals(3, count($rootAncestorChildNodes));
        $this->assertEquals(0, count($rootFirstChildChildNodes));
               
        // The gedmo-doctrine tree extension alters transactional mode by creating 
        // temporary tables for its calculations. The code below "manually" deletes 
        // the records that were inserted in this test.
        
        // The following two lines explicitly close the opened transaction (which must be done
        // for the entity manager connection AND for the client connection, don't ask me why : 
        // it seems there's a nested transaction involved here...).
        $this->em->getConnection()->rollback();
        $this->client->rollback();
        
        foreach ($ancestors as $ancestor)
        {
            $this->em->remove($ancestor);
        }
        
        $this->em->flush();
        $this->client->beginTransaction();
    }
    
    public function conflictualMappingEntityProvider()
    {
        return array(
            array('Claroline\CommonBundle\Tests\Stub\Entity\InvalidMapping\ConflictualMapping1'),
            array('Claroline\CommonBundle\Tests\Stub\Entity\InvalidMapping\ConflictualMapping2'),
            array('Claroline\CommonBundle\Tests\Stub\Entity\InvalidMapping\ConflictualMapping3')
        );
    }
    
    public function invalidDiscriminatorColumnNameEntityProvider()
    {
        return array(
            array('Claroline\CommonBundle\Tests\Stub\Entity\InvalidMapping\InvalidDiscriminatorColumn1'),
            array('Claroline\CommonBundle\Tests\Stub\Entity\InvalidMapping\InvalidDiscriminatorColumn2')
        );
    }
    
    private function flushTheWholeValidHierarchy()
    {
        $ancestor = new Ancestor();
        $firstChild = new FirstChild();
        $secondChild = new SecondChild();
        $firstDescendant = new FirstDescendant();
        $secondDescendant = new SecondDescendant();
        
        $ancestor->setAncestorField('A');
        $firstChild->setAncestorField('B');
        $firstChild->setFirstChildField('C');
        $secondChild->setAncestorField('D');
        $secondChild->setSecondChildField('E');
        $firstDescendant->setAncestorField('F');
        $firstDescendant->setFirstChildField('G');
        $firstDescendant->setFirstDescendantField('H');
        $secondDescendant->setAncestorField('I');
        $secondDescendant->setFirstChildField('J');
        $secondDescendant->setSecondDescendantField('K');
        
        $this->em->persist($ancestor);
        $this->em->persist($firstChild);
        $this->em->persist($secondChild);
        $this->em->persist($firstDescendant);
        $this->em->persist($secondDescendant);
        $this->em->flush();
    }
    
    private function requestExactType($className)
    {
        return $this->em
            ->createQuery(
                "SELECT a "
                ."FROM Claroline\CommonBundle\Tests\Stub\Entity\\".$className." a "
                ."WHERE a INSTANCE OF Claroline\CommonBundle\Tests\Stub\Entity\\".$className
            )
            ->getResult();
    }
}