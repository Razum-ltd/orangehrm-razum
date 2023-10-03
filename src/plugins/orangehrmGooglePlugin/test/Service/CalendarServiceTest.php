<?php
namespace OrangeHRM\Tests\Google\Service;

use OrangeHRM\Framework\Cache\FilesystemAdapter;
use OrangeHRM\Google\Service\CalendarService;
use OrangeHRM\Tests\Util\KernelTestCase;

class CalendarServiceTest extends KernelTestCase
{
    private $calendarService;

    protected function setUp(): void
    {
        $this->calendarService = new CalendarService();
        $cache = new FilesystemAdapter();
        $cache->clear();
        $this->createKernelWithMockServices([Services::CACHE => $cache]);
    }
}