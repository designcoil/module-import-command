<?php

declare(strict_types=1);

namespace DesignCoil\ImportCommand\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\AbstractSource;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingError;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\ImportExport\Model\Import\Source\Csv;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractImportCommand extends Command
{
    /**
     * @var Import
     */
    protected Import $import;

    /**
     * @var Filesystem
     */
    protected Filesystem $filesystem;

    /**
     * @var State
     */
    private State $appState;

    public function __construct(
        Import $import,
        Filesystem $filesystem,
        State $appState
    ) {
        $this->import = $import;
        $this->filesystem = $filesystem;
        $this->appState = $appState;
        parent::__construct();
    }

    /**
     * Add common import CLI options to the command definition.
     */
    protected function addImportOptions(): void
    {
        $this->addOption(
            'entity',
            null,
            InputOption::VALUE_REQUIRED,
            'Entity type code (e.g. catalog_product, customer)'
        );
        $this->addOption(
            'file',
            null,
            InputOption::VALUE_REQUIRED,
            'Path to import CSV file (absolute or relative to Magento root)'
        );
        $this->addOption(
            'behavior',
            null,
            InputOption::VALUE_OPTIONAL,
            'Import behavior: append, add_update, replace, delete',
            'append'
        );
        $this->addOption(
            'validation-strategy',
            null,
            InputOption::VALUE_OPTIONAL,
            'Validation strategy: validation-stop-on-errors, validation-skip-errors',
            ProcessingErrorAggregatorInterface::VALIDATION_STRATEGY_STOP_ON_ERROR
        );
        $this->addOption(
            'allowed-error-count',
            null,
            InputOption::VALUE_OPTIONAL,
            'Maximum allowed error count',
            '10'
        );
        $this->addOption(
            'field-separator',
            null,
            InputOption::VALUE_OPTIONAL,
            'CSV field separator',
            ','
        );
        $this->addOption(
            'multiple-value-separator',
            null,
            InputOption::VALUE_OPTIONAL,
            'Multiple value separator',
            ','
        );
        $this->addOption(
            'enclosure',
            null,
            InputOption::VALUE_OPTIONAL,
            'CSV field enclosure character',
            '"'
        );
        $this->addOption(
            'fields-enclosure',
            null,
            InputOption::VALUE_NONE,
            'Fields enclosed by double-quotes (matches Admin "Fields enclosure" checkbox)'
        );
        $this->addOption(
            'images-file-dir',
            null,
            InputOption::VALUE_OPTIONAL,
            'Images file directory (relative to Magento root, e.g. var/import/images)'
        );
        $this->addOption(
            'locale',
            null,
            InputOption::VALUE_OPTIONAL,
            'Locale for import (e.g. en_US)'
        );
    }

    /**
     * Set adminhtml area code so import entity adapters resolve correctly.
     */
    protected function initAreaCode(): void
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (LocalizedException $e) {
            // Area code already set – safe to ignore.
        }
    }

    /**
     * Populate the Import model with configuration derived from CLI options.
     */
    protected function setupImport(InputInterface $input): void
    {
        $data = [
            'entity'                                    => $input->getOption('entity'),
            'behavior'                                  => $input->getOption('behavior'),
            Import::FIELD_NAME_VALIDATION_STRATEGY      => $input->getOption('validation-strategy'),
            Import::FIELD_NAME_ALLOWED_ERROR_COUNT      => (int) $input->getOption('allowed-error-count'),
            Import::FIELD_FIELD_SEPARATOR               => $input->getOption('field-separator'),
            Import::FIELD_FIELD_MULTIPLE_VALUE_SEPARATOR => $input->getOption('multiple-value-separator'),
            Import::FIELDS_ENCLOSURE                    => $input->getOption('fields-enclosure') ? 1 : 0,
        ];

        $imagesDir = $input->getOption('images-file-dir');
        if ($imagesDir !== null) {
            $data[Import::FIELD_NAME_IMG_FILE_DIR] = $imagesDir;
        }

        $locale = $input->getOption('locale');
        if ($locale !== null) {
            $data['locale'] = $locale;
        }

        $this->import->setData($data);
    }

    /**
     * Copy the user-supplied file into var/importexport/ and return a CSV source adapter.
     *
     * This mirrors what the Admin UI does via uploadSource() – the file is placed in the
     * same temporary location Magento expects so that the native import pipeline works
     * exactly as it does through the back-office.
     *
     * @throws \InvalidArgumentException  When the resolved file does not exist.
     * @throws LocalizedException
     */
    protected function createSourceAdapter(InputInterface $input): AbstractSource
    {
        $entity    = $input->getOption('entity');
        $filePath  = $input->getOption('file');
        $delimiter = $input->getOption('field-separator');
        $enclosure = $input->getOption('enclosure');

        $rootDir = $this->filesystem->getDirectoryRead(DirectoryList::ROOT);
        $varDir  = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);

        $resolvedPath = $this->resolveFilePath($filePath, $rootDir->getAbsolutePath());

        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        if (!file_exists($resolvedPath)) {
            throw new \InvalidArgumentException("Import file not found: $resolvedPath");
        }

        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $extension    = pathinfo($resolvedPath, PATHINFO_EXTENSION) ?: 'csv';
        $destRelative = 'importexport/' . $entity . '.' . $extension;

        $varDir->create('importexport');

        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        copy($resolvedPath, $varDir->getAbsolutePath($destRelative));

        return new Csv(
            $varDir->getAbsolutePath($destRelative),
            $this->filesystem->getDirectoryWrite(DirectoryList::ROOT),
            $delimiter,
            $enclosure
        );
    }

    /**
     * Print validation / import errors grouped by error message → row numbers.
     */
    protected function outputErrors(OutputInterface $output, ProcessingErrorAggregatorInterface $errorAggregator): void
    {
        $grouped = $errorAggregator->getRowsGroupedByErrorCode([], [], true);
        if (empty($grouped)) {
            return;
        }

        $output->writeln('');
        $output->writeln('<error>Errors:</error>');

        foreach ($grouped as $message => $rows) {
            $rowList = implode(', ', $rows);
            $output->writeln(sprintf('  %s in row(s): %s', $message, $rowList));
        }
    }

    /**
     * Print a structured summary block (rows, entities, invalid, errors, limit status).
     */
    protected function outputSummary(
        OutputInterface $output,
        ProcessingErrorAggregatorInterface $errorAggregator
    ): void {
        $output->writeln('');
        $output->writeln('<info>Summary:</info>');
        $output->writeln(sprintf('  Rows processed:       %d', $this->import->getProcessedRowsCount()));
        $output->writeln(sprintf('  Entities processed:   %d', $this->import->getProcessedEntitiesCount()));
        $output->writeln(sprintf('  Invalid rows:         %d', $errorAggregator->getInvalidRowsCount()));
        $output->writeln(sprintf(
            '  Total errors:         %d',
            $errorAggregator->getErrorsCount([
                ProcessingError::ERROR_LEVEL_CRITICAL,
                ProcessingError::ERROR_LEVEL_NOT_CRITICAL,
            ])
        ));
        $output->writeln(sprintf(
            '  Error limit exceeded: %s',
            $errorAggregator->isErrorLimitExceeded() ? 'Yes' : 'No'
        ));
    }

    // ---------------------------------------------------------------
    //  Private helpers
    // ---------------------------------------------------------------

    /**
     * Turn a potentially-relative path into an absolute one (relative base = Magento root).
     */
    private function resolveFilePath(string $filePath, string $rootPath): string
    {
        if ($this->isAbsolutePath($filePath)) {
            return $filePath;
        }

        return rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . $filePath;
    }

    /**
     * Detect Unix (/…) and Windows (C:\…) absolute paths.
     */
    private function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return true;
        }
        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $path)) {
            return true;
        }

        return false;
    }
}
