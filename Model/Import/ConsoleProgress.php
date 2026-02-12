<?php

declare(strict_types=1);

namespace DesignCoil\ImportCommand\Model\Import;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Shared (singleton) service that holds a Symfony ProgressBar so that the
 * ImportDataProgressPlugin can advance it each time a data bunch is fetched
 * during import.
 */
class ConsoleProgress
{
    private ?ProgressBar $progressBar = null;

    private ?OutputInterface $output = null;

    /**
     * Begin tracking progress.
     */
    public function start(OutputInterface $output, int $totalBunches): void
    {
        $this->output      = $output;
        $this->progressBar = new ProgressBar($output, $totalBunches);
        $this->progressBar->setFormat('  %current%/%max% batches [%bar%] %percent:3s%%');
        $this->progressBar->start();
    }

    /**
     * Move the progress bar forward by one step.
     */
    public function advance(): void
    {
        $this->progressBar?->advance();
    }

    /**
     * Complete the progress bar and release references.
     */
    public function finish(): void
    {
        if ($this->progressBar !== null) {
            $this->progressBar->finish();
            $this->output?->writeln('');
            $this->progressBar = null;
            $this->output      = null;
        }
    }

    public function isActive(): bool
    {
        return $this->progressBar !== null;
    }
}
