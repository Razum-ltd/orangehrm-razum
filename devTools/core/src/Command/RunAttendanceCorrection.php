<?php

namespace OrangeHRM\DevTools\Command;

use OrangeHRM\Attendance\Traits\Service\AttendanceCorrectionServiceTrait;
use OrangeHRM\Config\Config;
use OrangeHRM\Core\Traits\ORM\EntityManagerHelperTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RunAttendanceCorrection extends Command
{
    use EntityManagerHelperTrait;
    use AttendanceCorrectionServiceTrait;
    protected static $defaultName = 'attendance:correction';
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setDescription('Run attendance correction')
            ->setHelp(
                'E.g. php devTools/core/console.php attendance:correction'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!Config::isInstalled()) {
            $io->warning('Application not installed.');
            return Command::INVALID;
        }

        try {
            $this->getAttendanceCorrectionService()->runCorrection();
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            $io->info($e->getTraceAsString());
            return Command::FAILURE;
        }

        $io->success("Attendance correction completed successfully");
        return Command::SUCCESS;
    }
}