<?php
/**
 * This file is part of the LuneticsLocaleBundle package.
 *
 * <https://github.com/lunetics/LocaleBundle/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that is distributed with this source code.
 */
namespace Lunetics\LocaleBundle\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

use Lunetics\LocaleBundle\LocaleGuesser\LocaleGuesserManager;
use Lunetics\LocaleBundle\Event\FilterLocaleSwitchEvent;
use Lunetics\LocaleBundle\LocaleBundleEvents;

/**
 * Locale Listener
 *
 * @author Christophe Willemsen <willemsen.christophe@gmail.com>
 * @author Matthias Breddin <mb@lunetics.com>
 */
class LocaleListener
{
    /**
     * @var string Default framework locale
     */
    private $defaultLocale;

    /**
     * @var LocaleGuesserManager
     */
    private $guesserManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EventDispatcher
     */
    private $dispatcher;

    /**
     * Construct the guessermanager
     *
     * @param string               $defaultLocale  Framework default locale
     * @param LocaleGuesserManager $guesserManager Locale Guesser Manager
     * @param LoggerInterface      $logger         Logger
     */
    public function __construct($defaultLocale = 'en', LocaleGuesserManager $guesserManager, LoggerInterface $logger = null)
    {
        $this->defaultLocale = $defaultLocale;
        $this->guesserManager = $guesserManager;
        $this->logger = $logger;
    }

    /**
     * Called at the "kernel.request" event
     *
     * Call the LocaleGuesserManager to guess the locale
     * by the activated guessers
     *
     * Sets the identified locale as default locale to the request
     *
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        /** @var $request \Symfony\Component\HttpFoundation\Request */
        $request = $event->getRequest();

        if ($event->getRequestType() !== HttpKernelInterface::MASTER_REQUEST && !$request->isXmlHttpRequest()) {
            $this->logEvent('Request is not a "MASTER_REQUEST" : SKIPPING...');

            return;
        }

        $manager = $this->guesserManager;

        if ($locale = $manager->runLocaleGuessing($request)) {
            $this->logEvent('Setting [ %s ] as defaultLocale for the Request', $locale);
            $request->setLocale($locale);
            if ($manager->getGuesser('session') || $manager->getGuesser('cookie')) {
                $localeSwitchEvent = new FilterLocaleSwitchEvent($locale);
                $this->dispatcher->dispatch(LocaleBundleEvents::onLocaleChange, $localeSwitchEvent);
            }

            $this->dispatcher->addListener(KernelEvents::RESPONSE, function(FilterResponseEvent $event) {
                return $event->getResponse()->setVary('Accept-Language');
            });

            return;
        }
        $request->setDefaultLocale($this->defaultLocale);
    }

    /**
     * DI Setter for the EventDispatcher
     *
     * @param \Symfony\Component\EventDispatcher\EventDispatcher $dispatcher
     */
    public function setEventDispatcher(EventDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Log detection events
     *
     * @param string $logMessage
     * @param string $parameters
     */
    private function logEvent($logMessage, $parameters = null)
    {
        if (null !== $this->logger) {
            $this->logger->info(sprintf($logMessage, $parameters));
        }
    }
}
