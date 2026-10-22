# API Documentation with Scribe

Scribe has been successfully installed and configured for this Laravel 13 application.

## Installation Status
- ✅ Package `knuckleswtf/scribe` installed as a development dependency
- ✅ Configuration published to `config/scribe.php`
- ✅ Service provider automatically discovered and registered

## Usage

### Generate Documentation
```bash
# Generate HTML documentation (default)
php artisan scribe:generate

# Generate Markdown documentation
php artisan scribe:generate --format=markdown

# Generate OpenAPI 3.0 JSON specification
php artisan scribe:generate --format=openapi

# Force regeneration (overwrite existing files)
php artisan scribe:generate --force
```

### Configuration
The Scribe configuration file is located at `config/scribe.php`. You can customize:
- Documentation title and description
- Authentication strategies
- API URL and version
- Response field extraction
- And many other options

### Output
By default, Scribe generates documentation in:
- `docs/` folder (HTML)
- `docs/scribe.json` (OpenAPI specification)
- Various intermediate files in `._scribe/` 

## For Internal Consumers
- Share the generated HTML documentation via your internal wiki or portal
- Provide the OpenAPI/Swagger JSON for API testing and client generation

## For External Consumers
- Publish the HTML documentation to your developer portal
- Share the OpenAPI specification with partners and third-party developers
- Consider versioning your documentation alongside your API versions

## Next Steps
1. Run `php artisan scribe:generate` to create initial documentation
2. Review and customize the generated documentation as needed
3. Configure authentication in `config/scribe.php` if your APIs require it
4. Add annotations to your controllers and methods to enrich the documentation
5. Consider setting up automatic documentation generation in your CI/CD pipeline

## Troubleshooting
If you encounter any issues:
- Check that your routes are properly documented with DocBlock annotations
- Ensure your API returns consistent response structures
- Verify that your authentication guards are properly configured in `config/scribe.php`

