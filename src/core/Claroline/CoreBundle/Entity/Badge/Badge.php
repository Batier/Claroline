<?php

namespace Claroline\CoreBundle\Entity\Badge;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Badge
 *
 * @ORM\Table(name="claro_badge")
 * @ORM\Entity(repositoryClass="Claroline\CoreBundle\Repository\BadgeRepository")
 * @ORM\HasLifecycleCallbacks
 */
class Badge
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var integer
     *
     * @ORM\Column(type="smallint", nullable=false)
     */
    protected $version;

    /**
     * @var string
     *
     * @ORM\Column(name="image", type="string", nullable=false)
     */
    protected $imagePath;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="expired_at", type="datetime", nullable=true)
     */
    protected $expiredAt;

    /**
     * @var UploadedFile
     *
     * @Assert\File
     */
    protected $file;

    /**
     * @var BadgeTranslation[]
     *
     * @ORM\OneToMany(
     *   targetEntity="Claroline\CoreBundle\Entity\Badge\BadgeTranslation",
     *   mappedBy="badge",
     *   cascade={"persist", "remove"}
     * )
     */
    private $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    /**
     * @return BadgeTranslation[]
     */
    public function getTranslations()
    {
        return $this->translations;
    }

    /**
     * @param string $locale
     *
     * @return BadgeTranslation|null
     */
    public function getTranslationForLocale($locale)
    {
        foreach($this->getTranslations() as $translation)
        {
            if($locale === $translation->getLocale()) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * @return BadgeTranslation|null
     */
    public function getFrTranslation()
    {
        return $this->getTranslationForLocale('fr');
    }

    /**
     * @return BadgeTranslation|null
     */
    public function getEnTranslation()
    {
        return $this->getTranslationForLocale('en');
    }

    /**
     * @param BadgeTranslation $translation
     * @return Badge
     */
    public function addTranslation(BadgeTranslation $translation)
    {
        if (!$this->translations->contains($translation)) {
            $this->translations[] = $translation;
            $translation->setBadge($this);
        }

        return $this;
    }

    /**
     * @param BadgeTranslation $translation
     * @return Badge
     */
    public function removeTranslation(BadgeTranslation $translation)
    {
        $this->translations->removeElement($translation);

        return $this;
    }

    /**
     * @param \DateTime $expiredAt
     *
     * @return Badge
     */
    public function setExpiredAt($expiredAt)
    {
        $this->expiredAt = $expiredAt;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getExpiredAt()
    {
        return $this->expiredAt;
    }

    /**
     * @param int $id
     *
     * @return Badge
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $imagePath
     *
     * @return Badge
     */
    public function setImagePath($imagePath)
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    /**
     * @return string
     */
    public function getImagePath()
    {
        return $this->imagePath;
    }

    /**
     * @param int $version
     *
     * @return Badge
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @return int
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param UploadedFile $file
     *
     * @return Badge
     */
    public function setFile(UploadedFile $file)
    {
        $this->file = $file;

        return $this;
    }

    /**
     * @return UploadedFile
     */
    public function getFile()
    {
        return $this->file;
    }
    /**
     * @return null|string
     */
    public function getAbsolutePath()
    {
        return (null === $this->imagePath) ? null : $this->getUploadRootDir() . DIRECTORY_SEPARATOR . $this->slug . '.' . $this->imagePath;
    }

    /**
     * @return null|string
     */
    public function getWebPath()
    {
        return (null === $this->imagePath) ? null : $this->getUploadDir() . DIRECTORY_SEPARATOR . $this->slug . '.' . $this->imagePath;
    }

    /**
     * @return string
     */
    protected function getUploadRootDir()
    {
        $ds = DIRECTORY_SEPARATOR;
        return sprintf('%s%s..%s..%s..%s..%s..%s..%sweb%s%s', __DIR__, $ds, $ds, $ds, $ds, $ds, $ds, $ds, $ds, $this->getUploadDir());
    }

    /**
     * @return string
     */
    protected function getUploadDir()
    {
        return sprintf("uploads%sbadges", DIRECTORY_SEPARATOR);
    }

    /**
     * @ORM\PrePersist()
     */
    public function PrePersist()
    {
        if (null !== $this->file) {
            $this->imagePath = $this->file->guessExtension();
        }
    }

    /**
     * @ORM\PreUpdate()
     */
    public function preUpdate(PreUpdateEventArgs $eventArgs)
    {
        $oldImagePath = $this->imagePath;

        if (null !== $this->file) {
            $this->removeUpload();
            $this->imagePath = $this->file->guessExtension();
        }

        if($eventArgs->hasChangedField('name')) {
            $oldFilePath = sprintf('%s%s%s.%s', $this->getUploadRootDir(), DIRECTORY_SEPARATOR, $eventArgs->getOldValue('slug'), $oldImagePath);
            $newFilePath = sprintf('%s%s%s.%s', $this->getUploadRootDir(), DIRECTORY_SEPARATOR, $eventArgs->getNewValue('slug'), $this->imagePath);
            rename($oldFilePath, $newFilePath);
        }
    }

    /**
     * @ORM\PostPersist()
     * @ORM\PostUpdate()
     */
    public function upload()
    {
        if (null === $this->file) {
            return;
        }

        $this->file->move($this->getUploadRootDir(), $this->slug . '.' . $this->file->guessExtension());

        unset($this->file);
    }

    /**
     * @ORM\PostRemove()
     */
    public function removeUpload()
    {
        $filePath = $this->getAbsolutePath();
        if (null !== $filePath) {
            if(file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }
}
