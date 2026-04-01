/**
 * @file
 * MCP admin JavaScript behaviors.
 */

(function ($, Drupal, once) {
  'use strict';

  /**
   * Provides tool search functionality for MCP plugin configuration.
   */
  Drupal.behaviors.mcpToolSearch = {
    attach: function (context, settings) {
      const elements = once('mcp-tool-search', '.mcp-tools-search', context);
      
      elements.forEach(function(searchInput) {
        // Find the vertical tabs container
        const form = $(searchInput).closest('form');
        const verticalTabs = form.find('.vertical-tabs');
        
        if (!verticalTabs.length) return;
        
        // Get all tab menu items and their corresponding panels
        const tabMenuItems = verticalTabs.find('.vertical-tabs__menu-item');
        const tabPanels = verticalTabs.find('.vertical-tabs__pane');
        
        // Add event listener for search
        searchInput.addEventListener('input', function(e) {
          const searchTerm = e.target.value.toLowerCase().trim();
          
          let visibleCount = 0;
          
          if (searchTerm === '') {
            // Show all tabs and panels if search is empty
            tabMenuItems.show();
            tabPanels.each(function() {
              $(this).find('details').removeAttr('open');
            });
            
            // Show the vertical tabs container
            verticalTabs.show();
            
            // Remove no results message if exists
            $('.mcp-no-results-message').remove();
            return;
          }
          
          // Iterate through each tab
          tabMenuItems.each(function(index) {
            const $menuItem = $(this);
            const $tabPanel = $(tabPanels[index]);
            
            // Get the tool name from the tab
            const toolName = $menuItem.find('a strong').text().toLowerCase();
            
            // Get the description from the panel content
            let toolDescription = '';
            const infoDiv = $tabPanel.find('[id*="-info"]');
            if (infoDiv.length) {
              toolDescription = infoDiv.text().toLowerCase();
            }
            
            // Search in both name and description
            if (toolName.includes(searchTerm) || toolDescription.includes(searchTerm)) {
              $menuItem.show();
              visibleCount++;
              
              // Open the details element if search term is 3+ characters
              if (searchTerm.length >= 3) {
                $tabPanel.find('details').attr('open', 'open');
              }
              
              // If this is the first visible tab, make it active
              if (visibleCount === 1) {
                $menuItem.find('a').click();
              }
            } else {
              $menuItem.hide();
            }
          });
          
          // Show a message if no tools match
          let noResultsMessage = $('.mcp-no-results-message');
          
          if (visibleCount === 0) {
            // Hide the vertical tabs container
            verticalTabs.hide();
            
            if (!noResultsMessage.length) {
              noResultsMessage = $('<div class="mcp-no-results-message messages messages--warning">' + 
                Drupal.t('No tools found matching "@term"', {'@term': searchTerm}) + '</div>');
              $(searchInput).closest('.form-item').after(noResultsMessage);
            } else {
              noResultsMessage.html(Drupal.t('No tools found matching "@term"', {'@term': searchTerm}));
              noResultsMessage.show();
            }
          } else {
            // Show the vertical tabs container
            verticalTabs.show();
            
            if (noResultsMessage.length) {
              noResultsMessage.hide();
            }
          }
        });
        
        // Add clear button
        const $searchWrapper = $(searchInput).closest('.form-item');
        if (!$searchWrapper.find('.mcp-search-clear').length) {
          const clearButton = $('<button type="button" class="mcp-search-clear button button--small">' + 
            Drupal.t('Clear') + '</button>');
          clearButton.on('click', function() {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
          });
          $searchWrapper.find('.description').after(clearButton);
        }
      });
    }
  };

})(jQuery, Drupal, once);