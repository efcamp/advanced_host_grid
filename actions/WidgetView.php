<?php declare(strict_types = 0);

namespace Modules\AdvancedHostGrid\Actions;

use API,
	CBarGauge,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CMenuPopupHelper,
	CMacrosResolverHelper,
	CSpan;

use Modules\AdvancedHostGrid\Widget;
use Modules\AdvancedHostGrid\Includes\WidgetForm;

class WidgetView extends CControllerDashboardWidgetView {

	protected function doAction(): void {
		$group_by = $this->fields_values['group_by'] ?? [];
		$columns = $this->fields_values['columns'] ?? [];
		$order_column = $this->fields_values['column'] ?? 0;
		$order = $this->fields_values['order'] ?? Widget::ORDER_TOP_N;
		$show_lines = $this->fields_values['show_lines'] ?? 100;
		$show_host_count = (bool)($this->fields_values['show_host_count'] ?? false);
		$show_all_matches = (bool)($this->fields_values['show_all_matches'] ?? false);
		$expand_depth = (int)($this->fields_values['expand_depth'] ?? 1);
		$remember_expanded = (bool)($this->fields_values['remember_expanded'] ?? false);
		$grouping_color_full = (bool)($this->fields_values['grouping_color_full'] ?? false);

		$honeycomb_view = (bool)($this->fields_values['honeycomb_view'] ?? false);
		$honeycomb_shape = (int)($this->fields_values['honeycomb_shape'] ?? 0);
		$honeycomb_primary_label = (int)($this->fields_values['honeycomb_primary_label'] ?? 2);
		$honeycomb_secondary_label = (int)($this->fields_values['honeycomb_secondary_label'] ?? 0);

		if ($honeycomb_view) {
			$show_all_matches = true;
		}
		
		// Maintenance override settings
		$maintenance_override = (bool)($this->fields_values['maintenance_override'] ?? false);
		$m_settings = explode(':', (string)($this->fields_values['maintenance_override_settings'] ?? '1:Maintenance:e6e6e6'));
		$m_level = (int)($m_settings[0] ?? 1) - 1; // 0-indexed internally
		$m_label = (string)($m_settings[1] ?? 'Maintenance');
		$m_color = trim((string)($m_settings[2] ?? ''));
		if ($m_color === '') {
			$m_color = '6c6c6c';
		}

		// ---- Fetch hosts ----
		$host_options = [
			'output' => ['hostid', 'name', 'status', 'maintenance_status', 'maintenance_type', 'maintenanceid', 'host'],
			'selectHostGroups' => ['groupid', 'name'],
			'selectTags' => ['tag', 'value'],
			'selectInventory' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		];

		if ($this->isTemplateDashboard()) {
			if (empty($this->fields_values['override_hostid'])) {
				$this->setResponse(new CControllerResponseData([
					'name' => $this->getInput('name', $this->widget->getName()),
					'hosts' => [],
					'columns' => $columns,
					'group_by' => $group_by,
					'grouped_data' => [],
					'user' => ['debug_mode' => $this->getDebugMode()]
				]));
				return;
			}
			$host_options['hostids'] = $this->fields_values['override_hostid'];
		} else {
			if (!empty($this->fields_values['groupids'])) {
				$host_options['groupids'] = $this->fields_values['groupids'];
			}
			if (!empty($this->fields_values['hostids'])) {
				$host_options['hostids'] = $this->fields_values['hostids'];
			}
			if (!empty($this->fields_values['tags'])) {
				$host_options['evaltype'] = $this->fields_values['evaltype'] ?? TAG_EVAL_TYPE_AND_OR;
				$host_options['tags'] = $this->fields_values['tags'];
			}
		}

		if (empty($this->fields_values['maintenance'])) {
			$host_options['filter'] = ['maintenance_status' => HOST_MAINTENANCE_STATUS_OFF];
		}

		$hosts = API::Host()->get($host_options);

		if (!$hosts) {
			$this->setResponse(new CControllerResponseData([
				'name' => $this->getInput('name', $this->widget->getName()),
				'hosts' => [],
				'columns' => $columns,
				'group_by' => $group_by,
				'grouped_data' => [],
				'user' => ['debug_mode' => $this->getDebugMode()]
			]));
			return;
		}

		$host_ids = array_keys($hosts);

		// ---- Fetch active trigger severities for filtering/grouping ----
		$host_severities = [];
		$db_triggers = API::Trigger()->get([
			'output' => ['priority'],
			'selectHosts' => ['hostid'],
			'hostids' => $host_ids,
			'filter' => ['value' => TRIGGER_VALUE_TRUE],
			'monitored' => true,
			'nopermissions' => true
		]);

		foreach ($db_triggers as $trigger) {
			foreach ($trigger['hosts'] as $h) {
				$hid = $h['hostid'];
				if (!isset($host_severities[$hid]) || (int)$trigger['priority'] > $host_severities[$hid]) {
					$host_severities[$hid] = (int)$trigger['priority'];
				}
			}
		}

		// ---- Fetch item values for columns, filters, and grouping ----
		$item_patterns = [];
		foreach ($columns as $column) {
			if ($column['data'] == Widget::DATA_ITEM_VALUE && !empty($column['item'])) {
				$item_patterns[] = $column['item'];
			}
		}
		for ($i = 1; $i <= 3; $i++) {
			$target = (int)($this->fields_values['filter'.$i.'_column'] ?? -1);
			$param = (string)($this->fields_values['filter'.$i.'_target_param'] ?? '');
			if ($target === WidgetForm::FILTER_TARGET_ITEM_VALUE && $param !== '') {
				$item_patterns[] = $param;
			}
		}
		foreach ($group_by as $group_row) {
			if ($group_row['attribute'] == Widget::GROUP_BY_ITEM_VALUE && !empty($group_row['item_pattern'])) {
				$item_patterns[] = $group_row['item_pattern'];
			}
			if (isset($group_row['group_order_by']) && $group_row['group_order_by'] == Widget::GROUP_ORDER_BY_ITEM_VALUE && !empty($group_row['group_order_item_pattern'])) {
				$item_patterns[] = $group_row['group_order_item_pattern'];
			}
		}
		$item_patterns = array_unique($item_patterns);

		$host_item_values = [];
		$items_by_host_all = []; // [hostid][itemid] => [item_info...]
		if ($item_patterns) {
			$all_db_items = [];
			$matched_patterns = [];
			$first_match_tracker = [];

			foreach ($item_patterns as $pattern) {
				$db_items = API::Item()->get([
					'output' => ['itemid', 'hostid', 'value_type', 'name', 'units', 'valuemapid', 'key_', 'name_resolved'],
					'selectValueMap' => ['mappings'],
					'hostids' => $host_ids,
					'search' => ['name' => $pattern],
					'searchWildcardsEnabled' => true,
					'searchByAny' => true,
					'webitems' => true,
					'monitored' => true,
					'filter' => [
						'value_type' => [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_TEXT]
					],
					'sortfield' => 'name'
				]);

				if ($db_items) {
					foreach ($db_items as $item) {
						$hostid = $item['hostid'];
						
						// If not exploding, we only care about the first match for this host+pattern combination
						if (!$show_all_matches && isset($first_match_tracker[$hostid][$pattern])) {
							continue;
						}

						$first_match_tracker[$hostid][$pattern] = true;

						if (!isset($all_db_items[$item['itemid']])) {
							$all_db_items[$item['itemid']] = $item;
							$matched_patterns[$item['itemid']] = [];
						}
						
						$matched_patterns[$item['itemid']][] = $pattern;
					}
				}
			}

			if ($all_db_items) {
				// Batch fetch the latest history value for all matched items efficiently
				$history = \Manager::History()->getLastValues($all_db_items, 1);

				foreach ($all_db_items as $itemid => $item) {
					if (isset($history[$itemid]) && !empty($history[$itemid])) {
						$val_row = $history[$itemid][0];
						$value = $val_row['value'];
						$is_numeric = in_array($item['value_type'], [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT]);
						$display_value = $is_numeric ? formatHistoryValue($value, $item) : $value;
						
						$item_name = array_key_exists('name_resolved', $item) ? $item['name_resolved'] : $item['name'];
						
						$cell_data = [
							'value' => $display_value,
							'raw_value' => $value,
							'is_numeric' => $is_numeric,
							'itemid' => $itemid,
							'item_name' => $item_name,
							'item' => $item
						];
						
						$hostid = $item['hostid'];
						foreach ($matched_patterns[$itemid] as $pattern) {
							if (!$show_all_matches) {
								if (!isset($host_item_values[$hostid][$pattern])) {
									$host_item_values[$hostid][$pattern] = $cell_data;
								}
							} else {
								$items_by_host_all[$hostid][$itemid] = $cell_data + ['pattern' => $pattern];
								if (!isset($host_item_values[$hostid][$pattern])) {
									$host_item_values[$hostid][$pattern] = $cell_data;
								}
							}
						}
					}
				}
			}
		}

		// ---- Fetch maintenance info for icons ----
		$maintenances = [];
		if ($host_ids) {
			$maintenanceids = [];
			foreach ($hosts as $host) {
				if ($host['maintenanceid'] != 0) {
					$maintenanceids[] = $host['maintenanceid'];
				}
			}
			if ($maintenanceids) {
				$maintenances = API::Maintenance()->get([
					'output' => ['name', 'description'],
					'maintenanceids' => array_unique($maintenanceids),
					'preservekeys' => true
				]);
			}
		}

		// ---- Resolve Text Columns using CMacrosResolverHelper ----
		$text_columns = [];
		$text_explode_col_idx = null;
		foreach ($columns as $col_index => $column) {
			if ($column['data'] == Widget::DATA_TEXT) {
				$text_columns[$col_index] = $column['text'] ?? '';
				if (!empty($column['explode_text']) && $text_explode_col_idx === null) {
					$text_explode_col_idx = $col_index;
				}
			}
		}
		if ($text_columns && $host_ids) {
			$text_columns = CMacrosResolverHelper::resolveWidgetTopHostsTextColumns($text_columns, $host_ids);
		}

		// ---- Build host data rows ----
		$host_rows = [];
		$row_index = 0;
		foreach ($hosts as $hostid => $host) {
			$row_index++;
			$base_row = [
				'hostid' => $hostid,
				'name' => $host['name'],
				'host' => $host['host'],
				'group_names' => array_column($host['hostgroups'] ?? [], 'name'),
				'tags' => $host['tags'] ?? [],
				'inventory' => $host['inventory'] ?? [],
				'severity' => $host_severities[$hostid] ?? 0,
				'in_maintenance' => (int)$host['maintenance_status'] === HOST_MAINTENANCE_STATUS_ON,
				'menu_popup' => CMenuPopupHelper::getHost($hostid),
				'exploded_item_name' => '', // Default for standard rows
				'explosion_index' => 0,
				'row_index' => $row_index
			];

			// Expansion Mode: Create one row per matched item
			if ($show_all_matches && !empty($items_by_host_all[$hostid])) {
				$exp_idx = 0;
				foreach ($items_by_host_all[$hostid] as $itemid => $item_info) {
					$exp_idx++;
					$row = $base_row;
					$row['explosion_index'] = $exp_idx;
					$row['exploded_item_name'] = $item_info['item_name'];
					$row['exploded_item_pattern'] = $item_info['pattern'];
					$row['exploded_item_val'] = $item_info['value'];
					$row['exploded_item_raw'] = $item_info['raw_value'];
					$row['columns'] = [];
					$row['bubble_up_colors'] = [];

					foreach ($columns as $col_index => $column) {
						$cell = ['value' => '', 'raw_value' => '', 'is_numeric' => false, 'threshold_color' => ''];
						
						if ($column['data'] == Widget::DATA_HOST_NAME) {
							$cell['value'] = $host['name'];
							$cell['raw_value'] = $host['name'];
							if ($row['in_maintenance']) {
								$m_name = isset($maintenances[$host['maintenanceid']]) ? $maintenances[$host['maintenanceid']]['name'] : _('Maintenance');
								$cell['maintenance_icon_html'] = (new CSpan())->addClass('icon-maintenance')->setHint($m_name)->toString();
							}
						}
						elseif ($column['data'] == Widget::DATA_ITEM_VALUE) {
							if ($item_info['pattern'] === ($column['item'] ?? '')) {
								$cell['raw_value'] = $item_info['raw_value'];
								$cell['is_numeric'] = $item_info['is_numeric'];
								$cell['item_name'] = $item_info['item_name'];

								if (!empty($column['prepend_item'])) {
									$cell['item_name'] = $this->extractItemNameSubstring(
										$cell['item_name'], 
										(string)($column['prepend_item_begin'] ?? ''), 
										(string)($column['prepend_item_end'] ?? '')
									);
								}

								// Precision formatting & Display Mode
								$display_as_text = (int)($column['display_value_as'] ?? 0) === 1;

								if ($cell['is_numeric'] && !$display_as_text) {
									$decimals = (int)($column['decimal_places'] ?? 2);
									$cell['value'] = formatAggregatedHistoryValue($cell['raw_value'], $item_info['item'], AGGREGATE_NONE, false, true, [
										'decimals' => $decimals,
										'decimals_exact' => true,
										'small_scientific' => false,
										'zero_as_zero' => false
									]);
								} else {
									$cell['value'] = $item_info['value'];
									$cell['is_numeric'] = false; // Force non-numeric behavior for Text mode
								}


								// Thresholds / Highlights.
								if ($cell['is_numeric'] && !empty($column['thresholds'])) {
									foreach ($column['thresholds'] as $t_idx => $threshold) {
										if ($cell['raw_value'] >= $threshold['threshold']) {
											$cell['threshold_color'] = $threshold['color'];
											$cell['threshold_rank'] = $t_idx;
										}
									}
								}
								elseif (!$cell['is_numeric'] && !empty($column['highlights'])) {
									foreach ($column['highlights'] as $h_idx => $highlight) {
										if ($highlight['pattern'] !== '' && @preg_match('/'.$highlight['pattern'].'/', (string)$cell['value'])) {
											$cell['threshold_color'] = $highlight['color'];
											$cell['threshold_rank'] = $h_idx;
										}
									}
								}

								// Bubble up color if configured.
								if ($cell['threshold_color'] !== '' && (int)($column['apply_to_node'] ?? 0) === 1) {
									$row['bubble_up_colors'][] = [
										'color' => $cell['threshold_color'],
										'rank'  => $cell['threshold_rank'] ?? 0,
										'col'   => $col_index,
										'dir'   => (int)($column['parent_status_priority'] ?? 0)
									];
								}

								// Native Gauge Rendering.
								if (in_array((int)($column['display'] ?? 0), [1, 2])) {
									$cell['gauge_html'] = $this->renderGauge($column, $cell);
								}
							}
						}
						elseif ($column['data'] == Widget::DATA_TEXT) {
							$resolved = isset($text_columns[$col_index][$hostid]) ? $text_columns[$col_index][$hostid] : ($column['text'] ?? '');
							$cell['value'] = $resolved;
							$cell['raw_value'] = $resolved;
						}
						$row['columns'][$col_index] = $cell;
					}

					

					$host_rows[] = $row;
				}
			}
			// Text Expansion Mode: Create one row per text line
			elseif ($text_explode_col_idx !== null && !$show_all_matches) {
				$text_content = isset($text_columns[$text_explode_col_idx][$hostid]) ? $text_columns[$text_explode_col_idx][$hostid] : '';
				// Split by newline and remove empty lines
				$lines = array_filter(array_map('trim', explode("\n", (string)$text_content)), 'strlen');

				$exp_idx = 0;
				foreach ($lines as $line) {
					$exp_idx++;
					$row = $base_row;
					$row['explosion_index'] = $exp_idx;
					$row['exploded_item_name'] = $line;
					$row['columns'] = [];
					$row['bubble_up_colors'] = [];

					foreach ($columns as $col_index => $column) {
						$cell = ['value' => '', 'raw_value' => '', 'is_numeric' => false, 'threshold_color' => ''];

						if ($col_index === $text_explode_col_idx) {
							$cell['value'] = $line;
							$cell['raw_value'] = $line;
						}
						elseif ($column['data'] == Widget::DATA_HOST_NAME) {
							$cell['value'] = $host['name'];
							$cell['raw_value'] = $host['name'];
							if ($row['in_maintenance']) {
								$m_name = isset($maintenances[$host['maintenanceid']]) ? $maintenances[$host['maintenanceid']]['name'] : _('Maintenance');
								$cell['maintenance_icon_html'] = (new CSpan())->addClass('icon-maintenance')->setHint($m_name)->toString();
							}
						}
						// other columns stay empty

						$row['columns'][$col_index] = $cell;
					}
					$host_rows[] = $row;
				}
			}
			// Standard Mode: One row per host
			else {
				$row = $base_row;
				$row['columns'] = [];
				$row['bubble_up_colors'] = [];

				// Pick the first matched item name for the "Item Name" grouping attribute
				if (!empty($host_item_values[$hostid])) {
					$first_item = reset($host_item_values[$hostid]);
					$row['exploded_item_name'] = $first_item['item_name'];
				}

				foreach ($columns as $col_index => $column) {
					$cell = ['value' => '', 'raw_value' => '', 'is_numeric' => false, 'threshold_color' => ''];
					
					switch ($column['data']) {
						case Widget::DATA_HOST_NAME:
							$cell['value'] = $host['name'];
							$cell['raw_value'] = $host['name'];
							if ($row['in_maintenance']) {
								$m_name = isset($maintenances[$host['maintenanceid']]) ? $maintenances[$host['maintenanceid']]['name'] : _('Maintenance');
								$cell['maintenance_icon_html'] = (new CSpan())->addClass('icon-maintenance')->setHint($m_name)->toString();
							}
							break;
							
						case Widget::DATA_ITEM_VALUE:
							$pattern = $column['item'] ?? '';
							if (isset($host_item_values[$hostid][$pattern])) {
								$v = $host_item_values[$hostid][$pattern];
								$cell['raw_value'] = $v['raw_value'];
								$cell['is_numeric'] = $v['is_numeric'];
								$cell['item_name'] = $v['item_name'];

								if (!empty($column['prepend_item'])) {
									$cell['item_name'] = $this->extractItemNameSubstring(
										$cell['item_name'], 
										(string)($column['prepend_item_begin'] ?? ''), 
										(string)($column['prepend_item_end'] ?? '')
									);
								}

								// Precision formatting
								if ($cell['is_numeric']) {
									$decimals = (int)($column['decimal_places'] ?? 2);
									$cell['value'] = formatAggregatedHistoryValue($cell['raw_value'], $v['item'], AGGREGATE_NONE, false, true, [
										'decimals' => $decimals,
										'decimals_exact' => true,
										'small_scientific' => false,
										'zero_as_zero' => false
									]);
								} else {
									$cell['value'] = $v['value'];
								}


								// Thresholds / Highlights.
								$display_as_text = (int)($column['display_value_as'] ?? 0) === 1;

								if ($cell['is_numeric'] && !$display_as_text) {
									if (!empty($column['thresholds'])) {
										foreach ($column['thresholds'] as $t_idx => $threshold) {
											if ($cell['raw_value'] >= $threshold['threshold']) {
												$cell['threshold_color'] = $threshold['color'];
												$cell['threshold_rank'] = $t_idx;
											}
										}
									}
								}
								elseif (!empty($column['highlights'])) {
									foreach ($column['highlights'] as $h_idx => $highlight) {
										if ($highlight['pattern'] !== '' && @preg_match('/'.$highlight['pattern'].'/', (string)$cell['value'])) {
											$cell['threshold_color'] = $highlight['color'];
											$cell['threshold_rank'] = $h_idx;
										}
									}
								}

								// Bubble up color if configured.
								if ($cell['threshold_color'] !== '' && (int)($column['apply_to_node'] ?? 0) === 1) {
									$row['bubble_up_colors'][] = [
										'color' => $cell['threshold_color'],
										'rank'  => $cell['threshold_rank'] ?? 0,
										'col'   => $col_index,
										'dir'   => (int)($column['parent_status_priority'] ?? 0)
									];
								}

								// Native Gauge Rendering.
								if (in_array((int)($column['display'] ?? 0), [1, 2])) {
									$cell['gauge_html'] = $this->renderGauge($column, $cell);
								}
							}
							break;
							
						case Widget::DATA_TEXT:
							$resolved = isset($text_columns[$col_index][$hostid]) ? $text_columns[$col_index][$hostid] : ($column['text'] ?? '');
							$cell['value'] = $resolved;
							$cell['raw_value'] = $resolved;
							break;
					}
					$row['columns'][$col_index] = $cell;
				}

				$host_rows[] = $row;
			}
		}

		// ---- Populate grouping values for all rows ----
		foreach ($host_rows as &$row) {
			$row['grouping_raw_values'] = [];
			$row['grouping_values'] = [];
			$in_maintenance = $row['in_maintenance'];

			foreach ($group_by as $l => $group_row) {
				if ($maintenance_override && $in_maintenance && $l === $m_level) {
					$row['grouping_raw_values'][] = $m_label;
					$row['grouping_values'][] = $m_label;
				} else {
					$res = $this->getGroupAttributeValue($row, $group_row, $host_item_values);
					$row['grouping_raw_values'][] = $res['raw'];
					$row['grouping_values'][] = $res['value'];
				}
			}
		}
		unset($row);

		// ---- Map columnids to current indices for sorting/filtering ----
		$col_id_map = [];
		foreach (array_values($columns) as $idx => $col) {
			if (isset($col['columnid'])) {
				$col_id_map[$col['columnid']] = $idx;
			}
		}

		// ---- Apply global filters ----
		$host_rows = $this->applyGlobalFilters($host_rows, $host_item_values, $col_id_map);

		// ---- Sort and limit ----
		$real_order_col = $order_column;
		if (is_string($order_column) && isset($col_id_map[$order_column])) {
			$real_order_col = $col_id_map[$order_column];
		}

		if ($columns && array_key_exists((int)$real_order_col, $columns)) {
			usort($host_rows, function ($a, $b) use ($real_order_col, $order) {
				$val_a = $a['columns'][$real_order_col]['raw_value'] ?? '';
				$val_b = $b['columns'][$real_order_col]['raw_value'] ?? '';
				$is_num = ($a['columns'][$real_order_col]['is_numeric'] ?? false) && ($b['columns'][$real_order_col]['is_numeric'] ?? false);
				
				if ($is_num) {
					$cmp = (float) $val_a <=> (float) $val_b;
				} else {
					$cmp = strnatcasecmp((string) $val_a, (string) $val_b);
				}

				if ($cmp !== 0) {
					return $order == Widget::ORDER_BOTTOM_N ? $cmp : -$cmp;
				}

				// Tie-breaker 1: Host ID / Row Index
				if ($a['row_index'] !== $b['row_index']) {
					return $a['row_index'] <=> $b['row_index'];
				}

				// Tie-breaker 2: Explosion Index (Preserves order of textarea or item pattern)
				return $a['explosion_index'] <=> $b['explosion_index'];
			});
		}
		$host_rows = array_slice($host_rows, 0, (int)$show_lines);

		// ---- Parse grouping overrides (mappings and inherits) ----
		$group_mappings = [];
		$group_inherits = [];
		$explicit_mapping_levels = [];
		foreach ($group_by as $l => $group_row) {
			$mapping_str = trim((string)($group_row['value_mappings'] ?? ''));
			if ($mapping_str === '') continue;

			if (preg_match('/^INHERIT:(\d+)$/i', $mapping_str, $matches)) {
				$group_inherits[$l] = (int)$matches[1] - 1;
			} else {
				$group_mappings[$l] = $this->parseValueMappings($mapping_str);
				$explicit_mapping_levels[] = $l;
			}
		}

		if ($maintenance_override && $m_label !== '' && $m_color !== '') {
			if (!isset($group_mappings[$m_level])) {
				$group_mappings[$m_level] = [];
			}
			$group_mappings[$m_level][] = [
				'type' => 'STATIC',
				'condition' => $m_label,
				'label' => $m_label,
				'color' => $m_color
			];
		}

		// ---- Build results ----
		$grouped_data = $this->buildGroupedTree($host_rows, $group_by, 0, $group_mappings, $group_inherits, $explicit_mapping_levels, $host_item_values);

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->widget->getName()),
			'columns' => $columns,
			'group_by' => $group_by,
			'grouped_data' => $grouped_data,
			'host_count' => count($host_rows),
			'show_host_count' => $show_host_count,
			'expand_depth' => $expand_depth,
			'remember_expanded' => $remember_expanded,
			'grouping_color_full' => $grouping_color_full,
			'honeycomb_view' => $honeycomb_view,
			'honeycomb_shape' => $honeycomb_shape,
			'honeycomb_primary_label' => $honeycomb_primary_label,
			'honeycomb_secondary_label' => $honeycomb_secondary_label,
			'user' => ['debug_mode' => $this->getDebugMode()]
		]));
	}

	private function applyGlobalFilters(array $host_rows, array $host_item_values, array $col_id_map = []): array {
		$logic = trim((string)($this->fields_values['filter_logic'] ?? ''));
		if ($logic === '') $logic = '1 and 2 and 3';

		$filters = [];
		for ($i = 1; $i <= 3; $i++) {
			$target = $this->fields_values['filter'.$i.'_column'] ?? -1;
			$op = (int)($this->fields_values['filter'.$i.'_op'] ?? Widget::FILTER_OP_NONE);
			if ($op === Widget::FILTER_OP_NONE || $target === -1) {
				$filters[$i] = null;
			} else {
				$filters[$i] = [
					'target' => $target,
					'param'  => (string)($this->fields_values['filter'.$i.'_target_param'] ?? ''),
					'op'     => $op,
					'val'    => (string)($this->fields_values['filter'.$i.'_val'] ?? '')
				];
			}
		}

		if ($filters[1] === null && $filters[2] === null && $filters[3] === null) return $host_rows;

		return array_values(array_filter($host_rows, function ($row) use ($filters, $logic, $host_item_values, $col_id_map) {
			$results = [];
			for ($i = 1; $i <= 3; $i++) {
				if ($filters[$i] === null) { $results[$i] = true; continue; }
				
				$target = $filters[$i]['target'];
				$param = $filters[$i]['param'];
				$op = $filters[$i]['op'];
				$val = $filters[$i]['val'];
				
				$match_values = [];
				$is_numeric = false;

				$resolved_target = $target;
				if (is_string($target) && isset($col_id_map[$target])) {
					$resolved_target = $col_id_map[$target];
				}

				if (is_numeric($resolved_target) && (int)$resolved_target < 100) {
					$cell = $row['columns'][(int)$resolved_target] ?? null;
					if ($cell) { $match_values[] = $cell['raw_value']; $is_numeric = $cell['is_numeric']; }
				} else {
					switch ((int)$resolved_target) {
						case WidgetForm::FILTER_TARGET_HOST_GROUP: $match_values = $row['group_names']; break;
						case WidgetForm::FILTER_TARGET_SEVERITY: $match_values[] = $row['severity']; $is_numeric = true; break;
						case WidgetForm::FILTER_TARGET_TAG_VALUE:
							foreach ($row['tags'] as $tag) {
								if ($param === '' || strcasecmp($tag['tag'], $param) === 0) { $match_values[] = $tag['value']; }
							}
							break;
						case WidgetForm::FILTER_TARGET_INVENTORY:
							$inv_field = $param;
							foreach (Widget::INVENTORY_FIELDS as $k => $l) {
								if (strcasecmp($param, $l) === 0) {
									$inv_field = $k;
									break;
								}
							}
							$match_values[] = $row['inventory'][$inv_field] ?? '';
							break;
						case WidgetForm::FILTER_TARGET_ITEM_VALUE:
							if (isset($host_item_values[$row['hostid']][$param])) {
								$v = $host_item_values[$row['hostid']][$param];
								$match_values[] = $v['raw_value']; $is_numeric = $v['is_numeric'];
							}
							break;
					}
				}

				if (empty($match_values)) {
					$results[$i] = in_array($op, [
						Widget::FILTER_OP_NOT_EQUALS,
						Widget::FILTER_OP_NOT_CONTAINS,
						Widget::FILTER_OP_NOT_EXISTS
					]);
					continue;
				}

				$is_negative_op = in_array($op, [
					Widget::FILTER_OP_NOT_EQUALS,
					Widget::FILTER_OP_NOT_CONTAINS,
					Widget::FILTER_OP_NOT_EXISTS
				]);

				$res = $is_negative_op ? true : false;
				
				foreach ($match_values as $raw) {
					$m_res = false;
					switch ($op) {
						case Widget::FILTER_OP_EQUALS: $m_res = ($is_numeric && is_numeric($val)) ? ((float)$raw == (float)$val) : ((string)$raw === (string)$val); break;
						case Widget::FILTER_OP_NOT_EQUALS: $m_res = ($is_numeric && is_numeric($val)) ? ((float)$raw != (float)$val) : ((string)$raw !== (string)$val); break;
						case Widget::FILTER_OP_GREATER: $m_res = ($is_numeric && (float)$raw > (float)$val); break;
						case Widget::FILTER_OP_LESS: $m_res = ($is_numeric && (float)$raw < (float)$val); break;
						case Widget::FILTER_OP_GREATER_EQUAL: $m_res = ($is_numeric && (float)$raw >= (float)$val); break;
						case Widget::FILTER_OP_LESS_EQUAL: $m_res = ($is_numeric && (float)$raw <= (float)$val); break;
						case Widget::FILTER_OP_CONTAINS: $m_res = (stripos((string)$raw, (string)$val) !== false); break;
						case Widget::FILTER_OP_NOT_CONTAINS: $m_res = (stripos((string)$raw, (string)$val) === false); break;
						case Widget::FILTER_OP_EXISTS: $m_res = ($raw !== '' && $raw !== null); break;
						case Widget::FILTER_OP_NOT_EXISTS: $m_res = ($raw === '' || $raw === null); break;
					}

					if ($is_negative_op) {
						if (!$m_res) {
							$res = false;
							break;
						}
					} else {
						if ($m_res) {
							$res = true;
							break;
						}
					}
				}
				$results[$i] = $res;
			}
			return $this->evaluateLogic($logic, $results);
		}));
	}

	private function evaluateLogic(string $logic, array $results): bool {
		$logic = strtolower(trim($logic));
		if ($logic === '') return true;
		$logic = str_replace(['(', ')'], [' ( ', ' ) '], $logic);
		$tokens = preg_split('/\s+/', $logic, -1, PREG_SPLIT_NO_EMPTY);
		return $this->evalTokens($tokens, $results);
	}

	private function evalTokens(array &$tokens, array $results): bool {
		$values = []; $ops = []; $precedence = ['not' => 3, 'and' => 2, 'or' => 1];
		while (!empty($tokens)) {
			$token = array_shift($tokens);
			if ($token === '(') { $values[] = $this->evalTokens($tokens, $results); }
			elseif ($token === ')') { break; }
			elseif (isset($precedence[$token])) {
				while (!empty($ops) && end($ops) !== '(' && $precedence[end($ops)] >= $precedence[$token]) { $this->applyOp($values, array_pop($ops)); }
				$ops[] = $token;
			} elseif (is_numeric($token)) { $values[] = $results[(int)$token] ?? true; }
		}
		while (!empty($ops)) { $this->applyOp($values, array_pop($ops)); }
		return !empty($values) ? (bool)$values[0] : true;
	}

	private function applyOp(array &$values, string $op): void {
		if ($op === 'not') { $v = array_pop($values); $values[] = !$v; }
		elseif ($op === 'and') { $b = array_pop($values); $a = array_pop($values); $values[] = ($a && $b); }
		elseif ($op === 'or') { $b = array_pop($values); $a = array_pop($values); $values[] = ($a || $b); }
	}

	private function buildGroupedTree(array $hosts, array $group_by, int $level, array $group_mappings = [], array $group_inherits = [], array $explicit_levels = [], array $host_item_values = []): array {
		$total_levels = count($group_by);
		if ($level >= $total_levels) return [['label' => '', 'host_count' => count($hosts), 'hosts' => $hosts, 'children' => []]];
		
		$groups = [];
		$mapped_colors = [];
		foreach ($hosts as $h) {
			$raw_val = $h['grouping_raw_values'][$level] ?? Widget::UNKNOWN_GROUP_LABEL;
			if ($raw_val === '' || $raw_val === null) $raw_val = Widget::UNKNOWN_GROUP_LABEL;
			
			$mapped_label = $h['grouping_values'][$level] ?? $raw_val;
			$mapped_color = '';

			// 1. Mapping lookup (Direct or Wildcard)
			if (isset($group_mappings[$level])) {
				$matched = false;
				$level_rules = $group_mappings[$level];

				foreach ($level_rules as $rule) {
					if ($this->matchRule($rule, $raw_val)) {
						$mapped_label = $rule['label'];
						if ($rule['color'] !== '') {
							$mapped_color = $rule['color'];
						}
						$matched = true;
						break;
					}
				}

				// If overrides are defined for this level but we didn't match, 
				// use default grey to stop parent color inheritance.
				if (!$matched && in_array($level, $explicit_levels)) {
					$mapped_color = '6c6c6c';
				}
			}

			if (!isset($groups[$mapped_label])) {
				$groups[$mapped_label] = [];
				$mapped_colors[$mapped_label] = $mapped_color;
			}
			$groups[$mapped_label][] = $h;
		}

		$group_row = $group_by[$level] ?? [];
		$order_by = (int)($group_row['group_order_by'] ?? Widget::GROUP_ORDER_BY_LABEL);
		$order_dir = (int)($group_row['group_order'] ?? Widget::GROUP_ORDER_ASC);
		$order_pattern = (string)($group_row['group_order_item_pattern'] ?? '');

		$group_sort_values = [];
		foreach ($groups as $mapped_label => $group_hosts) {
			if ($mapped_label === Widget::UNKNOWN_GROUP_LABEL) {
				$group_sort_values[$mapped_label] = null;
				continue;
			}
			if ($order_by == Widget::GROUP_ORDER_BY_HOST_COUNT) {
				$group_sort_values[$mapped_label] = count($group_hosts);
			} elseif ($order_by == Widget::GROUP_ORDER_BY_ITEM_VALUE && $order_pattern !== '') {
				$val = null;
				foreach ($group_hosts as $h) {
					$v = null;
					if (isset($h['exploded_item_pattern']) && $h['exploded_item_pattern'] === $order_pattern) {
						$v = $h['exploded_item_raw'];
					}
					elseif (isset($host_item_values[$h['hostid']][$order_pattern])) {
						$v = $host_item_values[$h['hostid']][$order_pattern]['raw_value'];
					}

					if ($v !== null) {
						if (is_numeric($v)) {
							$v = (float)$v;
						}
						if ($val === null) {
							$val = $v;
						} else {
							if (is_numeric($v) && is_numeric($val)) {
								if ($order_dir == Widget::GROUP_ORDER_DESC) {
									if ((float)$v > (float)$val) $val = $v;
								} else {
									if ((float)$v < (float)$val) $val = $v;
								}
							} else {
								$cmp = strnatcasecmp((string)$v, (string)$val);
								if ($order_dir == Widget::GROUP_ORDER_DESC) {
									if ($cmp > 0) $val = $v;
								} else {
									if ($cmp < 0) $val = $v;
								}
							}
						}
					}
				}
				$group_sort_values[$mapped_label] = $val;
			} else {
				$group_sort_values[$mapped_label] = $mapped_label;
			}
		}

		uksort($groups, function($a, $b) use ($group_sort_values, $order_by, $order_dir) {
			if ($a === Widget::UNKNOWN_GROUP_LABEL) return 1;
			if ($b === Widget::UNKNOWN_GROUP_LABEL) return -1;

			$val_a = $group_sort_values[$a];
			$val_b = $group_sort_values[$b];

			if ($order_by == Widget::GROUP_ORDER_BY_LABEL) {
				$cmp = strnatcasecmp((string)$val_a, (string)$val_b);
				return ($order_dir == Widget::GROUP_ORDER_DESC) ? -$cmp : $cmp;
			}

			if ($val_a === null && $val_b === null) {
				$cmp = strnatcasecmp((string)$a, (string)$b);
				return ($order_dir == Widget::GROUP_ORDER_DESC) ? -$cmp : $cmp;
			}
			if ($val_a === null) return 1;
			if ($val_b === null) return -1;

			if (is_numeric($val_a) && is_numeric($val_b)) {
				$cmp = $val_a <=> $val_b;
			} else {
				$cmp = strnatcasecmp((string)$val_a, (string)$val_b);
			}

			return ($order_dir == Widget::GROUP_ORDER_DESC) ? -$cmp : $cmp;
		});

		$tree = [];
		foreach ($groups as $mapped_label => $group_hosts) {
			$node = ['label' => $mapped_label, 'host_count' => count($group_hosts), 'color' => $mapped_colors[$mapped_label]];

			// 2. Inheritance check (Rollup)
			if ($node['color'] === '' && isset($group_inherits[$level])) {
				$target_level = $group_inherits[$level];
				if (isset($group_mappings[$target_level])) {
					$target_rules = $group_mappings[$target_level];
					
					foreach ($target_rules as $rule) {
						if ($rule['type'] === 'WILDCARD') continue; // Skip wildcard in inheritance
						
						$matched = false;
						foreach ($group_hosts as $h) {
							if ($this->matchRule($rule, $h['grouping_raw_values'][$target_level] ?? '')) {
								$matched = true;
								break;
							}
						}
						if ($matched && ($rule['color'] ?? '') !== '') {
							$node['color'] = $rule['color'];
							break; // Highest priority match found
						}
					}
				}
			}

			// 3. Parent Status Bubbling
			$bubbled = [];
			foreach ($group_hosts as $h) {
				if (!empty($h['bubble_up_colors'])) {
					$bubbled = array_merge($bubbled, $h['bubble_up_colors']);
				}
			}
			
			if ($bubbled) {
				// Group by column
				$by_col = [];
				foreach ($bubbled as $b) {
					$by_col[$b['col']][] = $b;
				}
				
				$winning_colors = [];
				
				foreach ($by_col as $col_idx => $col_colors) {
					$dir = $col_colors[0]['dir']; // 0 = highest matched, 1 = lowest matched
					
					usort($col_colors, function($a, $b) use ($dir) {
						if ($dir === 0) {
							return $b['rank'] <=> $a['rank'];
						} else {
							return $a['rank'] <=> $b['rank'];
						}
					});
					
					$winning_colors[$col_idx] = $col_colors[0]['color'];
				}
				
				if (!empty($winning_colors)) {
					ksort($winning_colors);
					$node['row_color'] = reset($winning_colors);
				}
			}

			if ($level + 1 < $total_levels) {
				$node['children'] = $this->buildGroupedTree($group_hosts, $group_by, $level + 1, $group_mappings, $group_inherits, $explicit_levels, $host_item_values);
				$node['hosts'] = [];
			} else {
				$node['children'] = [];
				$node['hosts'] = $group_hosts;
			}
			$tree[] = $node;
		}
		return $tree;
	}



	private function getGroupAttributeValue(array $row, array $group_row, array $host_item_values): array {
		$raw = '';
		$value = '';
		switch ($group_row['attribute']) {
			case Widget::GROUP_BY_TAG_VALUE:
				$tag_name = (string)($group_row['tag_name'] ?? '');
				foreach ($row['tags'] as $tag) {
					if (strcasecmp($tag['tag'], $tag_name) === 0) { 
						$raw = $tag['value']; 
						$value = $tag['value'];
						break; 
					}
				}
				break;
			case Widget::GROUP_BY_HOST_GROUP: 
				$v = !empty($row['group_names']) ? $row['group_names'][0] : ''; 
				$raw = $v;
				$value = $v;
				break;
			case Widget::GROUP_BY_HOST_INVENTORY: 
				$v = $row['inventory'][$group_row['inventory_field'] ?? ''] ?? ''; 
				$raw = $v;
				$value = $v;
				break;
			case Widget::GROUP_BY_SEVERITY: 
				$v = (string)($row['severity'] ?? 0); 
				$raw = $v;
				$value = $v;
				break;
			case Widget::GROUP_BY_ITEM_VALUE:
				$pattern = (string)($group_row['item_pattern'] ?? '');
				
				if (isset($row['exploded_item_pattern']) && $row['exploded_item_pattern'] === $pattern) {
					$raw = (string)$row['exploded_item_raw'];
					$value = (string)$row['exploded_item_val'];
				}
				elseif (isset($host_item_values[$row['hostid']][$pattern])) {
					$v = $host_item_values[$row['hostid']][$pattern];
					$raw = (string)$v['raw_value'];
					$value = (string)$v['value'];
				}
				break;
			case Widget::GROUP_BY_HOST_NAME:
				$raw = (string)($row['name'] ?? '');
				$value = $raw;
				break;
			case Widget::GROUP_BY_ITEM_NAME:
				$raw = (string)($row['exploded_item_name'] ?? '');
				$value = $raw;
				break;
		}

		if ($raw === '' || $raw === null) {
			$raw = Widget::UNKNOWN_GROUP_LABEL;
			$value = Widget::UNKNOWN_GROUP_LABEL;
		}

		return ['raw' => (string)$raw, 'value' => (string)$value];
	}

	private function extractItemNameSubstring(string $name, string $begin, string $end): string {
		$res = $name;
		if ($begin !== '') {
			$pos = strpos($res, $begin);
			if ($pos !== false) {
				$res = substr($res, $pos + strlen($begin));
			}
		}
		if ($end !== '') {
			$pos = strpos($res, $end);
			if ($pos !== false) {
				$res = substr($res, 0, $pos);
			}
		}
		return $res;
	}

	private function parseValueMappings(string $mapping_str): array {
		if ($mapping_str === '') return [];
		
		static $cache = [];
		if (isset($cache[$mapping_str])) return $cache[$mapping_str];
		
		$result = [];
		$entries = explode(',', $mapping_str);

		foreach ($entries as $entry) {
			$entry = trim($entry);
			if ($entry === '') continue;

			$eq_pos = strpos($entry, '=');
			if ($eq_pos !== false && $eq_pos > 0 && in_array($entry[$eq_pos - 1], ['!', '<', '>'])) {
				// The first '=' is part of an operator (!=, <=, >=). Find the next one.
				$eq_pos = strpos($entry, '=', $eq_pos + 1);
			}

			if ($eq_pos === false) continue;

			$condition = trim(substr($entry, 0, $eq_pos));
			$label_color = trim(substr($entry, $eq_pos + 1));
			
			// NULL keyword handling
			if (strcasecmp($condition, 'NULL') === 0 || strcasecmp($condition, 'EMPTY') === 0) {
				$condition = Widget::UNKNOWN_GROUP_LABEL;
			}

			// Split label and color on LAST ':'
			$color = '';
			$label = $label_color;
			$colon_pos = strrpos($label_color, ':');

			if ($colon_pos !== false) {
				$possible_color = trim(substr($label_color, $colon_pos + 1));
				if (preg_match('/^[0-9A-Fa-f]{6}$/', $possible_color)) {
					$label = trim(substr($label_color, 0, $colon_pos));
					$color = $possible_color;
				}
			}

			if ($label === '') continue;

			$rule = [
				'condition' => $condition,
				'label' => $label,
				'color' => $color,
				'type' => 'STATIC'
			];

			// Detect Rule Type
			if ($condition === '*' || strcasecmp($condition, 'DEFAULT') === 0) {
				$rule['type'] = 'WILDCARD';
			}
			// Operator Check: >=70, <5, !=0
			elseif (preg_match('/^(>=|<=|>|<|!=)\s*(-?\d+(\.\d+)?)$/', $condition, $matches)) {
				$rule['type'] = 'OPERATOR';
				$rule['op'] = $matches[1];
				$rule['val'] = (float)$matches[2];
			}
			// Range Check: 10-20
			elseif (preg_match('/^(-?\d+(\.\d+)?)\s*-\s*(-?\d+(\.\d+)?)$/', $condition, $matches)) {
				$rule['type'] = 'RANGE';
				$rule['min'] = (float)$matches[1];
				$rule['max'] = (float)$matches[4];
			}

			$result[] = $rule;
		}
		
		$cache[$mapping_str] = $result;
		return $result;
	}

	private function matchRule(array $rule, $value): bool {
		$value = (string)$value;

		switch ($rule['type']) {
			case 'WILDCARD':
				return true;

			case 'OPERATOR':
				if (!is_numeric($value)) return false;
				$v = (float)$value;
				switch ($rule['op']) {
					case '>':  return $v > $rule['val'];
					case '>=': return $v >= $rule['val'];
					case '<':  return $v < $rule['val'];
					case '<=': return $v <= $rule['val'];
					case '!=': return $v != $rule['val'];
				}
				return false;

			case 'RANGE':
				if (!is_numeric($value)) return false;
				$v = (float)$value;
				return ($v >= $rule['min'] && $v <= $rule['max']);

			case 'STATIC':
			default:
				return (strcasecmp($rule['condition'], $value) === 0);
		}
	}

	/**
	 * Render a native Zabbix CBarGauge.
	 */
	private function renderGauge(array $column, array $cell): string {
		$min = (float)($column['min'] ?? 0);
		$max = (float)($column['max'] ?? 100);
		$value = (float)$cell['raw_value'];
		$color = $cell['threshold_color'] !== '' ? $cell['threshold_color'] : ($column['base_color'] ?? '4796C4');

		// Ensure dot decimal formatting for web component compatibility
		$f_val = number_format($value, 4, '.', '');
		$f_min = number_format($min, 4, '.', '');
		$f_max = number_format($max, 4, '.', '');

		if ((int)($column['display'] ?? 0) === Widget::DISPLAY_INDICATORS) {
			$f_val = $f_max;
		}

		$bar_gauge = (new CBarGauge())
			->setAttribute('value', $f_val)
			->setAttribute('fill', '#' . $color)
			->setAttribute('min', $f_min)
			->setAttribute('max', $f_max);

		if ((int)($column['display'] ?? 0) === 1) { // Bar
			$bar_gauge->setAttribute('solid', 1);
		}
		// Note: We DO NOT set solid=0 for Indicators, because the native component 
		// treats the presence of the attribute as "True", regardless of value.

		if (!empty($column['thresholds'])) {
			foreach ($column['thresholds'] as $threshold) {
				$t_val = sprintf("%.4f", (float)$threshold['threshold']);
				$bar_gauge->addThreshold($t_val, '#' . $threshold['color']);
			}
		}

		return $bar_gauge->toString();
	}
}
