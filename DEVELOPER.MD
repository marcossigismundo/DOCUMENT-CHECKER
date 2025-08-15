# Developer Documentation

This document provides detailed technical information for developers working on the Tainacan Document Checker plugin.

## Architecture Overview

The plugin follows a modular architecture with clear separation of concerns:

- **Core Logic**: Document verification and API interaction (`includes/class-document-checker.php`)
- **Admin Interface**: WordPress admin integration (`includes/class-admin.php`)
- **AJAX Handling**: Asynchronous operations (`includes/class-ajax-handler.php`)
- **Frontend Display**: Shortcode implementation (`public/doc-status.php`)

## Development Setup

### Prerequisites

- PHP 7.4 or higher
- Composer 2.x
- WordPress 5.8 or higher
- Node.js (optional, for asset compilation)

### Installation for Development

1. Clone the repository:
```bash
git clone https://github.com/your-org/tainacan-document-checker.git
cd tainacan-document-checker
```

2. Install dependencies:
```bash
composer install
```

3. Set up pre-commit hooks (optional):
```bash
cp hooks/pre-commit .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
```

## Code Standards

### PHP Standards

We follow WordPress PHP Coding Standards with some specific rules:

- **PHP 7.4+ features**: Use type declarations, arrow functions, null coalescing operators
- **Strict typing**: All PHP files must declare `declare(strict_types=1);`
- **Namespacing**: Use PSR-4 autoloading with `TainacanDocumentChecker\Core` namespace
- **Documentation**: PHPDoc blocks for all public methods and classes

### JavaScript Standards

- Use ES6+ features where appropriate
- jQuery for DOM manipulation (WordPress standard)
- Proper event delegation for dynamic content

### CSS Standards

- BEM methodology for class naming
- Prefix all classes with `tcd-`
- Mobile-first responsive design
- Support for WordPress admin color schemes

## Testing

### Unit Tests

Run PHPUnit tests:
```bash
composer test
```

Run specific test file:
```bash
./vendor/bin/phpunit tests/test-document-checker.php
```

### Integration Tests

Set up WordPress test environment:
```bash
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

### Code Coverage

Generate code coverage report:
```bash
composer test -- --coverage-html coverage
```

View report at `coverage/index.html`

## API Integration

### Tainacan REST API Endpoints

The plugin interacts with these Tainacan endpoints:

- `GET /items/{id}/attachments` - Fetch item attachments
- `GET /collection/{id}/items` - Fetch collection items

### Request Headers

```php
array(
    'Accept' => 'application/json',
    'timeout' => 30
)
```

### Error Handling

All API calls should handle:
- Network timeouts
- Invalid JSON responses
- 404 errors for missing items
- Rate limiting (if implemented)

## Database Schema

### Table: wp_tainacan_document_checks

```sql
CREATE TABLE wp_tainacan_document_checks (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    item_id bigint(20) unsigned NOT NULL,
    collection_id bigint(20) unsigned NOT NULL,
    check_type varchar(20) NOT NULL DEFAULT 'individual',
    check_status varchar(20) NOT NULL,
    missing_documents text DEFAULT NULL,
    found_documents text DEFAULT NULL,
    check_date datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY item_id (item_id),
    KEY collection_id (collection_id),
    KEY check_date (check_date)
)
```

## AJAX Endpoints

### tcd_check_single_item

**Request:**
```javascript
{
    action: 'tcd_check_single_item',
    item_id: 123,
    nonce: 'security_nonce'
}
```

**Response:**
```javascript
{
    success: true,
    data: {
        status: 'complete|incomplete',
        item_id: 123,
        html: '<div>...</div>',
        summary: {
            total_attachments: 5,
            found_documents: 3,
            missing_documents: 0
        }
    }
}
```

### tcd_check_batch

**Request:**
```javascript
{
    action: 'tcd_check_batch',
    collection_id: 1,
    page: 1,
    per_page: 20,
    nonce: 'security_nonce'
}
```

**Response:**
```javascript
{
    success: true,
    data: {
        page: 1,
        total_pages: 5,
        total_items: 100,
        has_more: true,
        summary: {
            complete: 15,
            incomplete: 5,
            error: 0
        },
        html: '<div>...</div>',
        progress: 20
    }
}
```

## Transient Caching

### Cache Keys

- Single item: `tcd_item_{item_id}`
- Batch results: `tcd_batch_{collection_id}_{page}_{per_page}`

### Cache Duration

Default: 300 seconds (5 minutes)
Configurable via Settings page

### Cache Invalidation

```php
// Clear specific cache
delete_transient('tcd_item_123');

// Clear all plugin caches
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tcd_%'");
```

## Hooks Reference

### Actions

```php
// Before checking single item
do_action('tcd_before_single_check', $item_id);

// After checking single item
do_action('tcd_after_single_check', $item_id, $result);

// Before batch check
do_action('tcd_before_batch_check', $collection_id, $page, $per_page);

// After batch check
do_action('tcd_after_batch_check', $collection_id, $results);
```

### Filters

```php
// Modify required documents list
$documents = apply_filters('tcd_required_documents', $documents);

// Filter check results
$result = apply_filters('tcd_check_result', $result, $item_id);

// Modify API timeout
$timeout = apply_filters('tcd_api_timeout', 30);

// Filter attachment data before processing
$attachment = apply_filters('tcd_process_attachment', $attachment, $item_id);
```

## Security Considerations

### Nonce Verification

All AJAX requests must include valid nonces:
```php
check_ajax_referer('tcd_ajax_nonce', 'nonce');
```

### Capability Checks

Admin functions require appropriate capabilities:
```php
current_user_can('manage_options')
```

### Data Sanitization

- Item IDs: `absint()`
- Text fields: `sanitize_text_field()`
- URLs: `esc_url_raw()`
- HTML output: `wp_kses_post()` or `esc_html()`

### SQL Injection Prevention

Use prepared statements:
```php
$wpdb->prepare("SELECT * FROM {$table} WHERE item_id = %d", $item_id)
```

## Performance Optimization

### Database Queries

- Use proper indexes on frequently queried columns
- Limit result sets with appropriate LIMIT clauses
- Consider pagination for large datasets

### API Calls

- Implement request throttling
- Use transient caching
- Batch process items to reduce API calls

### JavaScript

- Debounce rapid user actions
- Use event delegation for dynamic content
- Minimize DOM manipulation

## Debugging

### Enable Debug Mode

In Settings:
1. Check "Enable debug mode"
2. Debug info appears in check results

### Debug Logging

```php
if (WP_DEBUG_LOG) {
    error_log('TCD Debug: ' . print_r($data, true));
}
```

### Common Issues

1. **API Connection Failed**
   - Check API URL configuration
   - Verify SSL certificates
   - Check server firewall rules

2. **Missing Attachments**
   - Verify Tainacan API is returning data
   - Check attachment mime types
   - Ensure proper permissions

3. **Performance Issues**
   - Increase cache duration
   - Reduce batch size
   - Check server resources

## Deployment

### Production Build

```bash
# Install production dependencies only
composer install --no-dev --optimize-autoloader

# Remove development files
rm -rf tests/ *.xml *.neon .github/ DEVELOPER.md

# Create zip for distribution
zip -r tainacan-document-checker.zip . -x "*.git*" "node_modules/*" "*.DS_Store"
```

### Version Bumping

Update version in:
- `tainacan-document-checker.php` header
- `composer.json`
- `README.md`

### Release Checklist

1. Run all tests
2. Update changelog
3. Create git tag
4. Build production package
5. Create GitHub release
6. Update documentation

## Contributing Guidelines

### Branch Strategy

- `main` - Production-ready code
- `develop` - Integration branch
- `feature/*` - New features
- `bugfix/*` - Bug fixes
- `hotfix/*` - Emergency fixes

### Pull Request Process

1. Fork the repository
2. Create feature branch
3. Write tests for new functionality
4. Ensure all tests pass
5. Update documentation
6. Submit PR with detailed description

### Code Review Checklist

- [ ] Follows coding standards
- [ ] Includes appropriate tests
- [ ] Documentation updated
- [ ] Security considerations addressed
- [ ] Performance impact assessed
- [ ] Backward compatibility maintained

## Support

For development questions:
- GitHub Issues: [Project Issues](https://github.com/your-org/tainacan-document-checker/issues)
- Email: dev@your-website.com
- Slack: #tainacan-dev channel