# Tainacan Document Checker

A WordPress plugin for verifying that required documents are attached to Tainacan collection items.

## Description

Tainacan Document Checker helps administrators ensure that all required documents are properly attached to items in their Tainacan collections. The plugin provides both individual and batch verification capabilities, maintains a history of checks, and offers a customizable list of required documents.

## Features

- **Individual Item Verification**: Check specific items for required documents
- **Batch Verification**: Process entire collections with pagination support
- **Customizable Document Requirements**: Manage the list of required document names
- **Check History**: Track verification results over time
- **Caching**: Transient-based caching to reduce API calls
- **Debug Mode**: Detailed logging for troubleshooting
- **Shortcode Support**: Display verification status on the frontend
- **REST API Integration**: Seamless integration with Tainacan's REST API

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Tainacan plugin installed and activated
- Composer for dependency management

## Installation

1. Clone or download this repository to your `wp-content/plugins/` directory:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/your-org/tainacan-document-checker.git
   ```

2. Navigate to the plugin directory and install dependencies:
   ```bash
   cd tainacan-document-checker
   composer install --no-dev --optimize-autoloader
   ```

3. Activate the plugin through the WordPress admin panel

## Usage

### Admin Interface

Navigate to **Document Checker** in your WordPress admin menu. The interface provides several tabs:

- **Single Check**: Verify documents for individual items
- **Batch Check**: Process entire collections
- **History**: View past verification results
- **Settings**: Configure API settings and options
- **Manage Document Names**: Customize required documents

### Shortcode

Display document verification status on the frontend:

```
[tainacan_doc_status item_id="123"]
```

### Required Documents

By default, the plugin checks for these documents:
- `comprovante_endereco` (Address proof)
- `documento_identidade` (Identity document)
- `documento_responsavel` (Responsible party document)

### WP-CLI Support (Future Enhancement)

```bash
# Run batch check for a collection
wp tainacan-doc-check run --collection=123

# Check individual item
wp tainacan-doc-check item --id=456
```

## Development

### Setup Development Environment

1. Install development dependencies:
   ```bash
   composer install
   ```

2. Run code standards check:
   ```bash
   composer run phpcs
   ```

3. Run static analysis:
   ```bash
   composer run phpstan
   ```

4. Run tests:
   ```bash
   composer run test
   ```

### Coding Standards

This plugin follows WordPress PHP Coding Standards. Use the included `phpcs.xml` configuration:

```bash
./vendor/bin/phpcs
```

Fix automatically fixable issues:
```bash
./vendor/bin/phpcbf
```

### Plugin Structure

```
tainacan-document-checker/
├── tainacan-document-checker.php    # Main plugin file
├── composer.json                     # Composer configuration
├── phpcs.xml                        # PHPCS configuration
├── phpstan.neon                     # PHPStan configuration
├── README.md                        # This file
├── includes/                        # Core plugin classes (PSR-4)
│   ├── class-document-checker.php   # Main verification logic
│   ├── class-admin.php             # Admin interface
│   └── class-ajax-handler.php      # AJAX endpoints
├── admin/                          # Admin templates
│   └── admin-page.php             # Main admin page
├── public/                         # Frontend templates
│   └── doc-status.php             # Shortcode template
├── assets/                         # CSS and JavaScript
│   ├── css/
│   │   ├── admin-style.css
│   │   └── frontend-style.css
│   └── js/
│       └── admin.js
├── languages/                      # Translation files
├── tests/                         # PHPUnit tests
└── .github/
    └── workflows/
        └── ci.yml                 # GitHub Actions workflow
```

## Hooks and Filters

### Actions

- `tcd_before_single_check` - Fires before checking a single item
- `tcd_after_single_check` - Fires after checking a single item
- `tcd_before_batch_check` - Fires before starting a batch check
- `tcd_after_batch_check` - Fires after completing a batch check

### Filters

- `tcd_required_documents` - Modify the list of required documents
- `tcd_check_result` - Filter check results before saving
- `tcd_api_timeout` - Modify API request timeout (default: 30 seconds)

## API Reference

### Document_Checker Class

```php
// Check single item
$checker = new \TainacanDocumentChecker\Core\Document_Checker();
$result = $checker->check_item_documents($item_id);

// Check collection batch
$result = $checker->check_collection_documents($collection_id, $page, $per_page);

// Get item history
$history = $checker->get_item_history($item_id, $limit);
```

### AJAX Endpoints

- `tcd_check_single_item` - Check individual item
- `tcd_check_batch` - Run batch check
- `tcd_get_item_history` - Retrieve check history
- `tcd_clear_cache` - Clear transient cache

## Troubleshooting

### Enable Debug Mode

1. Go to **Document Checker** → **Settings**
2. Check **Enable debug mode**
3. Debug information will appear in check results

### Common Issues

- **API Connection Failed**: Verify the Tainacan API URL in settings
- **No Attachments Found**: Check if the item has attachments in Tainacan
- **Missing Documents**: Ensure document names match exactly (case-sensitive)

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed for the Tainacan community to enhance document management and verification capabilities.

## Support

For issues and feature requests, please use the [GitHub issue tracker](https://github.com/your-org/tainacan-document-checker/issues).