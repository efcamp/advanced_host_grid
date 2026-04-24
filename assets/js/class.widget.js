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
		this._expand_depth = response.expand_depth !== undefined ? parseInt(response.expand_depth) : 1;
		this._grouping_color_full = response.grouping_color_full || false;
		this._honeycomb_view = response.honeycomb_view || false;
		this._honeycomb_shape = response.honeycomb_shape || 0;
		this._honeycomb_primary_label = response.honeycomb_primary_label !== undefined ? response.honeycomb_primary_label : 2;
		this._honeycomb_secondary_label = response.honeycomb_secondary_label !== undefined ? response.honeycomb_secondary_label : 0;

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

		const columnDefs = Object.values(this._columns)
			.map((col, originalIndex) => ({...col, originalIndex}))
			.filter(col => parseInt(col.is_hidden || 0) === 0);

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
		if (!this._honeycomb_view) {
			table.appendChild(thead);
		}

		// Body.
		const tbody = document.createElement('tbody');

		if (this._group_by.length > 0 && this._grouped_data.length > 0) {
			this._renderGroupedNodes(tbody, this._grouped_data, 0, columnDefs, '', '');
		}
		else if (this._grouped_data.length > 0) {
			// No grouping — render flat.
			const flatHosts = this._grouped_data[0]?.hosts || [];
			if (this._honeycomb_view) {
				this._renderHoneycombWrapper(tbody, flatHosts, 0, columnDefs);
			} else {
				flatHosts.forEach(host => {
					this._renderHostRow(tbody, host, 0, columnDefs);
				});
			}
		}

		table.appendChild(tbody);

		// Scrollable wrapper.
		const wrapper = document.createElement('div');
		wrapper.className = 'ahg-scroll-wrapper';
		wrapper.appendChild(table);

		this._container.appendChild(wrapper);

		// Synchronize label widths for perfect gauge alignment.
		requestAnimationFrame(() => {
			this._syncLabelWidths();
		});
	}

	_syncLabelWidths() {
		const labels = Array.from(this._container.querySelectorAll('.ahg-gauge-prepend-label'));
		if (labels.length === 0) return;

		const columns = {};
		labels.forEach(label => {
			const cell = label.closest('.ahg-cell');
			if (!cell) return;
			const index = Array.from(cell.parentNode.children).indexOf(cell);
			if (!columns[index]) columns[index] = [];
			columns[index].push(label);
		});

		Object.values(columns).forEach(colLabels => {
			let maxWidth = 60; // 60px minimum width for short labels
			
			colLabels.forEach(label => {
				// Measure natural width without constraints
				label.style.width = 'max-content';
				label.style.minWidth = '0';
				label.style.maxWidth = 'none';
				const width = label.getBoundingClientRect().width;
				if (width > maxWidth) {
					maxWidth = width;
				}
			});
			
			// Limit to a reasonable max to prevent a ridiculous name from breaking the UI
			maxWidth = Math.min(maxWidth, 400);
			const finalWidth = Math.ceil(maxWidth) + 'px';
			
			// Apply exact width to all labels in this column
			colLabels.forEach(label => {
				label.style.width = finalWidth;
				label.style.minWidth = finalWidth;
				label.style.maxWidth = finalWidth;
			});
		});
	}

	_renderGroupedNodes(tbody, nodes, level, columnDefs, inheritedColor = '', parentGroupId = '', manualOverrideColor = '') {
		nodes.forEach((node, nodeIndex) => {
			if (node.label === undefined || node.label === null) return;
			if (node.label === '') {
				// Skip empty label nodes (flat mode from controller).
				if (node.hosts) {
					if (this._honeycomb_view) {
						this._renderHoneycombWrapper(tbody, node.hosts, level, columnDefs);
					} else {
						node.hosts.forEach(host => {
							this._renderHostRow(tbody, host, level, columnDefs, inheritedColor);
						});
					}
				}
				return;
			}

			const parentPrefix = parentGroupId ? parentGroupId + '_' : 'g_';
			const cleanLabel = String(node.label).replace(/[^a-zA-Z0-9]/g, '_');
			const groupId = parentPrefix + level + '_' + nodeIndex + '_' + cleanLabel;
			
			// Initial expansion state based on depth
			if (!(groupId in this._expanded)) {
				this._expanded[groupId] = (level < this._expand_depth);
			}
			const isExpanded = this._expanded[groupId] === true;

			// Determine effective color: node's own color overrides inherited.
			const nodeColor = node.color || '';
			const effectiveColor = nodeColor || inheritedColor;
			const finalInheritedColor = node.row_color || effectiveColor;
			const activeManualColor = nodeColor || manualOverrideColor;
			const mappingColor = effectiveColor ? '#' + effectiveColor : '';

			// Group header row.
			const tr = document.createElement('tr');
			tr.className = 'ahg-group-row ahg-group-level-' + level;
			if (!isExpanded) tr.classList.add('ahg-collapsed');

			const tdExpand = document.createElement('td');
			tdExpand.className = 'ahg-group-toggle';
			tdExpand.colSpan = columnDefs.length;
			tdExpand.style.paddingLeft = (level * 20 + 10) + 'px';

			const toggleIcon = document.createElement('span');
			toggleIcon.className = 'ahg-status-circle';
			
			// Priority: Bubbled-up row color (Parent Status) > Dynamic grouping color
			const finalIconColor = node.row_color ? '#' + node.row_color : mappingColor;
			if (finalIconColor) {
				toggleIcon.style.backgroundColor = finalIconColor;
			}

			const labelSpan = document.createElement('span');
			labelSpan.className = 'ahg-group-label';
			labelSpan.textContent = node.label;
			labelSpan.style.marginLeft = '4px';

			if (finalIconColor && this._grouping_color_full) {
				labelSpan.style.color = finalIconColor;
				if (node.row_color) labelSpan.style.fontWeight = '600';
			}

			tdExpand.appendChild(toggleIcon);
			tdExpand.appendChild(labelSpan);

			tr.appendChild(tdExpand);
			tbody.appendChild(tr);

			// Toggle event.
			tr.addEventListener('click', () => {
				const scrollWrapper = this._container.querySelector('.ahg-scroll-wrapper');
				const currentScroll = scrollWrapper ? scrollWrapper.scrollTop : 0;

				const willBeExpanded = !isExpanded;
				this._expanded[groupId] = willBeExpanded;

				// If collapsing, recursively collapse all descendant groups so they are closed upon reopening
				if (!willBeExpanded) {
					Object.keys(this._expanded).forEach(key => {
						if (key.startsWith(groupId + '_')) {
							this._expanded[key] = false;
						}
					});
				}

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
				this._renderGroupedNodes(tbody, node.children, level + 1, columnDefs, finalInheritedColor, groupId, activeManualColor);
			}

			if (node.hosts && node.hosts.length > 0) {
				if (this._honeycomb_view) {
					this._renderHoneycombWrapper(tbody, node.hosts, level + 1, columnDefs);
				} else {
					node.hosts.forEach(host => {
						this._renderHostRow(tbody, host, level + 1, columnDefs, finalInheritedColor, activeManualColor);
					});
				}
			}
		});
	}

	_renderHostRow(tbody, host, level, columnDefs, inheritedColor = '', manualOverrideColor = '') {
		const tr = document.createElement('tr');
		tr.className = 'ahg-host-row js-menu-host';
		tr.dataset.hostid = host.hostid;
		if (host.menu_popup) {
			tr.setAttribute('data-menu-popup', JSON.stringify(host.menu_popup));
		}
		
		const hostColumns = host.columns || [];
		columnDefs.forEach((col) => {
			const colIndex = col.originalIndex;
			const td = document.createElement('td');
			td.className = 'ahg-cell ahg-host-cell';

			const cellData = hostColumns[colIndex] || {};
			const rawValue = cellData ? cellData.raw_value : '';
			const displayValue = cellData ? (cellData.value !== undefined ? cellData.value : rawValue) : '';

			// Apply threshold coloring.
			const color = cellData.threshold_color || this._getThresholdColor(col, rawValue, cellData?.is_numeric);
			const isGauge = [1, 2].includes(parseInt(col.display));
			const colorCell = parseInt(col.threshold_color_cell !== undefined ? col.threshold_color_cell : 1) === 1;

			if (color && !isGauge && colorCell) {
				td.style.backgroundColor = '#' + color;
				td.classList.add('ahg-threshold-colored');

				// Set text color based on background brightness.
				const brightness = this._getColorBrightness(color);
				td.style.color = brightness > 128 ? '#1a1a2e' : '#ffffff';
			}

			if (colIndex === 0) {
				const container = document.createElement('div');
				container.className = 'ahg-indent-wrapper';

				// Apply padding for indentation.
				container.style.paddingLeft = (level * 20 + 8) + 'px';

				// Add status circle matching row threshold or manual parent group override
				const circle = document.createElement('span');
				circle.className = 'ahg-status-circle';
				
				let rowNativeColor = '';
				Object.values(this._columns).forEach((col, colIdx) => {
					const colData = hostColumns[colIdx] || {};
					const rVal = colData ? colData.raw_value : '';
					const c = colData.threshold_color || this._getThresholdColor(col, rVal, colData?.is_numeric);
					if (!rowNativeColor && c) {
						rowNativeColor = c;
					}
				});

				const targetColor = manualOverrideColor || rowNativeColor;
				if (targetColor) {
					circle.style.backgroundColor = '#' + targetColor;
				}
				circle.style.marginRight = '4px';
				container.appendChild(circle);
				
				// Data type 0 is HOST_NAME
				if (parseInt(col.data) === 0) {
					const hostInfo = document.createElement('div');
					hostInfo.style.display = 'flex';
					hostInfo.style.alignItems = 'center';
					hostInfo.style.gap = '2px';

					const a = document.createElement('a');
					a.className = 'ahg-host-link';
					a.setAttribute('data-hostid', host.hostid);
					a.href = 'javascript:void(0);';
					a.textContent = displayValue;
					
					hostInfo.appendChild(a);

					if (cellData.maintenance_icon_html) {
						const iconContainer = document.createElement('span');
						iconContainer.innerHTML = cellData.maintenance_icon_html;
						hostInfo.appendChild(iconContainer);
					}
					
					container.appendChild(hostInfo);
				} else {
					// Check for Bar/Indicator displays even in Column 0
					switch (parseInt(col.display)) {
						case 1: // Bar
							this._renderNativeGauge(container, cellData, col, true);
							break;
						case 2: // Indicator
							this._renderNativeGauge(container, cellData, col, true);
							break;
						default: {
							const span = document.createElement('span');
							let text = displayValue;
							const actualName = cellData.item_name || col.item || '';
							if (parseInt(col.prepend_item) === 1 && actualName) {
								text = actualName + ': ' + text;
							}
							span.textContent = text;
							container.appendChild(span);
							break;
						}
					}
				}
				td.appendChild(container);
			} else {
				switch (parseInt(col.display)) {
					case 1: // Bar
						this._renderNativeGauge(td, cellData, col);
						break;

					case 2: // Indicator
						this._renderNativeGauge(td, cellData, col);
						break;

					default:
						const valSpan = document.createElement('span');
						valSpan.className = 'ahg-value-span';
						valSpan.style.padding = '0 8px';
						
						let text = displayValue;
						const actualName = cellData.item_name || col.item || '';
						if (parseInt(col.prepend_item) === 1 && actualName) {
							text = actualName + ': ' + text;
						}
						valSpan.textContent = text;
						td.appendChild(valSpan);
						break;
				}
			}

			tr.appendChild(td);
		});

		tbody.appendChild(tr);
	}

	/**
	 * Render a native CBarGauge received from PHP.
	 * Layout: [Prepended Name] [Gauge Container] [Value]
	 */
	_renderNativeGauge(container, cellData, col, inColumnZero = false) {
		const wrapper = document.createElement('div');
		wrapper.className = 'ahg-native-gauge-wrapper';
		wrapper.style.display = 'flex';
		wrapper.style.alignItems = 'center';
		wrapper.style.justifyContent = 'space-between';
		wrapper.style.width = '100%';
		wrapper.style.gap = '2px'; // Reduced to ~30%
		wrapper.style.padding = inColumnZero ? '0' : '0 2px'; // Reduced to ~30%

		// 1. Prepended Host/Item name
		const actualName = cellData.item_name || col.item || '';
		if (parseInt(col.prepend_item) === 1 && actualName) {
			const label = document.createElement('div');
			label.className = 'ahg-gauge-prepend-label';
			label.textContent = actualName + ':';
			label.title = actualName; 
			label.style.flex = '0 0 auto'; 
			label.style.overflow = 'hidden';
			label.style.textOverflow = 'ellipsis';
			label.style.whiteSpace = 'nowrap';
			label.style.marginRight = '2px'; // Reduced to ~30%
			wrapper.appendChild(label);
		}

		// 2. The Gauge itself
		const gaugeContainer = document.createElement('div');
		gaugeContainer.className = 'ahg-gauge-visual-container';
		gaugeContainer.style.flex = '1 1 auto'; // Allow it to claim all remaining space
		gaugeContainer.style.minWidth = '60px';  // ENFORCE minimum width (60px Floor)
		// Removed overflow: hidden to prevent clipping the right stroke of the Canvas
		gaugeContainer.innerHTML = cellData.gauge_html || '';
		wrapper.appendChild(gaugeContainer);

		// Manually trigger gauge component update if needed (Zabbix 7.4 sync)
		const gaugeEl = gaugeContainer.querySelector('z-bar-gauge');
		if (gaugeEl) {
			// Web components in Zabbix sometimes need a property nudge after innerHTML injection
			if (cellData.raw_value !== undefined) {
				gaugeEl.value = cellData.raw_value;
			}
			
			// Force the internal refresh if the component is already upgraded
			if (typeof gaugeEl._refresh === 'function') {
				gaugeEl._refresh();
			} else {
				// Fallback: trigger a resize event to nudge the ResizeObserver
				window.dispatchEvent(new Event('resize'));
			}
		}

		// 3. The Value string
		const valueLabel = document.createElement('div');
		valueLabel.className = 'ahg-gauge-value-label';
		valueLabel.textContent = cellData.value !== undefined ? cellData.value : (cellData.raw_value || '');
		valueLabel.style.whiteSpace = 'nowrap';
		valueLabel.style.minWidth = '40px';
		valueLabel.style.textAlign = 'right';
		valueLabel.style.flex = '0 0 auto'; // Prevent gauge overlap
		valueLabel.style.marginLeft = 'auto'; // Force to right
		wrapper.appendChild(valueLabel);

		container.appendChild(wrapper);
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

	_renderHoneycombWrapper(tbody, hosts, level, columnDefs) {
		if (hosts.length === 0) return;

		const tr = document.createElement('tr');
		tr.className = 'ahg-honeycomb-row';

		const td = document.createElement('td');
		td.colSpan = columnDefs.length > 0 ? columnDefs.length : 1;
		td.style.padding = 0;

		const wrapper = document.createElement('div');
		wrapper.className = 'ahg-honeycomb-wrapper';
		wrapper.style.marginLeft = (level * 20 + 8) + 'px';

		hosts.forEach(host => {
			wrapper.appendChild(this._createHoneycombCell(host, columnDefs));
		});

		td.appendChild(wrapper);
		tr.appendChild(td);
		tbody.appendChild(tr);
	}

	_createHoneycombCell(host, columnDefs) {
        let targetColIndex = -1;
        for (let i = 0; i < columnDefs.length; i++) {
            const colIdx = columnDefs[i].originalIndex;
            if (parseInt(columnDefs[i].data) === 4 || parseInt(columnDefs[i].data) === 1) { 
                // Only select this column as the source for the honeycomb shape if it actually has data
                if (host.columns[colIdx] !== undefined && host.columns[colIdx].raw_value !== '') {
                    targetColIndex = colIdx;
                    break;
                }
            }
        }
        if (targetColIndex === -1 && columnDefs.length > 0) {
            targetColIndex = 0;
        }

        const cellData = targetColIndex >= 0 ? (host.columns[targetColIndex] || {}) : {};
        const colDef = targetColIndex >= 0 ? columnDefs[targetColIndex] : null;

        const cell = document.createElement('div');
        cell.className = 'ahg-honeycomb-cell js-menu-host';
        if (parseInt(this._honeycomb_shape) === 1) {
            cell.classList.add('ahg-square-cell');
        } else {
            cell.classList.add('ahg-hex-cell');
        }
        cell.dataset.hostid = host.hostid;
        
        if (host.menu_popup) {
            cell.setAttribute('data-menu-popup', JSON.stringify(host.menu_popup));
        }

        let bgColor = '';
        if (colDef) {
            const rawValue = cellData ? cellData.raw_value : '';
            bgColor = cellData.threshold_color || this._getThresholdColor(colDef, rawValue, cellData?.is_numeric) || colDef.base_color;
        }
        if (bgColor) {
            cell.style.backgroundColor = '#' + bgColor;
            const brightness = this._getColorBrightness(bgColor);
            cell.style.color = brightness > 128 ? '#1a1a2e' : '#ffffff';
        }

        const getLabelMarkup = (type) => {
            switch(parseInt(type)) {
                case 0: return host.name || '';
                case 1: return cellData.item_name || (colDef ? colDef.item : '') || '';
                case 2: return cellData.value !== undefined ? cellData.value : (cellData.raw_value || '');
                default: return '';
            }
        };

        const primaryStr = getLabelMarkup(this._honeycomb_primary_label);
        const secondaryStr = getLabelMarkup(this._honeycomb_secondary_label);

        if (primaryStr) {
            const p = document.createElement('div');
            p.className = 'ahg-hc-primary';
            p.textContent = primaryStr;
            cell.appendChild(p);
        }

        if (secondaryStr) {
            const s = document.createElement('div');
            s.className = 'ahg-hc-secondary';
            s.textContent = secondaryStr;
            cell.appendChild(s);
        }

        let tooltipLines = [];
        if (host.name) tooltipLines.push('Host: ' + host.name);
        if (cellData.item_name) tooltipLines.push('Item: ' + cellData.item_name);
        const valStr = cellData.value !== undefined ? cellData.value : cellData.raw_value;
        if (valStr !== undefined && valStr !== '') tooltipLines.push('Value: ' + valStr);
        
        if (tooltipLines.length > 0) {
            cell.setAttribute('data-hintbox', '1');
            cell.setAttribute('data-hintbox-contents', tooltipLines.join('<br>'));
        }

        return cell;
    }
}
