<?php

declare(strict_types=1);

namespace DesignCoil\ImportCommand\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI command: product:import:validate
 *
 * Validates an import file through Magento's native ImportExport pipeline
 * (the same codepath used by Admin > System > Data Transfer > Import).
 */
class ValidateImportCommand extends AbstractImportCommand
{
    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('product:import:validate')
            ->setDescription('Validate an import file using Magento native import validation');
        $this->addImportOptions();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initAreaCode();

        try {
            $this->setupImport($input);
            $source = $this->createSourceAdapter($input);
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return Command::FAILURE;
        }

        try {
            $result = $this->import->validateSource($source);
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Validation failed: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }

        $errorAggregator = $this->import->getErrorAggregator();

        if ($result) {
            $output->writeln('<info>Validation result: OK</info>');
        } else {
            $output->writeln('<error>Validation result: FAILED</error>');
            $this->outputErrors($output, $errorAggregator);
        }

        $this->outputSummary($output, $errorAggregator);

        return $result ? Command::SUCCESS : Command::FAILURE;
    }
}
