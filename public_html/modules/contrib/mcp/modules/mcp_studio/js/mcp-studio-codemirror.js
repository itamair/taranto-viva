/**
 * @file
 * MCP Studio CodeMirror editor integration.
 */

(function ($, Drupal, once, CodeMirror) {
  'use strict';

  // Make jsonlint available globally for CodeMirror if it exists
  if (typeof jsonlint !== 'undefined') {
    window.jsonlint = jsonlint;
  }

  /**
   * Behavior for MCP Studio CodeMirror editor.
   */
  Drupal.behaviors.mcpStudioCodeMirror = {
    attach: function (context, settings) {
      // Select output editors (codemirror-editor but NOT json-schema-editor)
      const outputElements = once('mcp-studio-codemirror', 'textarea.codemirror-editor:not(.json-schema-editor)', context);
      // Select JSON schema editors specifically
      const schemaElements = once('mcp-studio-json-schema', 'textarea.json-schema-editor', context);

      outputElements.forEach(function (element) {
        const $textarea = $(element);
        // Find the output wrapper more reliably
        let $container = $textarea.closest('.output-field-group');
        if (!$container.length) {
          $container = $textarea.closest('[class*="output-wrapper"]');
        }
        if (!$container.length) {
          $container = $textarea.parent();
        }

        // Find mode selector
        let $modeSelect = $container.find('.output-mode-selector');
        if (!$modeSelect.length) {
          $modeSelect = $textarea.closest('form').find('.output-mode-selector');
        }

        // Initialize CodeMirror
        const editor = CodeMirror.fromTextArea(element, {
          lineNumbers: true,
          lineWrapping: true,
          indentUnit: 2,
          tabSize: 2,
          indentWithTabs: false,
          theme: 'default',
          autoCloseBrackets: true,
          matchBrackets: true,
          styleActiveLine: true,
          mode: 'text/plain',
          extraKeys: {
            'Tab': function (cm) {
              // Insert 2 spaces instead of tab
              cm.replaceSelection('  ', 'end');
            },
            'Shift-Tab': function (cm) {
              cm.execCommand('indentLess');
            },
            'F11': function (cm) {
              cm.setOption('fullScreen', !cm.getOption('fullScreen'));
            },
            'Esc': function (cm) {
              if (cm.getOption('fullScreen')) {
                cm.setOption('fullScreen', false);
              }
            },
            'Ctrl-Enter': function (cm) {
              cm.setOption('fullScreen', !cm.getOption('fullScreen'));
            },
            'Cmd-Enter': function (cm) {
              cm.setOption('fullScreen', !cm.getOption('fullScreen'));
            }
          }
        });

        // Add TWIG-specific auto-closing
        editor.on('beforeChange', function (cm, change) {
          if (change.origin === '+input' && $modeSelect.val() === 'twig') {
            const text = change.text[0];
            const cursor = cm.getCursor();

            // Auto-close TWIG delimiters
            if (text === '{' && change.text.length === 1) {
              const nextChar = cm.getRange(cursor, {line: cursor.line, ch: cursor.ch + 1});

              // Check what follows the {
              setTimeout(function () {
                const newCursor = cm.getCursor();
                const prevTwo = cm.getRange({line: newCursor.line, ch: newCursor.ch - 2}, newCursor);

                if (prevTwo === '{{') {
                  cm.replaceRange(' }}', newCursor);
                  cm.setCursor({line: newCursor.line, ch: newCursor.ch + 1});
                } else if (prevTwo === '{%') {
                  cm.replaceRange(' %}', newCursor);
                  cm.setCursor({line: newCursor.line, ch: newCursor.ch + 1});
                } else if (prevTwo === '{#') {
                  cm.replaceRange(' #}', newCursor);
                  cm.setCursor({line: newCursor.line, ch: newCursor.ch + 1});
                }
              }, 1);
            }
          }
        });

        // Get CodeMirror wrapper
        const $cmWrapper = $(editor.getWrapperElement());
        const $fullscreenBtn = $('<button type="button" class="mcp-studio-fullscreen-btn" title="' + Drupal.t('Toggle fullscreen (F11)') + '"><span class="fullscreen-icon">⛶</span></button>');

        $cmWrapper.prepend($fullscreenBtn);

        // Fullscreen button click handler
        $fullscreenBtn.on('click', function (e) {
          e.preventDefault();
          editor.setOption('fullScreen', !editor.getOption('fullScreen'));
        });

        // Update fullscreen button when state changes
        editor.on('optionChange', function (cm, option) {
          if (option === 'fullScreen') {
            $fullscreenBtn.toggleClass('is-fullscreen', cm.getOption('fullScreen'));
            if (cm.getOption('fullScreen')) {
              $fullscreenBtn.find('.fullscreen-icon').text('✕');
            } else {
              $fullscreenBtn.find('.fullscreen-icon').text('⛶');
            }
          }
        });

        // Function to update editor mode
        function updateEditorMode(mode) {
          let cmMode = 'text/plain';
          let gutters = ['CodeMirror-linenumbers'];
          let lintOptions = false;

          // Clear any existing error markers
          editor.clearGutter('CodeMirror-lint-markers');

          switch (mode) {
            case 'json':
              cmMode = { name: 'application/json', json: true };
              gutters.push('CodeMirror-lint-markers');

              // Enable JSON linting if jsonlint is available
              if (typeof jsonlint !== 'undefined' && CodeMirror.lint) {
                lintOptions = {
                  getAnnotations: CodeMirror.lint.json,
                  async: true
                };
              }
              break;

            case 'twig':
              cmMode = 'twig';
              break;

            case 'text':
            default:
              cmMode = 'text/plain';
              break;
          }

          // Update editor options
          editor.setOption('mode', cmMode);
          editor.setOption('gutters', gutters);
          editor.setOption('lint', lintOptions);

          // Add mode class to wrapper
          $cmWrapper
            .removeClass('cm-mode-text cm-mode-json cm-mode-twig')
            .addClass('cm-mode-' + mode);

          // Refresh editor
          setTimeout(function () {
            editor.refresh();
          }, 100);
        }

        // Initialize with current mode
        const initialMode = $modeSelect.val() || $textarea.attr('data-mode') || 'text';
        updateEditorMode(initialMode);

        // Handle mode change
        $modeSelect.on('change', function () {
          updateEditorMode($(this).val());
        });

        // Sync content to textarea before form submit
        $textarea.closest('form').on('submit', function () {
          editor.save();
        });

        // Also save on blur
        editor.on('blur', function () {
          editor.save();
        });

        // Client-side validation
        let validationTimeout;
        editor.on('change', function () {
          const mode = $modeSelect.val();

          // Clear previous timeout
          clearTimeout(validationTimeout);

          // Remove any existing error messages
          const $cmWrapper = $(editor.getWrapperElement());
          $cmWrapper.parent().find('.codemirror-error-message').remove();
          $cmWrapper.removeClass('has-error');

          // Debounce validation
          validationTimeout = setTimeout(function () {
            const value = editor.getValue().trim();
            if (!value) { return;
            }

            switch (mode) {
              case 'json':
                try {
                  JSON.parse(value);
                } catch (e) {
                  $cmWrapper.addClass('has-error');
                  $('<div class="codemirror-error-message messages messages--error">' +
                    Drupal.t('Invalid JSON: @error', {'@error': e.message}) +
                    '</div>').insertAfter($cmWrapper);
                }
                break;

              case 'twig':
                // Basic TWIG validation
                const openTags = (value.match(/\{%/g) || []).length;
                const closeTags = (value.match(/%\}/g) || []).length;
                const openVars = (value.match(/\{\{/g) || []).length;
                const closeVars = (value.match(/\}\}/g) || []).length;
                const openComments = (value.match(/\{#/g) || []).length;
                const closeComments = (value.match(/#\}/g) || []).length;

                let errors = [];

                if (openTags !== closeTags) {
                  errors.push(Drupal.t('Unbalanced {% %} tags: @open opening, @close closing', {
                    '@open': openTags,
                    '@close': closeTags
                  }));
                }

                if (openVars !== closeVars) {
                  errors.push(Drupal.t('Unbalanced {{ }} variables: @open opening, @close closing', {
                    '@open': openVars,
                    '@close': closeVars
                  }));
                }

                if (openComments !== closeComments) {
                  errors.push(Drupal.t('Unbalanced {# #} comments: @open opening, @close closing', {
                    '@open': openComments,
                    '@close': closeComments
                  }));
                }

                if (errors.length > 0) {
                  $cmWrapper.addClass('has-error');
                  $('<div class="codemirror-error-message messages messages--error">' +
                    errors.join('<br>') +
                    '</div>').insertAfter($cmWrapper);
                }
                break;
            }
          }, 500);
        });

        // Store editor instance for later access
        $textarea.data('codemirror', editor);

        // Handle Drupal's collapsible elements
        const $details = $textarea.closest('details');
        if ($details.length) {
          $details.on('toggle', function () {
            if (this.open) {
              setTimeout(function () {
                editor.refresh();
              }, 100);
            }
          });
        }
      });

      // Initialize JSON Schema editors
      schemaElements.forEach(function (element) {
        const $textarea = $(element);

        // JSON Schema keywords for autocomplete
        const schemaKeywords = {
          // Core schema keywords
          '$schema': '"http://json-schema.org/draft-07/schema#"',
          '$ref': '"#/definitions/"',
          '$id': '""',

          // Type keywords
          'type': '"string"',
          'enum': '[]',
          'const': '""',

          // Object keywords
          'properties': '{}',
          'required': '[]',
          'additionalProperties': 'false',
          'patternProperties': '{}',
          'propertyNames': '{}',
          'minProperties': '0',
          'maxProperties': '10',

          // Array keywords
          'items': '{}',
          'additionalItems': 'false',
          'minItems': '0',
          'maxItems': '10',
          'uniqueItems': 'false',

          // String keywords
          'minLength': '0',
          'maxLength': '100',
          'pattern': '""',
          'format': '"email"',

          // Number keywords
          'minimum': '0',
          'maximum': '100',
          'exclusiveMinimum': '0',
          'exclusiveMaximum': '100',
          'multipleOf': '1',

          // Generic keywords
          'title': '""',
          'description': '""',
          'default': '""',
          'examples': '[]'
        };

        // Custom hint function for JSON Schema
        function jsonSchemaHint(cm) {
          const cursor = cm.getCursor();
          const token = cm.getTokenAt(cursor);
          const line = cm.getLine(cursor.line);

          // Check if we're inside quotes after a colon (property name position)
          const beforeCursor = line.substring(0, cursor.ch);
          const inPropertyName = beforeCursor.match(/"([^"]*)"?\s*:\s*$/);

          if (inPropertyName) {
            return null; // Don't autocomplete values
          }

          // Get the current word
          const word = token.string.replace(/^"/, '').replace(/"$/, '');

          // Filter matching keywords
          const matches = Object.keys(schemaKeywords).filter(key =>
            key.toLowerCase().startsWith(word.toLowerCase())
          );

          if (matches.length === 0) { return null;
          }

          return {
            list: matches.map(key => ({
              text: '"' + key + '": ' + schemaKeywords[key],
              displayText: key,
              className: 'json-schema-hint'
            })),
            from: CodeMirror.Pos(cursor.line, token.start),
            to: CodeMirror.Pos(cursor.line, token.end)
          };
        }

        // Initialize CodeMirror for JSON Schema
        const editor = CodeMirror.fromTextArea(element, {
          lineNumbers: true,
          lineWrapping: true,
          indentUnit: 2,
          tabSize: 2,
          indentWithTabs: false,
          theme: 'default',
          mode: { name: 'application/json', json: true },
          autoCloseBrackets: true,
          matchBrackets: true,
          styleActiveLine: true,
          gutters: ['CodeMirror-linenumbers', 'CodeMirror-lint-markers'],
          lint: typeof jsonlint !== 'undefined' ? {
            getAnnotations: CodeMirror.lint.json,
            async: true
          } : false,
          hintOptions: {
            hint: jsonSchemaHint,
            completeSingle: false
          },
          extraKeys: {
            'Tab': function (cm) {
              cm.replaceSelection('  ', 'end');
            },
            'Shift-Tab': function (cm) {
              cm.execCommand('indentLess');
            },
            'Ctrl-Space': 'autocomplete',
            '"': function (cm) {
              // Auto-show hints after typing a quote
              cm.replaceSelection('"');
              setTimeout(function () {
                cm.showHint();
              }, 100);
            }
          }
        });

        // Add placeholder hint
        if (!editor.getValue()) {
          editor.setOption('placeholder', $textarea.attr('placeholder') || '');
        }

        // JSON Schema validation
        let validationTimeout;
        editor.on('change', function () {
          clearTimeout(validationTimeout);

          const $wrapper = $(editor.getWrapperElement());
          const $container = $wrapper.parent();

          // Remove previous errors
          $container.find('.codemirror-error-message').remove();
          $wrapper.removeClass('has-error');

          validationTimeout = setTimeout(function () {
            const value = editor.getValue().trim();
            if (!value || value === '{}') { return;
            }

            try {
              const schema = JSON.parse(value);

              // Basic JSON Schema validation
              if (typeof schema !== 'object' || Array.isArray(schema)) {
                throw new Error(Drupal.t('Schema must be a JSON object'));
              }

              if (!schema.type && !schema.$ref && !schema.$schema) {
                throw new Error(Drupal.t('Schema must have a "type" property'));
              }

              if (schema.type) {
                const validTypes = ['null', 'boolean', 'object', 'array', 'number', 'string', 'integer'];
                if (!validTypes.includes(schema.type)) {
                  throw new Error(Drupal.t('Invalid type "@type"', {'@type': schema.type}));
                }
              }

            } catch (e) {
              $wrapper.addClass('has-error');
              $('<div class="codemirror-error-message messages messages--error">' +
                Drupal.t('Invalid JSON Schema: @error', {'@error': e.message}) +
                '</div>').insertAfter($wrapper);
            }
          }, 500);
        });

        // Save on form submit
        $textarea.closest('form').on('submit', function () {
          editor.save();
        });

        // Store instance
        $textarea.data('codemirror', editor);
      });
    }
  };

})(jQuery, Drupal, once, CodeMirror);
