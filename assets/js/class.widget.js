/**
 * Advanced Host Grid widget JavaScript class.
 * Extends the CWidget base class to render a grouped tree-table with color thresholds.
 */
class WidgetAdvancedHostGrid extends CWidget {

	static GROUP_BY_TAG_VALUE = 0;
	static GROUP_BY_HOST_GROUP = 1;
	static GROUP_BY_SEVERITY = 2;
	static GROUP_BY_HOST_INVENTORY = 3;
	static GROUP_BY_ITEM_VALUE = 4;

	static DISPLAY_AS_IS = 0;
	static DISPLAY_BAR = 1;
	static DISPLAY_INDICATORS = 2;

	onInitialize() {
		super.onInitialize();

		this._container = null;
		this._columns = [];
		this._group_by = [];
		this._grouped_data = [];
		this._host_count = 0;
		this._show_host_count = false;
		this._grouping_color_full = false;
		this._expanded = {};
	}

	setContents(response) {
		this._columns = response.columns || [];
		this._group_by = response.group_by || [];
		this._grouped_data = response.grouped_data || [];
		this._host_count = response.host_count || 0;
		this._show_host_count = response.show_host_count || false;
		this._grouping_color_full = response.grouping_color_full || false;

		super.setContents(response);

		this._container = this._body.querySelector('#ahg-container');

		if (!this._container) {
			this._container = this._body.querySelector('.advanced-host-grid-container');
		}

		if (this._container) {
			this._container.innerHTML = '';
			this._render();
		}
	}

	onResize() {
		super.onResize();
	}

	_render() {
		if (!this._container) return;

		const columnDefs = Object.values(this._columns);

		if (columnDefs.length === 0) {
			this._container.innerHTML = '<div class="ahg-no-data">' + t('No data') + '</div>';
			return;
		}

		// Build table.
		const table = document.createElement('table');
		table.className = 'ahg-table';
		if (this._group_by.length === 0) {
			table.classList.add('ahg-no-grouping');
		}

		if (this._show_host_count && this._host_count > 0) {
			const countBadge = document.createElement('div');
			countBadge.className = 'ahg-host-count';
			countBadge.textContent = `${this._host_count} ` + (this._host_count === 1 ? t('host') : t('hosts'));
			this._container.appendChild(countBadge);
		}

		// Header.
		const thead = document.createElement('thead');
		const headerRow = document.createElement('tr');

		/* Remove the extra indent column from header to allow host rows to align flush left */

		columnDefs.forEach(col => {
			const th = document.createElement('th');
			th.className = 'ahg-col-header';
			th.textContent = col.name || this._getColumnLabel(col);
			headerRow.appendChild(th);
		});

		thead.appendChild(headerRow);
		table.appendChild(thead);

		// Body.
		const tbody = document.createElement('tbody');

		if (this._group_by.length > 0 && this._grouped_data.length > 0) {
			this._renderGroupedNodes(tbody, this._grouped_data, 0, columnDefs, '', '');
		}
		else if (this._grouped_data.length > 0) {
			// No grouping — render flat.
			const flatHosts = this._grouped_data[0]?.hosts || [];
			flatHosts.forEach(host => {
				this._renderHostRow(tbody, host, 0, columnDefs);
			});
		}

		table.appendChild(tbody);

		// Scrollable wrapper.
		const wrapper = document.createElement('div');
		wrapper.className = 'ahg-scroll-wrapper';
		wrapper.appendChild(table);

		this._container.appendChild(wrapper);
	}

	_renderGroupedNodes(tbody, nodes, level, columnDefs, inheritedColor = '', parentGroupId = '') {
		nodes.forEach((node, nodeIndex) => {
			if (node.label === undefined || node.label === null) return;
			if (node.label === '') {
				// Skip empty label nodes (flat mode from controller).
				if (node.hosts) {
					node.hosts.forEach(host => {
						this._renderHostRow(tbody, host, level, columnDefs, inheritedColor);
					});
				}
				return;
			}

			const parentPrefix = parentGroupId ? parentGroupId + '_' : 'g_';
			const cleanLabel = String(node.label).replace(/[^a-zA-Z0-9]/g, '_');
			const groupId = parentPrefix + level + '_' + nodeIndex + '_' + cleanLabel;
			
			// Start collapsed by default (Solarwinds tree style)
			const isExpanded = this._expanded[groupId] === true;

			// Determine effective color: node's own color overrides inherited.
			const nodeColor = node.color || '';
			const effectiveColor = nodeColor || inheritedColor;
			const mappingColor = effectiveColor ? '#' + effectiveColor : '';

			// Group header row.
			const tr = document.createElement('tr');
			tr.className = 'ahg-group-row ahg-group-level-' + level;
			if (!isExpanded) tr.classList.add('ahg-collapsed');

			const tdExpand = document.createElement('td');
			tdExpand.className = 'ahg-group-toggle';
			tdExpand.colSpan = columnDefs.length;
			tdExpand.style.paddingLeft = (level * 16 + 8) + 'px';

			const toggleIcon = document.createElement('span');
			toggleIcon.className = 'ahg-status-circle';
			if (mappingColor) {
				toggleIcon.style.backgroundColor = mappingColor;
			}

			const labelSpan = document.createElement('span');
			labelSpan.className = 'ahg-group-label';
			labelSpan.textContent = node.label;
			labelSpan.style.marginLeft = '4px';

			if (mappingColor && this._grouping_color_full) {
				labelSpan.style.color = mappingColor;
			}

			tdExpand.appendChild(toggleIcon);
			tdExpand.appendChild(labelSpan);

			tr.appendChild(tdExpand);
			tbody.appendChild(tr);

			// Toggle event.
			tr.addEventListener('click', () => {
				const scrollWrapper = this._container.querySelector('.ahg-scroll-wrapper');
				const currentScroll = scrollWrapper ? scrollWrapper.scrollTop : 0;

				this._expanded[groupId] = !isExpanded;
				this._container.innerHTML = '';
				this._render();

				const newScrollWrapper = this._container.querySelector('.ahg-scroll-wrapper');
				if (newScrollWrapper) {
					newScrollWrapper.scrollTop = currentScroll;
				}
			});

			if (!isExpanded) return;

			// Render children or hosts.
			if (node.children && node.children.length > 0) {
				this._renderGroupedNodes(tbody, node.children, level + 1, columnDefs, effectiveColor, groupId);
			}

			if (node.hosts && node.hosts.length > 0) {
				node.hosts.forEach(host => {
					this._renderHostRow(tbody, host, level + 1, columnDefs, effectiveColor);
				});
			}
		});
	}

	_renderHostRow(tbody, host, indentLevel, columnDefs, groupColor = '') {
		const tr = document.createElement('tr');
		tr.className = 'ahg-host-row';

		const hostColumns = host.columns || {};
		columnDefs.forEach((col, colIndex) => {
			const td = document.createElement('td');
			td.className = 'ahg-cell ahg-host-cell';

			const cellData = host.columns[colIndex] || {};
			const rawValue = cellData ? cellData.raw_value : '';
			const displayValue = cellData ? (cellData.value !== undefined ? cellData.value : rawValue) : '';

			// Apply threshold coloring.
			const color = this._getThresholdColor(col, rawValue, cellData?.is_numeric);

			if (color) {
				td.style.backgroundColor = '#' + color;
				td.classList.add('ahg-threshold-colored');

				// Set text color based on background brightness.
				const brightness = this._getColorBrightness(color);
				td.style.color = brightness > 128 ? '#1a1a2e' : '#ffffff';
			}

			if (colIndex === 0) {
				const container = document.createElement('div');
				container.style.display = 'flex';
				container.style.alignItems = 'center';
				container.style.gap = '8px';

				// Add status circle matching parent group.
				const circle = document.createElement('span');
				circle.className = 'ahg-status-circle';
				if (groupColor) {
					circle.style.backgroundColor = '#' + groupColor;
				}
				container.appendChild(circle);
				
				// Calculate indent to match parent group exactly (parent_level * 16 + 8)
				// Note: indentLevel passed here is already level + 1
				// Indent 16px deeper than parent group label
				td.style.paddingLeft = (indentLevel * 16 + 8) + 'px';

				// Data type 0 is HOST_NAME
				if (col.data === 0) {
					const hostInfo = document.createElement('div');
					hostInfo.style.display = 'flex';
					hostInfo.style.alignItems = 'center';
					hostInfo.style.gap = '2px';

					const a = document.createElement('a');
					a.className = 'js-menu-host link-action ahg-host-link';
					a.setAttribute('data-hostid', host.hostid);
					a.href = 'javascript:void(0);';
					a.textContent = displayValue;
					
					if (cellData.menu_popup) {
						a.setAttribute('data-menu-popup', JSON.stringify(cellData.menu_popup));
					}
					
					hostInfo.appendChild(a);

					// Inject native maintenance icon HTML to the RIGHT if applicable.
					if (cellData.maintenance_icon_html) {
						const iconContainer = document.createElement('span');
						iconContainer.innerHTML = cellData.maintenance_icon_html;
						hostInfo.appendChild(iconContainer);
					}
					
					container.appendChild(hostInfo);
				} else {
					const span = document.createElement('span');
					span.textContent = displayValue;
					container.appendChild(span);
				}
				td.appendChild(container);
			} else {
				switch (col.display) {
					case WidgetAdvancedHostGrid.DISPLAY_BAR:
						this._renderBar(td, rawValue, col, displayValue);
						break;

					case WidgetAdvancedHostGrid.DISPLAY_INDICATORS:
						this._renderIndicator(td, rawValue, col, displayValue);
						break;

					default:
						td.textContent = displayValue;
						break;
				}
			}

			tr.appendChild(td);
		});

		tbody.appendChild(tr);
	}

	_renderBar(td, rawValue, col, displayValue) {
		const min = parseFloat(col.min) || 0;
		const max = parseFloat(col.max) || 100;
		const value = parseFloat(rawValue) || 0;
		const percent = Math.min(100, Math.max(0, ((value - min) / (max - min)) * 100));

		const barContainer = document.createElement('div');
		barContainer.className = 'ahg-bar-container';

		const barFill = document.createElement('div');
		barFill.className = 'ahg-bar-fill';
		barFill.style.width = percent + '%';

		const color = this._getThresholdColor(col, rawValue, true);
		if (color) {
			barFill.style.backgroundColor = '#' + color;
		}

		const barLabel = document.createElement('span');
		barLabel.className = 'ahg-bar-label';
		barLabel.textContent = displayValue;

		barContainer.appendChild(barFill);
		barContainer.appendChild(barLabel);
		td.appendChild(barContainer);
	}

	_renderIndicator(td, rawValue, col, displayValue) {
		const dot = document.createElement('span');
		dot.className = 'ahg-indicator-dot';

		const color = this._getThresholdColor(col, rawValue, true);
		if (color) {
			dot.style.backgroundColor = '#' + color;
		}
		else {
			dot.style.backgroundColor = col.base_color ? '#' + col.base_color : '#6D6D6D';
		}

		const label = document.createElement('span');
		label.className = 'ahg-indicator-label';
		label.textContent = displayValue;

		td.appendChild(dot);
		td.appendChild(label);
	}

	_getThresholdColor(col, rawValue, isNumeric) {
		if (!col.thresholds || col.thresholds.length === 0) {
			return col.base_color || '';
		}

		if (!isNumeric) {
			return col.base_color || '';
		}

		const numValue = parseFloat(rawValue);

		if (isNaN(numValue)) {
			return col.base_color || '';
		}

		// Find the highest threshold that the value meets.
		let matchedColor = col.base_color || '';

		// Sort thresholds by value ascending.
		const sorted = [...col.thresholds].sort((a, b) => {
			return parseFloat(a.threshold) - parseFloat(b.threshold);
		});

		for (const t of sorted) {
			if (numValue >= parseFloat(t.threshold)) {
				matchedColor = t.color;
			}
		}

		return matchedColor;
	}

	_getColorBrightness(hex) {
		if (!hex || hex.length < 6) return 200;

		const r = parseInt(hex.substring(0, 2), 16);
		const g = parseInt(hex.substring(2, 4), 16);
		const b = parseInt(hex.substring(4, 6), 16);

		return (r * 299 + g * 587 + b * 114) / 1000;
	}

	_getColumnLabel(col) {
		switch (col.data) {
			case 0: return 'Host name';
			case 1: return col.item || 'Item';
			case 2: return 'Text';
			default: return 'Column';
		}
	}

	_hexToRgba(hex, alpha) {
		if (!hex || hex.length < 6) return 'transparent';

		const r = parseInt(hex.substring(0, 2), 16);
		const g = parseInt(hex.substring(2, 4), 16);
		const b = parseInt(hex.substring(4, 6), 16);

		return `rgba(${r}, ${g}, ${b}, ${alpha})`;
	}
}
