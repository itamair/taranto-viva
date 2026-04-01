<?php

/**
 * @file
 * Include to manage integration with Views.
 */

/**
 * Implements hook_views_data().
 */
function entity_mesh_views_data() {

  // Table properties.
  $data['entity_mesh']['table']['group'] = t('Entity Mesh');

  $data['entity_mesh']['table']['base'] = [
    'field' => 'nid',
    'title' => t('Entity Mesh table'),
    'help' => t('Table for store entity mesh items.'),
    'weight' => -10,
  ];

  /*
  $data['entity_mesh']['table']['join'] = [
  'node_field_data' => [
  'left_field' => 'nid',
  'field' => 'nid',
  ],
  ];*/

  // Table fields.
  /*
  $data['entity_mesh']['nid'] = [
  'title' => t('Nid'),
  'help' => t('The node ID.'),
  'relationship' => [
  'label' => t('node'),
  'base' => 'node_field_data',
  'id' => 'standard',
  ],
  ];*/

  $data['entity_mesh']['type'] = [
    'title' => t('Type'),
    'help' => t('Type field.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];

  $data['entity_mesh']['category'] = [
    'title' => t('Category'),
    'help' => t('Category field.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'category_filter',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];

  $data['entity_mesh']['subcategory'] = [
    'title' => t('Sub Category'),
    'help' => t('Sub Category field.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'subcategory_filter',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];

  $data['entity_mesh']['source_hash_id'] = [
    'title' => t('Source hash ID'),
    'help' => t('Source hash ID field.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];

  $data['entity_mesh']['source_entity_type'] = [
    'title' => t('Entity Type (Source)'),
    'help' => t('Entity Type (Source) field.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];

  $data['entity_mesh']['source_entity_bundle'] = [
    'title' => t('Entity Bundle (Source)'),
    'help' => t('Entity Bundle (Source) field.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'source_bundle_filter',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];

  $data['entity_mesh']['source_entity_id'] = [
    'title' => t('Entity ID (Source)'),
    'help' => t('Entity ID (Source) field.'),
    'field' => [
      'id' => 'entity_mesh_link_source',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];

  $data['entity_mesh']['source_entity_langcode'] = [
    'title' => t('Entity Langcode (Source)'),
    'help' => t('Entity Langcode (Source) field.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'source_langcode_filter',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];

  $data['entity_mesh']['source_title'] = [
    'title' => t('Entity Title (Source)'),
    'help' => t('Entity Title (Source) field.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];

  $data['entity_mesh']['target_href'] = [
    'title' => t('Href (Target)'),
    'help' => t('Target Href field.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];

  $data['entity_mesh']['target_path'] = [
    'title' => t('Path (Target)'),
    'help' => t('Target Path field.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];

  $data['entity_mesh']['target_scheme'] = [
    'title' => t('Scheme (Target)'),
    'help' => t('Target Scheme field.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'target_schema_filter',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];

  $data['entity_mesh']['target_host'] = [
    'title' => t('Host (Target)'),
    'help' => t('Target Host field.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];

  $data['entity_mesh']['target_link_type'] = [
    'title' => t('Type (Target)'),
    'help' => t('Type (Target).'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'target_type_link_filter',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];

  $data['entity_mesh']['target_hash_id'] = [
    'title' => t('Target hash ID'),
    'help' => t('Target hash ID field.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];

  $data['entity_mesh']['target_entity_type'] = [
    'title' => t('Entity Type (Target)'),
    'help' => t('Entity Type (Target) field.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'target_entity_type_filter',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];

  $data['entity_mesh']['target_entity_bundle'] = [
    'title' => t('Entity Bundle (Target)'),
    'help' => t('Entity Bundle (Target) field.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'target_bundle_filter',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];

  $data['entity_mesh']['target_entity_id'] = [
    'title' => t('Entity ID (Target)'),
    'help' => t('Entity ID (Target) field.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];

  $data['entity_mesh']['entity_link'] = [
    'title' => t('Entity link'),
    'help' => t('Entity link field.'),
    'real field' => 'target_entity_id',
    'field' => [
      'id' => 'entity_mesh_link_target',
      'click sortable' => FALSE,
    ],
  ];

  $data['entity_mesh']['target_entity_langcode'] = [
    'title' => t('Entity Langcode (Target)'),
    'help' => t('Entity Langcode (Target) field.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'target_langcode_filter',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];

  $data['entity_mesh']['target_title'] = [
    'title' => t('Entity Title (Target)'),
    'help' => t('Entity Title (Target) field.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];

  $data['entity_mesh']['id'] = [
    'title' => t('Count'),
    'help' => t('Count of entity mesh records.'),
    'field' => [
      'id' => 'numeric',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'argument' => [
      'id' => 'numeric',
    ],
  ];

  $data['entity_mesh']['filtered_view_link'] = [
    'title' => t('Filtered View Link'),
    'help' => t('Link to the entity mesh view filtered by category and target host.'),
    'real field' => 'target_host',
    'field' => [
      'id' => 'entity_mesh_filtered_view_link',
      'click sortable' => FALSE,
    ],
  ];

  // RELATIONSHIPS:
  // @todo make this dynamic, see entity_usage as example!
  $types = [
    'target' => 'Target',
    'source' => 'Source',
  ];

  foreach ($types as $key => $value) {
    // Node.
    $data['entity_mesh'][$key . '_node'] = [
      'title' => t('Nid'),
      'help' => t('The node ID.'),
      'relationship' => [
        'base' => 'node_field_data',
        'base field' => 'nid',
        'field' => $key . '_entity_id',
        'id' => 'standard',
        'label' => t('Entity Mesh @key', ['@key' => $value]),
        'title' => t('Entity Mesh @key', ['@key' => $value]),
        'help' => t('Relate content to the Entity Mesh table.'),
        'extra' => [
          [
            'left_field' => $key . '_entity_type',
            'value' => 'node',
          ],
          [
            'field' => 'langcode',
            'left_field' => $key . '_entity_langcode',
          ],
        ],
      ],
    ];

    // Media.
    $data['entity_mesh'][$key . '_media'] = [
      'title' => t('Mid'),
      'help' => t('The media ID.'),
      'relationship' => [
        'base' => 'media_field_data',
        'base field' => 'mid',
        'field' => $key . '_entity_id',
        'id' => 'standard',
        'label' => t('Entity Mesh @key (Media)', ['@key' => $value]),
        'title' => t('Entity Mesh @key (Media)', ['@key' => $value]),
        'help' => t('Relate media to the Entity Mesh table.'),
        'extra' => [
          [
            'left_field' => $key . '_entity_type',
            'value' => 'media',
          ],
          [
            'field' => 'langcode',
            'left_field' => $key . '_entity_langcode',
          ],
        ],
      ],
    ];

    // Taxonomy.
    $data['entity_mesh'][$key . '_taxonomy'] = [
      'title' => t('Tid'),
      'help' => t('The taxonomy term ID.'),
      'relationship' => [
        'base' => 'taxonomy_term_field_data',
        'base field' => 'tid',
        'field' => $key . '_entity_id',
        'id' => 'standard',
        'label' => t('Entity Mesh @key (Taxonomy)', ['@key' => $value]),
        'title' => t('Entity Mesh @key (Taxonomy)', ['@key' => $value]),
        'help' => t('Relate taxonomy terms to the Entity Mesh table.'),
        'extra' => [
          [
            'left_field' => $key . '_entity_type',
            'value' => 'taxonomy_term',
          ],
          [
            'field' => 'langcode',
            'left_field' => $key . '_entity_langcode',
          ],
        ],
      ],
    ];
  }

  // Node integration.
  $data['node']['entity_mesh_source'] = [
    'title' => t('Nid'),
    'help' => t('The node ID.'),
    'relationship' => [
      'base' => 'entity_mesh',
      'base field' => 'source_entity_id',
      'field' => 'nid',
      'id' => 'standard',
      'label' => t('Entity Mesh Source'),
      'title' => t('Entity Mesh Source'),
      'help' => t('Relate content to the Entity Mesh table.'),
    ],
  ];

  $data['node']['entity_mesh_target'] = [
    'title' => t('Nid'),
    'help' => t('The node ID.'),
    'relationship' => [
      'base' => 'entity_mesh',
      'base field' => 'source_target_id',
      'field' => 'nid',
      'id' => 'standard',
      'label' => t('Entity Mesh Source'),
      'title' => t('Entity Mesh Source'),
      'help' => t('Relate content to the Entity Mesh table.'),
    ],
  ];

  // Media integration.
  $data['media']['entity_mesh_source'] = [
    'title' => t('Mid'),
    'help' => t('The media ID.'),
    'relationship' => [
      'base' => 'entity_mesh',
      'base field' => 'source_entity_id',
      'field' => 'mid',
      'id' => 'standard',
      'label' => t('Entity Mesh Source (Media)'),
      'title' => t('Entity Mesh Source (Media)'),
      'help' => t('Relate media to the Entity Mesh table.'),
      'extra' => [
        [
          'left_field' => 'source_entity_type',
          'value' => 'media',
        ],
        [
          'field' => 'langcode',
          'left_field' => 'source_entity_langcode',
        ],
      ],
    ],
  ];

  $data['media']['entity_mesh_target'] = [
    'title' => t('Mid'),
    'help' => t('The media ID.'),
    'relationship' => [
      'base' => 'entity_mesh',
      'base field' => 'target_entity_id',
      'field' => 'mid',
      'id' => 'standard',
      'label' => t('Entity Mesh Target (Media)'),
      'title' => t('Entity Mesh Target (Media)'),
      'help' => t('Relate media to the Entity Mesh table.'),
      'extra' => [
        [
          'left_field' => 'target_entity_type',
          'value' => 'media',
        ],
        [
          'field' => 'langcode',
          'left_field' => 'target_entity_langcode',
        ],
      ],
    ],
  ];

  // Taxonomy integration.
  $data['taxonomy_term_data']['entity_mesh_source'] = [
    'title' => t('Tid'),
    'help' => t('The taxonomy term ID.'),
    'relationship' => [
      'base' => 'entity_mesh',
      'base field' => 'source_entity_id',
      'field' => 'tid',
      'id' => 'standard',
      'label' => t('Entity Mesh Source (Taxonomy)'),
      'title' => t('Entity Mesh Source (Taxonomy)'),
      'help' => t('Relate taxonomy terms to the Entity Mesh table.'),
      'extra' => [
        [
          'left_field' => 'source_entity_type',
          'value' => 'taxonomy_term',
        ],
        [
          'field' => 'langcode',
          'left_field' => 'source_entity_langcode',
        ],
      ],
    ],
  ];

  $data['taxonomy_term_data']['entity_mesh_target'] = [
    'title' => t('Tid'),
    'help' => t('The taxonomy term ID.'),
    'relationship' => [
      'base' => 'entity_mesh',
      'base field' => 'target_entity_id',
      'field' => 'tid',
      'id' => 'standard',
      'label' => t('Entity Mesh Target (Taxonomy)'),
      'title' => t('Entity Mesh Target (Taxonomy)'),
      'help' => t('Relate taxonomy terms to the Entity Mesh table.'),
      'extra' => [
        [
          'left_field' => 'target_entity_type',
          'value' => 'taxonomy_term',
        ],
        [
          'field' => 'langcode',
          'left_field' => 'target_entity_langcode',
        ],
      ],
    ],
  ];

  return $data;
}
