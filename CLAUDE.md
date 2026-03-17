# Claude Guidance for Drupal Projects

## Project Overview
- **Site**: Taranto Viva (DDEV project: `taranto-viva`)
- **Drupal version**: 10 (PHP 8.3)
- **Web root**: `web/`
- **Config sync directory**: `config/sync`
- **Custom modules**: `web/modules/custom/`
- **DDEV**: All `drush` and `composer` commands must be prefixed with `ddev` (e.g. `ddev drush`, `ddev composer`)

## Custom Modules
- `leaflet_override`: Overrides the Leaflet module — responsive popups with Google Maps links for points and geometries
- `react_maplibre_map`: MapLibre GL JS map as a React/Vite app, embedded in Drupal as a Block
- `views_geojson`: Views feed style plugin for GeoJSON output
- `leaflet_choropleth`: Choropleth map support for Leaflet
- `geofield_311`: Geofield 311 Drupal functionalities
- `geocsm`: GeoCMS integration
- `italo_module`: General custom module for site-specific Drupal functionalities
- `popup_message`: Popup message for users
- `media_library_importer`: Imports media files from a directory into the media library
- `media_entity_image_exif`: Exposes EXIF data from images in Media entities

## Build/Lint/Test Commands
- **Build**: `ddev composer install`
- **Install**: `ddev drush site:install --existing-config`
- **Lint**: `phpcs` (config at `/phpcs.xml` or `/phpcs.xml.dist`; if absent: `phpcs --standard=Drupal path/to/test`)
- **Static Analysis**: `phpstan` (config at `/phpstan.neon`)
- **Run Single Test**: `phpunit --filter Test path/to/test` (config at `/phpunit.xml` or `/phpunit.xml.dist`; if absent: `phpunit -c web/core/phpunit.xml.dist --filter Test path/to/test`)
- If phpcs/phpstan/phpunit are not available: `ddev composer require --dev drupal/core-dev`

## Frontend Build (React/Vite)
Both React apps use Vite and TypeScript. Run from within each directory (no `ddev` needed):
- **MapLibre map**: `cd web/modules/custom/react_maplibre_map/react_app && npm install && npm run build`
- **Typesense search**: `cd typesense_instantseacrh_react && npm install && npm run build`
- Dev server: replace `build` with `dev` for HMR during development

## Configuration Management
- **Export configuration**: `ddev drush config:export -y`
- **Import configuration**: `ddev drush config:import -y`
- **Import partial configuration**: `ddev drush config:import --partial --source=path-to-module/config/install`
- **Verify configuration**: `ddev drush config:export --diff`
- **View config details**: `ddev drush config:get [config.name]`
- **Change config value**: `ddev drush config:set [config.name] [key] [value]`

## Development Commands
- **List available modules**: `ddev drush pm:list [--filter=FILTER]`
- **List enabled modules**: `ddev drush pm:list --status=enabled [--filter=FILTER]`
- **Download a Drupal module**: `ddev composer require drupal/[module_name]`
- **Install a Drupal module**: `ddev drush en [module_name]`
- **Clear cache**: `ddev drush cache:rebuild`
- **Inspect logs**: `ddev drush watchdog:show --count=20`
- **Delete logs**: `ddev drush watchdog:delete all`
- **Run cron**: `ddev drush cron`
- **Show status**: `ddev drush status`
- **View fields on entity**: `ddev drush field:info [entity_type] [bundle]`

## Best Practices
- Always export configuration after making changes: `ddev drush config:export -y`
- If making configuration changes to a module's `config/install`, apply them to active configuration too
- Use `config/install` (not `hook_install`) for module-provided install configuration
- Prefer contrib modules over replicating functionality in custom modules
- Check configuration diffs before importing: `ddev drush config:export --diff`

## Constraints
- Never run destructive drush commands (`drush sql:drop`, `drush site:install`) without explicit user confirmation
- Don't commit compiled JS/CSS assets (React build output)
- Config sync directory is `config/sync` — always export after config changes

## Code Style Guidelines
- **PHP Version**: 8.3+ compatibility required
- **Coding Standard**: Drupal coding standards
- **Indentation**: 2 spaces, no tabs
- **Line Length**: 120 characters maximum
- **Comment**: 80 characters maximum line length, always finishing with a full stop
- **Namespaces**: PSR-4 standard, `Drupal\{module_name}`
- **Types**: Strict typing with PHP 8 features, union types when needed
- **Documentation**: Required for classes and methods with PHPDoc
- **Class Structure**: Properties before methods, dependency injection via constructor
- **Naming**: CamelCase for classes/methods/properties, snake_case for variables, ALL_CAPS for constants
- **Error Handling**: Specific exception types with `@throws` annotations, meaningful messages
- **Plugins**: Follow Drupal plugin conventions with attributes for definition

When working in this codebase, prioritize adherence to Drupal patterns and conventions.
