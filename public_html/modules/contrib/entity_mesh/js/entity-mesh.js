/**
 * @file
 * Provides JavaScript functionality for the Entity Mesh visualization.
 */

/* global d3, entityMeshData */
(function (Drupal, once) {
  /**
   * Attaches behavior to initialize the Entity Mesh graph.
   */
  Drupal.behaviors.entityMesh = {
    attach(context, settings) {
      once('entityMesh', '#entity-mesh-graph', context).forEach((element) => {
        element.classList.add('processed');

        // Ensure data is available before generating the graph.
        if (settings.entity_mesh?.data) {
          this.graphGenerate(
            settings.entity_mesh.data,
            settings.entity_mesh.settings,
          );
        } else if (typeof entityMeshData !== 'undefined') {
          this.graphGenerate(entityMeshData, settings.entity_mesh.settings);
        }
      });
    },

    /**
     * Generates the D3 graph visualization.
     *
     * @param {Object} data - The graph data including nodes, links, and types.
     * @param {Object} settings - Graph settings.
     */
    graphGenerate(data, settings) {
      // Initialize objects to ensure they are reset for each request.
      const selector = '#entity-mesh-graph';
      const nodes = data.nodes || [];
      const links = data.links || [];
      const types = data.types || [];
      let selectedNode = null;
      let filterTypes = [];
      let legendItems;

      // Calculate the total number of nodes for each type.
      const typesTotal = Object.values(
        nodes.reduce((acc, item) => {
          if (!acc[item.type]) {
            acc[item.type] = { name: item.type, total: 0 };
          }
          acc[item.type].total += 1;
          return acc;
        }, {}),
      ).sort((a, b) => a.name.localeCompare(b.name));

      // Initialize graph dimensions.
      const graph = document.querySelector(selector);
      const graphCStyle = window.getComputedStyle(graph);
      const width =
        graph.clientWidth +
        parseFloat(graphCStyle.paddingLeft) +
        parseFloat(graphCStyle.paddingRight);
      const height = settings.fullHeight ? window.innerHeight - 300 : 450;

      // Ensure D3 is available
      if (typeof d3 === 'undefined') {
        console.error('D3.js is not loaded.');
        return;
      }

      // Initialize D3 color scale.
      const color = d3.scaleOrdinal(types, d3.schemeCategory10);

      // Generate D3 SVG graph.
      const svg = d3
        .select(selector)
        .append('svg')
        .attr('width', '100%')
        .attr('height', height);

      // Initialize D3 force simulation.
      const simulation = d3
        .forceSimulation()
        .force('center', d3.forceCenter(width / 2, height / 2))
        .force(
          'link',
          d3.forceLink().id((d) => d.id),
        )
        .force(
          'charge',
          d3
            .forceManyBody()
            .strength(-200)
            .distanceMax(Math.min(width, height) / 2),
        )
        .force('collide', d3.forceCollide().strength(-200))
        .force('y', d3.forceY(0))
        .force('x', d3.forceX(0));

      // Create a wrapper group for zoom functionality.
      const wrapper = svg.append('g').attr('class', 'zoom-wrapper');

      // Define arrow markers for directed links.
      wrapper
        .append('defs')
        .append('marker')
        .attr('id', 'arrow')
        .attr('viewBox', '0 -5 10 10')
        .attr('fill', '#DDD')
        .attr('refX', 25)
        .attr('refY', 0)
        .attr('markerWidth', 6)
        .attr('markerHeight', 6)
        .attr('orient', 'auto')
        .append('path')
        .attr('d', 'M0,-5L10,0L0,5')
        .attr('class', 'arrow-head');

      // Draw links.
      const link = wrapper
        .append('g')
        .attr('class', 'lines')
        .selectAll('line')
        .data(links)
        .enter()
        .append('line')
        .attr('stroke', '#DDD')
        .attr('stroke-width', 2)
        .attr('marker-end', 'url(#arrow)');

      // Draw nodes.
      const node = wrapper
        .append('g')
        .attr('class', 'nodes')
        .selectAll('g')
        .data(nodes)
        .enter()
        .append('g')
        .attr('class', 'node');

      /**
       * Gets the count of links for a node.
       *
       * @param {string} nodeId - The node ID.
       * @returns {number} - The count of links.
       */
      const getLinkCount = (nodeId) => {
        const count = links.filter((link) => link.target === nodeId).length;
        return count > 0 ? count : '';
      };

      /**
       * Calculates node radius.
       *
       * @param {string} node - The node ID.
       * @returns {number} - The node radius
       */
      const getNodeRadius = (node) => {
        const baseRadius = 5;
        const variableSize =
          getLinkCount(node.id) * (Math.log10(getLinkCount(node.id)) + 1);
        return Math.min(
          baseRadius + (Number.isNaN(variableSize) ? 1 : variableSize),
          50,
        );
      };

      const labels = node
        .append('text')
        .attr('class', 'node-label')
        .attr('text-anchor', 'left')
        .attr('dx', (d) => getNodeRadius(d) + 3)
        .attr('dy', 3)
        .text((d) => d.label)
        .style('display', 'none')
        .style('cursor', 'pointer')
        .style('pointer-events', 'all')
        .on('click', (event, d) => {
          event.stopPropagation();
          if (d.url) {
            window.open(d.url, '_blank', 'noopener');
          }
        });

      // Add circles to nodes.
      node
        .append('circle')
        .attr('r', (d) => {
          return getNodeRadius(d);
        })
        .attr('fill', (d) => color(d.type));

      /**
       * Handles drag start event.
       */
      const dragStarted = (event, d) => {
        if (!event.active) simulation.alphaTarget(0.3).restart();
        d.fx = d.x;
        d.fy = d.y;
      };

      /**
       * Handles dragging event.
       */
      const dragged = (event, d) => {
        d.fx = event.x;
        d.fy = event.y;
      };

      /**
       * Handles drag end event.
       */
      const dragEnded = (event, d) => {
        if (!event.active) simulation.alphaTarget(0);
        d.fx = null;
        d.fy = null;
      };

      // Enable dragging.
      const dragHandler = d3
        .drag()
        .on('start', dragStarted)
        .on('drag', dragged)
        .on('end', dragEnded);
      dragHandler(node);

      // Add tooltips.
      node.append('title').text((d) => d.label);

      // Show link counts.
      node
        .append('text')
        .text((d) => (getLinkCount(d.id) > 1 ? getLinkCount(d.id) : ''))
        .attr('font-size', '10px')
        .attr('fill', 'white')
        .attr('text-anchor', 'middle')
        .attr('y', 3);

      /**
       * Handles zoom actions.
       */
      const zoomActions = (event) => {
        wrapper.attr('transform', event.transform);
      };

      // Apply zoom behavior.
      const zoomHandler = d3.zoom().on('zoom', zoomActions);
      zoomHandler(svg);

      /**
       * Updates node positions at each tick of the simulation.
       */
      const ticked = () => {
        node.attr('transform', (d) => `translate(${d.x},${d.y})`);
        link
          .attr('x1', (d) => d.source.x)
          .attr('y1', (d) => d.source.y)
          .attr('x2', (d) => d.target.x)
          .attr('y2', (d) => d.target.y);
      };

      // Apply forces.
      simulation.nodes(nodes).alphaTarget(0).on('tick', ticked);
      simulation.force('link').links(links);

      /**
       * Gets connected nodes for a given node ID.
       *
       * @param {string} nodeId - The ID of the node.
       * @param {number} [depth=0] - The depth of connection to traverse.
       * @returns {Set<string>} - A set of connected node IDs.
       */
      const getConnectedNodes = (nodeId, depth = 0) => {
        const connectedNodes = new Set();
        const visitedNodes = new Set();

        const traverse = (currentNodeId, depth) => {
          visitedNodes.add(currentNodeId);

          links.forEach((link) => {
            // New node target (first level only):
            if (
              link.source.id === currentNodeId &&
              !visitedNodes.has(link.target.id)
            ) {
              connectedNodes.add(link.target.id);
              if (depth > 0) {
                traverse(link.target.id, depth - 1);
              }
            }
            // New node that points to the target node (first level only)
            else if (
              link.target.id === currentNodeId &&
              !visitedNodes.has(link.source.id)
            ) {
              connectedNodes.add(link.source.id);
              if (depth > 0) {
                traverse(link.source.id, depth - 1);
              }
            }
          });
        };

        traverse(nodeId, depth);

        return connectedNodes;
      };

      /**
       * Filters nodes and links based on selected node and types.
       */
      const filterNodes = () => {
        // Hide All:
        node.attr('display', 'none');
        link.attr('display', 'none');
        labels.style('display', 'none');

        let nodesVisible = node;
        let linksVisible = link;

        // Filter by Selected node:
        if (selectedNode !== null) {
          const connectedNodes = getConnectedNodes(selectedNode.id, 0);
          // Remove all nodes not connected but current one:
          nodesVisible = node.filter(
            (d) => connectedNodes.has(d.id) || d.id === selectedNode.id,
          );
          // Remove all links from nodes not in the list but current one:
          linksVisible = link.filter(
            (d) =>
              d.source.id === selectedNode.id ||
              d.target.id === selectedNode.id ||
              (connectedNodes.has(d.source.id) &&
                connectedNodes.has(d.target.id)),
          );

          labels
            .filter((d) => connectedNodes.has(d.id) || d.id === selectedNode.id)
            .style('display', 'inline');
        }

        // Filter by Type:
        if (filterTypes.length > 0) {
          nodesVisible = nodesVisible.filter(
            (d) => filterTypes.indexOf(d.type) !== -1,
          );
          linksVisible = linksVisible.filter(
            (d) =>
              filterTypes.indexOf(d.source.type) !== -1 &&
              filterTypes.indexOf(d.target.type) !== -1,
          );
        }

        // Show Visible ones:
        nodesVisible.attr('display', 'inline');
        linksVisible.attr('display', 'inline');
      };

      /**
       * Toggles visibility of node types in the legend.
       */
      const toggleLegends = (event, clickedLegend) => {
        const index = filterTypes.indexOf(clickedLegend.name);

        if (index === -1) {
          filterTypes.push(clickedLegend.name);
          d3.select(event.currentTarget).classed('selected', true);
        } else {
          filterTypes.splice(index, 1);
          d3.select(event.currentTarget).classed('selected', false);
        }

        if (filterTypes.length === types.length) {
          filterTypes = [];
          // @todo remove
          legendItems.classed('selected', false);
        }

        filterNodes();
      };

      // Legend
      const legend = svg
        .append('g')
        .attr('class', 'legend')
        .attr('transform', 'translate(20, 20)');

      legendItems = legend
        .selectAll('.legend-item')
        .data(typesTotal)
        .enter()
        .append('g')
        .attr('x', 20)
        .attr('y', 10)
        .attr('class', 'legend-item')
        .attr('transform', (d, i) => `translate(0, ${i * 30})`);

      legendItems
        .append('rect')
        .attr('width', 10)
        .attr('height', 10)
        .style('fill', (d) => color(d.name));

      legendItems
        .append('text')
        .attr('x', 20)
        .attr('y', 10)
        .text((d) => `${d.name} (${d.total})`)
        .on('click', toggleLegends);

      const toggleConnections = (event, clickedNode) => {
        node.classed('selected', false);

        // Revert selection:
        if (clickedNode === selectedNode) {
          node.attr('display', 'inline');
          link.attr('display', 'inline');
          labels.style('display', 'none');

          selectedNode = null;
          return;
        }

        selectedNode = clickedNode;
        // Apply the 'selected' class to the clicked node
        d3.select(event.currentTarget).classed('selected', true);

        filterNodes();
      };

      node.on('click', toggleConnections);
    },
  };
})(Drupal, once);
