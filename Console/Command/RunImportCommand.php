<?php

declare(strict_types=1);

namespace DesignCoil\ImportCommand\Console\Command;

use DesignCoil\ImportCommand\Model\Import\ConsoleProgress;
use Magento\Framework\App\State;
use Magento\Framework\Filesystem;
use Magento\ImportExport\Model\Import;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI command: product:import:run
 *
 * Performs a full import (validate → import → invalidate indexes) through
 * Magento's native ImportExport pipeline – the same codepath used by
 * Admin > System > Data Transfer > Import.
 */
class RunImportCommand extends AbstractImportCommand
{
    private ConsoleProgress $consoleProgress;

    public function __construct(
        Import $import,
        Filesystem $filesystem,
        State $appState,
        ConsoleProgress $consoleProgress
    ) {
        $this->consoleProgress = $consoleProgress;
        parent::__construct($import, $filesystem, $appState);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('product:import:run')
            ->setDescription('Run import using Magento native import execution');
        $this->addImportOptions();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initAreaCode();

        // ── Bootstrap ────────────────────────────────────────────────
        try {
            $this->setupImport($input);
            $source = $this->createSourceAdapter($input);
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return Command::FAILURE;
        }

        // ── Validate ─────────────────────────────────────────────────
        $output->writeln('<info>Validating import data...</info>');

        try {
            $validationResult = $this->import->validateSource($source);
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Validation failed: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }

        $errorAggregator = $this->import->getErrorAggregator();

        if (!$validationResult) {
            $output->writeln('<error>Validation failed. Import aborted.</error>');
            $this->outputErrors($output, $errorAggregator);
            $this->outputSummary($output, $errorAggregator);
            return Command::FAILURE;
        }

        // ── Import ───────────────────────────────────────────────────
        $totalBunches = count($this->import->getValidatedIds());
        $output->writeln(sprintf(
            '<info>Validation passed. Importing %d row(s) in %d batch(es)...</info>',
            $this->import->getProcessedRowsCount(),
            $totalBunches
        ));

        $this->consoleProgress->start($output, $totalBunches);

        try {
            $importResult = $this->import->importSource();
        } catch (\Exception $e) {
            $this->consoleProgress->finish();
            $output->writeln(sprintf('<error>Import failed: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }

        $this->consoleProgress->finish();

        if (!$importResult) {
            $output->writeln('<error>Import execution returned failure.</error>');
            $this->outputErrors($output, $errorAggregator);
            $this->outputSummary($output, $errorAggregator);
            return Command::FAILURE;
        }

        if ($errorAggregator->hasToBeTerminated()) {
            $output->writeln('<error>Import completed with critical errors.</error>');
            $this->outputErrors($output, $errorAggregator);
            $this->outputSummary($output, $errorAggregator);
            return Command::FAILURE;
        }

        // ── Post-import: invalidate indexes ──────────────────────────
        $this->import->invalidateIndex();

        // ── Results ──────────────────────────────────────────────────
        $output->writeln('');
        $output->writeln('<info>Import completed successfully.</info>');
        $output->writeln('');
        $output->writeln(sprintf('  Created: %d', $this->import->getCreatedItemsCount()));
        $output->writeln(sprintf('  Updated: %d', $this->import->getUpdatedItemsCount()));
        $output->writeln(sprintf('  Deleted: %d', $this->import->getDeletedItemsCount()));

        if ($errorAggregator->getErrorsCount() > 0) {
            $this->outputErrors($output, $errorAggregator);
        }

        $this->outputSummary($output, $errorAggregator);

        return Command::SUCCESS;
    }
}
