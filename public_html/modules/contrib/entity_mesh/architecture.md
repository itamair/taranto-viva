# Entity Mesh Module - Architecture Documentation

## 1. Module Overview

### Purpose
The Entity Mesh module provides a comprehensive link tracking and analysis system for Drupal entities. It creates a mesh of relationships between source entities (primarily nodes) and their target links, enabling site administrators to track, analyze, and report on all internal and external links within their content.

### Core Functionality
- **Link Analysis**: Renders entities and extracts all links from their HTML output
- **Relationship Tracking**: Maintains a database mesh of source-to-target link relationships
- **Broken Link Detection**: Identifies and reports broken, redirected, and access-denied links
- **Asynchronous Processing**: Supports both synchronous and asynchronous processing modes for scalability
- **Multi-language Support**: Handles multilingual content with language-aware link tracking
- **Reporting**: Provides Views-based reports with D3.js visualizations for link analysis

### Key Features
- Automatic link extraction from rendered entity content
- Support for internal (nodes, views, media) and external links
- Configurable processing modes (synchronous/asynchronous)
- Tracker queue system for batch processing
- Cron integration for background processing
- Access control based on configured analyzer account
- Views integration with custom filters and fields
- D3.js visualization for link relationship graphs
- CSV export capability for reports

### Version Compatibility
- **Drupal Core**: ^10 || ^11
- **Dependencies**:
  - drupal:node
  - drupal:views
  - views_data_export:views_data_export
  - drupal:language
  - drupal:media

## 2. Directory Structure

```
entity_mesh/
├── config/
│   ├── install/
│   │   └── entity_mesh.settings.yml
│   ├── optional/
│   │   ├── views.view.entity_mesh.yml
│   │   ├── views.view.entity_mesh_domains.yml
│   │   └── views.view.entity_mesh_node.yml
│   └── schema/
│       ├── entity_mesh.schema.yml
│       └── entity_mesh.views.schema.yml
├── css/
│   ├── batch-form.css
│   └── entity-mesh.css
├── js/
│   └── entity-mesh.js
├── src/
│   ├── Batches/
│   │   ├── EntityTrackerBatch.php
│   │   └── NodeBatch.php
│   ├── Commands/
│   │   └── EntityMeshCommands.php
│   ├── Form/
│   │   ├── BatchForm.php
│   │   ├── CronForm.php
│   │   └── SettingsForm.php
│   ├── Language/
│   │   ├── LanguageNegotiatorSwitcher.php
│   │   └── StaticLanguageNegotiator.php
│   ├── Plugin/
│   │   ├── QueueWorker/
│   │   │   └── EntityMeshQueueWorker.php
│   │   └── views/
│   │       ├── field/
│   │       │   ├── BaseLinkSource.php
│   │       │   ├── FilteredViewLink.php
│   │       │   ├── LinkSource.php
│   │       │   └── LinkTarget.php
│   │       ├── filter/
│   │       │   ├── BaseSelectFilter.php
│   │       │   ├── CategoryFilter.php
│   │       │   ├── SourceBundleFilter.php
│   │       │   ├── SourceLangcodeFilter.php
│   │       │   ├── SubcategoryFilter.php
│   │       │   ├── TargetBundleFilter.php
│   │       │   ├── TargetEntityTypeFilter.php
│   │       │   ├── TargetHrefFilter.php
│   │       │   ├── TargetLangcodeFilter.php
│   │       │   ├── TargetSchemaFilter.php
│   │       │   └── TargeTypeLinkFilter.php
│   │       └── style/
│   │           └── EntityMeshD3Style.php
│   ├── DummyAccount.php
│   ├── DummyAccountInterface.php
│   ├── Entity.php
│   ├── EntityRender.php
│   ├── HelperTrait.php
│   ├── Menu.php
│   ├── ProcessDataForD3Trait.php
│   ├── Repository.php
│   ├── RepositoryInterface.php
│   ├── Source.php
│   ├── SourceInterface.php
│   ├── Target.php
│   ├── TargetInterface.php
│   ├── ThemeSwitcher.php
│   ├── ThemeSwitcherInterface.php
│   ├── Tracker.php
│   ├── TrackerInterface.php
│   ├── TrackerManager.php
│   └── TrackerManagerInterface.php
├── tests/
│   └── src/
│       ├── Functional/
│       ├── FunctionalJavascript/
│       ├── Kernel/
│       └── Unit/
├── drush.services.yml
├── entity_mesh.info.yml
├── entity_mesh.install
├── entity_mesh.libraries.yml
├── entity_mesh.links.menu.yml
├── entity_mesh.links.task.yml
├── entity_mesh.module
├── entity_mesh.permissions.yml
├── entity_mesh.routing.yml
└── entity_mesh.services.yml
```

## 3. Service Architecture

### Service Dependency Tree
```
entity_mesh.entity_render
├── entity_mesh.repository
│   ├── @database
│   ├── @entity_mesh.logger
│   ├── @request_stack
│   ├── @entity_type.manager
│   ├── @entity_field.manager
│   └── @config.factory
├── @entity_type.manager
├── @language_manager
├── @config.factory
├── @renderer
├── @account_switcher
├── entity_mesh.language_negotiator_switcher
│   ├── @language_manager
│   ├── @module_handler
│   ├── @string_translation
│   └── entity_mesh.static_language_negotiator
├── @module_handler
├── @access_manager
├── entity_mesh.theme_switcher
│   ├── @theme.manager
│   ├── @theme.initialization
│   └── @config.factory
└── entity_mesh.tracker_manager
    └── entity_mesh.tracker
        ├── @database
        └── @datetime.time
```

### Core Services

#### entity_mesh.repository
- **Class**: `Drupal\entity_mesh\Repository`
- **Purpose**: Handles all database operations for the entity mesh table
- **Key Methods**:
  - `insertSource()`: Insert source with targets
  - `deleteSource()`: Remove source and its targets
  - `saveSource()`: Save/update source data
  - `getMeshAccount()`: Get configured analyzer account
  - `checkViewAccessEntity()`: Check entity access permissions

#### entity_mesh.entity_render
- **Class**: `Drupal\entity_mesh\EntityRender`
- **Purpose**: Renders entities and extracts links from HTML output
- **Key Methods**:
  - `processEntity()`: Main processing method for entities
  - `countEntityLinks()`: Count links in an entity
  - `createSourceFromEntity()`: Create source object from entity
  - `setTargetsInSourceFromEntityRender()`: Extract links from rendered HTML

#### entity_mesh.tracker_manager
- **Class**: `Drupal\entity_mesh\TrackerManager`
- **Purpose**: Manages entity tracking for asynchronous processing
- **Key Methods**:
  - `addTrackedEntity()`: Add entity for processing
  - `deleteTrackedEntity()`: Add entity for deletion
  - `addTrackedEntities()`: Batch add entities

#### entity_mesh.tracker
- **Class**: `Drupal\entity_mesh\Tracker`
- **Purpose**: Low-level tracker database operations
- **Key Methods**:
  - `addEntity()`: Add entity to tracker
  - `getPendingEntities()`: Get entities for processing
  - `markAsProcessed()`: Update status to processed
  - `markAsFailed()`: Update status to failed

### Service Categories

#### Data Layer Services
- `entity_mesh.repository`: Database operations for mesh data
- `entity_mesh.tracker`: Database operations for tracker queue

#### Processing Services
- `entity_mesh.entity_render`: Entity rendering and link extraction
- `entity_mesh.menu`: Menu link processing

#### Support Services
- `entity_mesh.logger`: Logging service
- `entity_mesh.language_negotiator_switcher`: Language switching
- `entity_mesh.static_language_negotiator`: Static language negotiation
- `entity_mesh.theme_switcher`: Theme switching for rendering

## 4. Plugin System

### Plugin Discovery
The module uses Drupal's annotation-based plugin discovery for:
- Queue Workers
- Views Plugins (fields, filters, styles)

### Plugin Management
Plugins are managed through standard Drupal plugin managers:
- Queue Worker plugins via `@plugin.manager.queue_worker`
- Views plugins via Views plugin system

### Plugin Types

#### Queue Worker Plugin
- **ID**: `entity_mesh_queue_worker`
- **Class**: `EntityMeshQueueWorker`
- **Purpose**: Processes queued entities (deprecated, moving to tracker system)

#### Views Field Plugins
- `BaseLinkSource`: Base class for link source fields
- `LinkSource`: Display source entity information
- `LinkTarget`: Display target link information
- `FilteredViewLink`: Display filtered view links

#### Views Filter Plugins
- `BaseSelectFilter`: Base class for select filters
- `CategoryFilter`: Filter by link category
- `SubcategoryFilter`: Filter by subcategory
- `SourceBundleFilter`: Filter by source bundle
- `SourceLangcodeFilter`: Filter by source language
- `TargetBundleFilter`: Filter by target bundle
- `TargetEntityTypeFilter`: Filter by target entity type
- `TargetHrefFilter`: Filter by target URL
- `TargetLangcodeFilter`: Filter by target language
- `TargetSchemaFilter`: Filter by URL scheme
- `TargeTypeLinkFilter`: Filter by link type

#### Views Style Plugin
- **ID**: `entity_mesh_d3_style`
- **Class**: `EntityMeshD3Style`
- **Purpose**: Render Views results as D3.js visualization

### Plugin Implementations
All plugins follow Drupal's plugin annotation pattern and implement appropriate interfaces from Views or Core.

## 5. Routing and Controllers

### Static Routes

#### Administrative Routes
- **entity_mesh.batch_form** (`/admin/config/system/entity-mesh`)
  - Form: `Drupal\entity_mesh\Form\BatchForm`
  - Purpose: Batch processing interface
  - Permission: `administer entity_mesh configuration`

- **entity_mesh.settings_form** (`/admin/config/system/entity-mesh/config`)
  - Form: `Drupal\entity_mesh\Form\SettingsForm`
  - Purpose: Module configuration
  - Permission: `administer entity_mesh configuration`

- **entity_mesh.cron_form** (`/admin/config/system/entity-mesh/cron`)
  - Form: `Drupal\entity_mesh\Form\CronForm`
  - Purpose: Cron settings management
  - Permission: `administer entity_mesh configuration`

### Dynamic Routes
The module doesn't implement dynamic routes but integrates with Views for report pages.

### Controller Descriptions
The module uses Form controllers exclusively:

#### BatchForm
- Provides interface for batch processing entities
- Allows selection of entity types and bundles
- Triggers batch API operations

#### SettingsForm
- Manages module configuration
- Source types configuration (entities to track)
- Target types configuration (link types to track)
- Processing mode settings (synchronous/asynchronous)
- Analyzer account configuration

#### CronForm
- Controls cron processing settings
- Enable/disable cron processing
- Set processing limits

## 6. Security Considerations

### Access Control

#### Permission System
- **administer entity_mesh configuration**: Full administrative access
- **access entity_mesh report**: View reports and analytics

#### Analyzer Account
The module implements a sophisticated analyzer account system:
- Configurable account type (anonymous, specific user, roles)
- Used for entity access checks during rendering
- DummyAccount implementation for role-based access

### Input Validation

#### Form Validation
- All forms extend `ConfigFormBase` with built-in CSRF protection
- Bundle and entity type validation against existing types
- Numeric validation for limits and counts

#### Database Input
- Prepared statements for all database queries
- Parameter binding for dynamic values
- Hash IDs for entity identification

### Output Sanitization

#### HTML Rendering
- Uses Drupal's renderer service for safe output
- DOM parsing for link extraction (no regex on HTML)
- Proper encoding of URLs and text

#### Views Integration
- Leverages Views' built-in sanitization
- Custom field plugins use proper escaping

### CSRF Protection
- All forms use Drupal's form API with built-in CSRF tokens
- State-changing operations require proper permissions

## 7. Performance Optimization

### Database Queries

#### Indexing Strategy
```sql
-- entity_mesh table indexes
PRIMARY KEY (id)
INDEX source_entity (source_entity_id, source_entity_type, source_entity_langcode)

-- entity_mesh_tracker table indexes
PRIMARY KEY (id)
INDEX entity_lookup (entity_type, entity_id)
INDEX status (status)
INDEX timestamp (timestamp)
UNIQUE KEY entity_unique (entity_type, entity_id)
```

#### Query Optimization
- Batch inserts for multiple targets
- Conditional loading based on configuration
- Efficient delete operations by source

### Caching Strategy

#### DOM Cache
- In-memory caching of rendered entity DOM
- Prevents redundant rendering in same request
- Automatic cleanup after processing

#### Configuration Cache
- Static caching of configuration values
- Mesh account caching with explicit invalidation

### Resource Loading

#### Asynchronous Processing
- Tracker queue for large operations
- Configurable synchronous limit (default 25 links)
- Cron-based background processing

#### Batch Operations
- Batch API integration for bulk processing
- Configurable batch sizes
- Progress tracking

## 8. Testing Strategy

### Test Coverage

#### Unit Tests
- `EntityRenderTest`: Entity rendering logic
- `RepositoryTest`: Repository operations
- `TrackerManagerTest`: Tracker management

#### Kernel Tests
- `EntityMeshTestBase`: Base test class
- `EntityMeshTestBasic`: Basic functionality
- `EntityMeshDiacriticalMarksTest`: Special character handling
- `EntityMeshEntityRenderTest`: Rendering tests
- `EntityMeshEntityRenderMultilingualTest`: Multilingual support
- `EntityMeshPermissionsTest`: Access control
- `EntityMeshProcessingModeTest`: Processing modes
- `EntityMeshSourceTypesTest`: Source type handling
- `EntityMeshViewsTest`: Views integration
- `EntityMeshWebformAccessTest`: Webform integration
- `TrackerTest`: Tracker functionality

#### Functional Tests
- `EntityMeshSettingsFormTest`: Configuration form

#### Functional JavaScript Tests
- `SourceTypeConfigurationTest`: Dynamic configuration UI

### Test Structure
```
tests/
├── src/
│   ├── Unit/           # Isolated unit tests
│   ├── Kernel/         # Integration tests with database
│   │   └── Traits/     # Reusable test traits
│   ├── Functional/     # Browser-based tests
│   └── FunctionalJavascript/  # JavaScript interaction tests
```

## 9. Key Design Patterns

### Repository Pattern
**Implementation**: `Repository` and `RepositoryInterface`
- Centralizes database operations
- Abstracts data access layer
- Provides consistent API for mesh operations

### Factory Pattern
**Implementation**: Service instantiation
- `instanceEmptySource()`: Creates Source objects
- `instanceEmptyTarget()`: Creates Target objects
- Logger factory for service-specific loggers

### Strategy Pattern
**Implementation**: Processing modes
- Synchronous processing strategy
- Asynchronous processing strategy
- Configurable switching between strategies

### Decorator Pattern
**Implementation**: `DummyAccount`
- Wraps user account functionality
- Adds role-based access simulation
- Transparent interface implementation

### Observer Pattern
**Implementation**: Entity hooks
- `hook_entity_insert()`
- `hook_entity_update()`
- `hook_entity_delete()`
- Observes entity lifecycle events

### Chain of Responsibility
**Implementation**: Link processing pipeline
1. Entity rendering
2. DOM parsing
3. Link extraction
4. Target identification
5. Database storage

### Trait Pattern
**Implementation**:
- `HelperTrait`: Shared utility methods
- `ProcessDataForD3Trait`: D3.js data processing
- `EntityMeshTestTrait`: Test utilities

### Service Locator Pattern
**Implementation**: Dependency injection container
- All services defined in `entity_mesh.services.yml`
- Automatic dependency resolution
- Lazy loading of services

### Command Pattern
**Implementation**: Drush commands
- `EntityMeshCommands`: Encapsulates CLI operations
- Separate command logic from execution

### Template Method Pattern
**Implementation**: Form classes
- `SettingsForm`, `BatchForm`, `CronForm`
- Inherit from `ConfigFormBase`
- Override specific methods while using base flow

## 10. Event System

### Entity Lifecycle Integration

The Entity Mesh module observes Drupal's entity lifecycle through hooks rather than the event system:

**Entity Hooks Implemented:**
- `hook_entity_insert()` - Triggers link analysis when entities are created
- `hook_entity_update()` - Re-analyzes links when entities are updated
- `hook_entity_delete()` - Removes mesh data when entities are deleted

**Processing Flow:**
1. Entity operation occurs (insert/update/delete)
2. Hook invokes `entity_mesh_process_entity()`
3. System checks if entity should be processed via `entity_mesh_is_render_entity()`
4. Eligible entities (Node, Media, Taxonomy) are processed
5. Processing mode determined (synchronous vs asynchronous)
6. Links extracted and stored in entity_mesh table

### Entity Processing Hook

**Implementation**: `entity_mesh.module:18-33`

The module uses a unified processing approach:
- Insert and update operations both call `entity_mesh_process_entity($entity, 'process')`
- Delete operations call `entity_mesh_process_entity($entity, 'delete')`
- Processing respects configuration for sync/async modes

### Form Alteration Hooks

**`hook_form_alter()`** - Implements warnings for entities with dependencies:
- Alters entity delete forms
- Displays warning messages when entity has incoming links
- Shows count and list of entities linking to the target

### Future Considerations

**Migration to Event System (Drupal 10+)**
- Current hook-based approach is functional but could benefit from event system
- Potential events to consider:
  - `EntityMeshProcessEvent` - Triggered before/after processing
  - `EntityMeshLinkExtractedEvent` - Triggered when links are found
  - `EntityMeshTargetResolvedEvent` - Triggered when targets are resolved
- Would improve extensibility and testing capabilities

### Available Alter Hooks

While the module doesn't currently provide alter hooks, these would be valuable additions:
- `hook_entity_mesh_source_alter()` - Modify source entity before processing
- `hook_entity_mesh_targets_alter()` - Modify extracted targets
- `hook_entity_mesh_link_alter()` - Modify individual link data before storage

## 11. API and Integration Points

### Public API

The Entity Mesh module provides several services for programmatic interaction:

#### Entity Rendering Service

**Service**: `entity_mesh.entity_render`
**Class**: `Drupal\entity_mesh\EntityRender`
**File**: `src/EntityRender.php`

**Primary Methods:**
```php
// Process an entity and extract links
$entity_render = \Drupal::service('entity_mesh.entity_render');
$entity_render->processEntity($entity, $operation = 'process');

// Count entity links
$count = $entity_render->countEntityLinks($entity);

// Get render array for entity
$render_array = $entity_render->renderEntity($entity);
```

#### Repository Service

**Service**: `entity_mesh.repository`
**Class**: `Drupal\entity_mesh\Repository`
**File**: `src/Repository.php`

**Query Methods:**
```php
$repository = \Drupal::service('entity_mesh.repository');

// Get all links from a source entity
$source_links = $repository->getEntityMeshBySource($entity_type, $entity_id, $langcode);

// Get all links to a target entity
$target_links = $repository->getEntityMeshByTarget($entity_type, $entity_id, $langcode);

// Get broken links
$broken = $repository->getBrokenLinks();

// Clear mesh data for entity
$repository->clearMeshBySource($entity_type, $entity_id, $langcode);
```

#### Tracker Manager Service

**Service**: `entity_mesh.tracker_manager`
**Class**: `Drupal\entity_mesh\TrackerManager`
**File**: `src/TrackerManager.php`

**Queue Management:**
```php
$tracker_manager = \Drupal::service('entity_mesh.tracker_manager');

// Add entity to processing queue
$tracker_manager->addTrackedEntity($entity);

// Process queued items (batch or cron)
$tracker_manager->processQueue($limit = 50);

// Get queue statistics
$stats = $tracker_manager->getQueueStats();
```

### Hooks Implemented

#### hook_entity_insert()
**File**: `entity_mesh.module:18-20`
**Purpose**: Processes entities when created
**Implementation**: Calls `entity_mesh_process_entity($entity, 'process')`

#### hook_entity_update()
**File**: `entity_mesh.module:25-27`
**Purpose**: Re-processes entities when updated
**Implementation**: Calls `entity_mesh_process_entity($entity, 'process')`

#### hook_entity_delete()
**File**: `entity_mesh.module:32-34`
**Purpose**: Cleans up mesh data when entities deleted
**Implementation**: Calls `entity_mesh_process_entity($entity, 'delete')`

#### hook_form_alter()
**File**: `entity_mesh.module:139-184`
**Purpose**: Adds dependency warnings to entity delete forms
**Implementation**: Checks for incoming links and displays warnings

#### hook_views_data()
**File**: `entity_mesh.views.inc:11-643`
**Purpose**: Exposes entity_mesh table to Views
**Implementation**: Defines fields, filters, relationships for Views integration

### Hooks Provided

Currently the module does not provide alter hooks for third-party extensions. Recommended additions:

```php
// Proposed hooks for future versions
hook_entity_mesh_source_alter(&$entity, $context);
hook_entity_mesh_targets_alter(&$targets, $entity);
hook_entity_mesh_link_alter(&$link_data, $entity, $target);
```

### Plugin System for Extension

The module provides several plugin types for extension:

#### Views Field Plugins
Extend link display capabilities:
- Base class: `Drupal\entity_mesh\Plugin\views\field\BaseLinkSource`
- Example: `LinkSource.php`, `LinkTarget.php`

#### Views Filter Plugins
Add custom filtering:
- Base class: `Drupal\entity_mesh\Plugin\views\filter\BaseSelectFilter`
- Example: `CategoryFilter.php`, `TargetEntityTypeFilter.php`

### Integration with Core Systems

#### Entity System
- Observes all content entity operations (Node, Media, Taxonomy)
- Stores relationships in custom `entity_mesh` table
- Maintains referential integrity through hooks

#### Configuration System
- Settings: `entity_mesh.settings`
- Configurable source/target entity types
- Processing mode configuration (sync/async)
- Analyzer account configuration

#### Render System
- Uses full render pipeline to extract links
- Renders entities with DummyAccount for access bypass
- Processes rendered HTML with DOM parser
- Extracts all link types (internal, external, anchors)

#### Views System
- Provides 14 custom Views plugins
- Exposes entity_mesh data to Views UI
- Creates dynamic displays per entity type
- Implements custom relationship handlers

#### Queue System
- Integrates with Drupal Queue API
- Creates `entity_mesh_tracker` queue
- Processes items via cron or batch operations
- Configurable queue processing limits
