# CLI Import for Magento 2

CLI commands that wrap Magento's native **ImportExport** pipeline - the exact same codepath used by **Admin > System > Data Transfer > Import**.

No third-party libraries. No custom CSV parsing. Just a thin CLI layer on top of core Magento.

## Commands

| Command | Description |
|---|---|
| `product:import:validate` | Validate a CSV import file and report errors |
| `product:import:run` | Validate, import, and invalidate indexes |

## Requirements

- Magento 2.4.x (Open Source or Commerce)
- PHP 8.2, 8.3, or 8.4

## Installation

### Via Composer (recommended)

```bash
composer require designcoil/module-import-command
bin/magento module:enable DesignCoil_ImportCommand
bin/magento setup:upgrade
bin/magento setup:di:compile
```

### Manual

Copy the module to `app/code/DesignCoil/ImportCommand/`, then:

```bash
bin/magento module:enable DesignCoil_ImportCommand
bin/magento setup:upgrade
bin/magento setup:di:compile
```

## Usage

### Validate a CSV file

```bash
bin/magento product:import:validate \
  --entity=catalog_product \
  --file=var/import/products.csv
```

Output on success:

```
Validation result: OK

Summary:
  Rows processed:       100
  Entities processed:   100
  Invalid rows:         0
  Total errors:         0
  Error limit exceeded: No
```

Output on failure:

```
Validation result: FAILED

Errors:
  Wrong URL/path used for attribute additional_images in row(s): 1, 2

Summary:
  Rows processed:       100
  Entities processed:   100
  Invalid rows:         2
  Total errors:         2
  Error limit exceeded: No
```

### Run a full import

```bash
bin/magento product:import:run \
  --entity=catalog_product \
  --file=var/import/products.csv \
  --behavior=append \
  --images-file-dir=var/import/images
```

Output:

```
Validating import data...
Validation passed. Importing 100 row(s) in 1 batch(es)...
  1/1 batches [============================] 100%

Import completed successfully.

  Created: 80
  Updated: 20
  Deleted: 0

Summary:
  Rows processed:       100
  Entities processed:   100
  Invalid rows:         0
  Total errors:         0
  Error limit exceeded: No
```

## CLI Options

All options are available on both commands.

### Required

| Option | Description |
|---|---|
| `--entity` | Entity type code: `catalog_product`, `customer`, `customer_address`, etc. |
| `--file` | Path to CSV file. Absolute or relative to Magento root. |

### Import behavior

| Option | Default | Description |
|---|---|---|
| `--behavior` | `append` | Import behavior: `append`, `add_update`, `replace`, `delete` |

### Validation

| Option | Default | Description |
|---|---|---|
| `--validation-strategy` | `validation-stop-on-errors` | `validation-stop-on-errors` or `validation-skip-errors` |
| `--allowed-error-count` | `10` | Maximum number of errors before stopping |

### CSV format

| Option | Default | Description |
|---|---|---|
| `--field-separator` | `,` | Column delimiter |
| `--multiple-value-separator` | `,` | Separator for multi-value fields |
| `--enclosure` | `"` | CSV field enclosure character |
| `--fields-enclosure` | off | Flag. Enables fields enclosed by double-quotes (matches Admin checkbox) |

### File & locale

| Option | Default | Description |
|---|---|---|
| `--images-file-dir` | *(none)* | Images directory relative to Magento root (e.g. `var/import/images`) |
| `--locale` | *(none)* | Locale code for import (e.g. `en_US`) |

## How it works

1. The CSV file is copied into `var/importexport/` - the same temp location Magento's admin upload uses.
2. A native `Magento\ImportExport\Model\Import\Source\Csv` adapter is created.
3. `Import::validateSource()` validates the data and saves it to the database in batches.
4. `Import::importSource()` reads those batches and performs the actual create/update/delete operations.
5. `Import::invalidateIndex()` marks related indexers for reindex.

This is identical to clicking **Check Data** and then **Import** in the admin panel.

## Examples

Product import with semicolon-delimited CSV:

```bash
bin/magento product:import:run \
  --entity=catalog_product \
  --file=/absolute/path/to/products.csv \
  --behavior=append \
  --field-separator=";" \
  --multiple-value-separator="|" \
  --images-file-dir=var/import/images \
  --fields-enclosure
```

Customer import, skip errors:

```bash
bin/magento product:import:run \
  --entity=customer \
  --file=var/import/customers.csv \
  --behavior=add_update \
  --validation-strategy=validation-skip-errors \
  --allowed-error-count=50
```

Validate only (dry run):

```bash
bin/magento product:import:validate \
  --entity=catalog_product \
  --file=var/import/products.csv \
  --validation-strategy=validation-skip-errors \
  --allowed-error-count=100 \
  --images-file-dir=var/import/images
```

## License

[MIT](LICENSE)
