<?php

declare(strict_types=1);

namespace DesignCoil\ImportCommand\Plugin;

use DesignCoil\ImportCommand\Model\Import\ConsoleProgress;
use Magento\ImportExport\Model\ResourceModel\Import\Data;

/**
 * After-plugin on the import data resource model.
 *
 * Every time a data bunch is fetched during import (via getNextBunch or
 * getNextUniqueBunch), the console progress bar is advanced by one step.
 */
class ImportDataProgressPlugin
{
    public function __construct(
        private readonly ConsoleProgress $progress
    ) {
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetNextBunch(Data $subject, $result)
    {
        if ($result !== null && $this->progress->isActive()) {
            $this->progress->advance();
        }
        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetNextUniqueBunch(Data $subject, $result)
    {
        if ($result !== null && $this->progress->isActive()) {
            $this->progress->advance();
        }
        return $result;
    }
}
