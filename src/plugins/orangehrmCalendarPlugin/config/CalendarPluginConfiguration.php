<?php

use OrangeHRM\Core\Traits\ServiceContainerTrait;
use OrangeHRM\Framework\Http\Request;
use OrangeHRM\Framework\Logger\LoggerFactory;
use OrangeHRM\Framework\PluginConfigurationInterface;
use OrangeHRM\Framework\Services;
use OrangeHRM\Calendar\Service\CalendarService;

class CalendarPluginConfiguration implements PluginConfigurationInterface
{
    use ServiceContainerTrait;

    /**
     * @inheritDoc
     */
    public function initialize(Request $request): void
    {
        $this->getContainer()->register(Services::CALENDAR_SERVICE, CalendarService::class);

        $this->getContainer()->register(Services::CALENDAR_LOGGER)
            ->setFactory([LoggerFactory::class, 'getLogger'])
            ->addArgument('Calendar')
            ->addArgument('calendar.log');
    }
}
