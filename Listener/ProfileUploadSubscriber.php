<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Bundle\ApiUserBundle\Listener;

use Klipper\Component\Content\ImageManipulator\Cache\CacheInterface;
use Klipper\Component\Content\Uploader\Event\UploadFileCompletedEvent;
use Klipper\Component\Content\Util\ContentUtil;
use Klipper\Component\Resource\Domain\DomainManagerInterface;
use Klipper\Component\Resource\Exception\ConstraintViolationException;
use Klipper\Component\User\Model\ProfileInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class ProfileUploadSubscriber implements EventSubscriberInterface
{
    private DomainManagerInterface $domainManager;

    private ?CacheInterface $imageManipulatorCache;

    private Filesystem $fs;

    public function __construct(
        DomainManagerInterface $domainManager,
        ?CacheInterface $imageManipulatorCache = null,
        ?Filesystem $fs = null
    ) {
        $this->domainManager = $domainManager;
        $this->imageManipulatorCache = $imageManipulatorCache;
        $this->fs = $fs ?? new Filesystem();
    }

    public static function getSubscribedEvents(): iterable
    {
        return [
            UploadFileCompletedEvent::class => [
                ['onUploadRequest', 0],
            ],
        ];
    }

    /**
     * @throws
     */
    public function onUploadRequest(UploadFileCompletedEvent $event): void
    {
        $file = $event->getFile()->getPathname();
        $profile = $event->getPayload();

        if ('user_profile_image' !== $event->getConfig()->getName()
                || !$profile instanceof ProfileInterface) {
            return;
        }

        $previousFile = $profile->getImagePath();
        $profile->setImagePath(ContentUtil::getRelativePath($this->fs, $event->getConfig(), $file));
        $res = $this->domainManager->get(ProfileInterface::class)->update($profile);

        if (!$res->isValid()) {
            $this->fs->remove($file);

            throw new ConstraintViolationException($res->getErrors());
        }

        try {
            $this->fs->remove(ContentUtil::getAbsolutePath($event->getConfig(), $previousFile));
        } catch (\Throwable $e) {
            // no check to optimize request to delete file, so do nothing on error
        }

        try {
            if (null !== $this->imageManipulatorCache) {
                $this->imageManipulatorCache->clear(
                    ContentUtil::getAbsolutePath($event->getConfig(), $previousFile)
                );
            }
        } catch (\Throwable $e) {
            // no check to optimize request to delete file, so do nothing on error
        }
    }
}
