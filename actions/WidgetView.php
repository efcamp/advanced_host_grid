<?php declare(strict_types = 0);

namespace Modules\AdvancedHostGrid\Actions;

use API,
	CControllerDashboardWidgetView,
	CControllerResponseData,
	CMenuPopupHelper,
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

		if (!$this->isTemplateDashboard()) {
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
		}
		$item_patterns = array_unique($item_patterns);

		$host_item_values = [];
		if ($item_patterns) {
			foreach ($item_patterns as $pattern) {
				$db_items = API::Item()->get([
					'output' => ['itemid', 'hostid', 'value_type', 'name', 'units', 'valuemapid'],
					'selectValueMap' => ['mappings'],
					'hostids' => $host_ids,
					'search' => ['name' => $pattern],
					'searchWildcardsEnabled' => true,
					'searchByAny' => true,
					'webitems' => true,
					'filter' => ['value_type' => [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_TEXT]],
					'sortfield' => 'name',
					'limit' => count($host_ids) * 10
				]);

				if ($db_items) {
					$items_by_host = [];
					foreach ($db_items as $item) {
						if (!array_key_exists($item['hostid'], $items_by_host)) {
							$items_by_host[$item['hostid']] = $item;
						}
					}
					foreach ($items_by_host as $hostid => $item) {
						$history = API::History()->get([
							'output' => ['value'],
							'itemids' => [$item['itemid']],
							'history' => $item['value_type'],
							'sortfield' => 'clock',
							'sortorder' => ZBX_SORT_DOWN,
							'limit' => 1
						]);
						if ($history) {
							$value = $history[0]['value'];
							if (in_array($item['value_type'], [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT])) {
								$converted = formatHistoryValue($value, $item);
								$host_item_values[$hostid][$pattern] = ['value' => $converted, 'raw_value' => $value, 'is_numeric' => true];
							} else {
								$host_item_values[$hostid][$pattern] = ['value' => $value, 'raw_value' => $value, 'is_numeric' => false];
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

		// ---- Build host data rows ----
		$host_rows = [];
		foreach ($hosts as $hostid => $host) {
			$in_maintenance = (int)$host['maintenance_status'] === HOST_MAINTENANCE_STATUS_ON;
			
			$row = [
				'hostid' => $hostid,
				'name' => $host['name'],
				'host' => $host['host'],
				'group_names' => array_column($host['hostgroups'] ?? [], 'name'),
				'tags' => $host['tags'] ?? [],
				'inventory' => $host['inventory'] ?? [],
				'severity' => $host_severities[$hostid] ?? 0,
				'in_maintenance' => $in_maintenance,
				'columns' => []
			];

			foreach ($columns as $col_index => $column) {
				$cell = ['value' => '', 'raw_value' => '', 'is_numeric' => false];
				
				switch ($column['data']) {
					case Widget::DATA_HOST_NAME:
						$cell['value'] = $host['name'];
						$cell['raw_value'] = $host['name'];
						$cell['menu_popup'] = CMenuPopupHelper::getHost($hostid);
						if ($in_maintenance) {
							$m_name = isset($maintenances[$host['maintenanceid']]) ? $maintenances[$host['maintenanceid']]['name'] : _('Maintenance');
							$cell['maintenance_icon_html'] = (new CSpan())
								->addClass('icon-maintenance')
								->setHint($m_name)
								->toString();
						}
						break;
						
					case Widget::DATA_ITEM_VALUE:
						$pattern = $column['item'] ?? '';
						if (isset($host_item_values[$hostid][$pattern])) {
							$v = $host_item_values[$hostid][$pattern];
							$cell['value'] = $v['value'];
							$cell['raw_value'] = $v['raw_value'];
							$cell['is_numeric'] = $v['is_numeric'];
						}
						break;
						
					case Widget::DATA_TEXT:
						$resolved = $this->resolveTextMacros($column['text'] ?? '', $host);
						$cell['value'] = $resolved;
						$cell['raw_value'] = $resolved;
						break;
				}
				$row['columns'][$col_index] = $cell;
			}

			$row['grouping_raw_values'] = [];
			$row['grouping_values'] = [];
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
			$host_rows[] = $row;
		}

		// ---- Apply global filters ----
		$host_rows = $this->applyGlobalFilters($host_rows, $host_item_values);

		// ---- Sort and limit ----
		if ($columns && array_key_exists((int)$order_column, $columns)) {
			usort($host_rows, function ($a, $b) use ($order_column, $order) {
				$val_a = $a['columns'][$order_column]['raw_value'] ?? '';
				$val_b = $b['columns'][$order_column]['raw_value'] ?? '';
				$is_num = ($a['columns'][$order_column]['is_numeric'] ?? false) && ($b['columns'][$order_column]['is_numeric'] ?? false);
				if ($is_num) {
					$cmp = (float) $val_a <=> (float) $val_b;
					return $order == Widget::ORDER_BOTTOM_N ? $cmp : -$cmp;
				}
				$cmp = strnatcasecmp((string) $val_a, (string) $val_b);
				return $order == Widget::ORDER_BOTTOM_N ? -$cmp : $cmp;
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

		// Pre-inject maintenance color for rollup
		if ($maintenance_override && $m_label !== '' && $m_color !== '') {
			if (!isset($group_mappings[$m_level])) $group_mappings[$m_level] = [];
			$group_mappings[$m_level][$m_label] = ['label' => $m_label, 'color' => $m_color];
		}

		// ---- Build results ----
		$grouped_data = $this->buildGroupedTree($host_rows, count($group_by), 0, $group_mappings, $group_inherits, $explicit_mapping_levels);

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->widget->getName()),
			'columns' => $columns,
			'group_by' => $group_by,
			'grouped_data' => $grouped_data,
			'host_count' => count($host_rows),
			'user' => ['debug_mode' => $this->getDebugMode()]
		]));
	}

	private function applyGlobalFilters(array $host_rows, array $host_item_values): array {
		$logic = trim((string)($this->fields_values['filter_logic'] ?? ''));
		if ($logic === '') $logic = '1 and 2 and 3';

		$filters = [];
		for ($i = 1; $i <= 3; $i++) {
			$target = (int)($this->fields_values['filter'.$i.'_column'] ?? -1);
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

		return array_values(array_filter($host_rows, function ($row) use ($filters, $logic, $host_item_values) {
			$results = [];
			for ($i = 1; $i <= 3; $i++) {
				if ($filters[$i] === null) { $results[$i] = true; continue; }
				
				$target = $filters[$i]['target'];
				$param = $filters[$i]['param'];
				$op = $filters[$i]['op'];
				$val = $filters[$i]['val'];
				
				$match_values = [];
				$is_numeric = false;

				if ($target < 100) {
					$cell = $row['columns'][$target] ?? null;
					if ($cell) { $match_values[] = $cell['raw_value']; $is_numeric = $cell['is_numeric']; }
				} else {
					switch ($target) {
						case WidgetForm::FILTER_TARGET_HOST_GROUP: $match_values = $row['group_names']; break;
						case WidgetForm::FILTER_TARGET_SEVERITY: $match_values[] = $row['severity']; $is_numeric = true; break;
						case WidgetForm::FILTER_TARGET_TAG_VALUE:
							foreach ($row['tags'] as $tag) {
								if ($param === '' || strcasecmp($tag['tag'], $param) === 0) { $match_values[] = $tag['value']; }
							}
							break;
						case WidgetForm::FILTER_TARGET_INVENTORY:
							$match_values[] = $row['inventory'][$param] ?? '';
							break;
						case WidgetForm::FILTER_TARGET_ITEM_VALUE:
							if (isset($host_item_values[$row['hostid']][$param])) {
								$v = $host_item_values[$row['hostid']][$param];
								$match_values[] = $v['raw_value']; $is_numeric = $v['is_numeric'];
							}
							break;
					}
				}

				if (empty($match_values) && ($op !== Widget::FILTER_OP_NOT_EQUALS && $op !== Widget::FILTER_OP_NOT_CONTAINS)) { 
					$results[$i] = false; continue; 
				}

				$res = false;
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
					}
					if ($m_res) { $res = true; break; }
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

	private function buildGroupedTree(array $hosts, int $total_levels, int $level, array $group_mappings = [], array $group_inherits = [], array $explicit_levels = []): array {
		if ($level >= $total_levels) return [['label' => '', 'host_count' => count($hosts), 'hosts' => $hosts, 'children' => []]];
		
		$groups = [];
		foreach ($hosts as $h) {
			$raw_val = $h['grouping_raw_values'][$level] ?? Widget::UNKNOWN_GROUP_LABEL;
			if ($raw_val === '' || $raw_val === null) $raw_val = Widget::UNKNOWN_GROUP_LABEL;
			if (!isset($groups[$raw_val])) $groups[$raw_val] = [];
			$groups[$raw_val][] = $h;
		}

		uksort($groups, function($a, $b) { 
			if ($a === Widget::UNKNOWN_GROUP_LABEL) return 1; if ($b === Widget::UNKNOWN_GROUP_LABEL) return -1;
			return strnatcasecmp($a, $b); 
		});

		$tree = [];
		foreach ($groups as $raw_value => $group_hosts) {
			$formatted_value = $group_hosts[0]['grouping_values'][$level] ?? $raw_value;
			$node = ['label' => $formatted_value, 'host_count' => count($group_hosts), 'color' => ''];
			
			// 1. Mapping lookup (Direct or Wildcard)
			if (isset($group_mappings[$level])) {
				$level_map = $group_mappings[$level];
				$matched = false;

				if (isset($level_map[$raw_value])) {
					// Specific match
					$node['label'] = $level_map[$raw_value]['label'];
					$node['color'] = $level_map[$raw_value]['color'];
					$matched = true;
				}
				elseif (isset($level_map['*'])) {
					// Wildcard match
					if ($level_map['*']['label'] !== '*') {
						$node['label'] = $level_map['*']['label'];
					}
					$node['color'] = $level_map['*']['color'];
					$matched = true;
				}

				// If overrides are defined for this level but we didn't match, 
				// use default grey to stop parent color inheritance.
				if (!$matched && in_array($level, $explicit_levels)) {
					$node['color'] = '6c6c6c';
				}
			}

			// 2. Inheritance check (Rollup)
			if ($node['color'] === '' && isset($group_inherits[$level])) {
				$target_level = $group_inherits[$level];
				if (isset($group_mappings[$target_level])) {
					$target_mapping = $group_mappings[$target_level];
					// Use native PHP associative array order for priority
					foreach ($target_mapping as $raw_val => $mapping) {
						if ($raw_val === '*') continue; // Skip wildcard in inheritance to avoid mass-pollution
						
						$matched = false;
						foreach ($group_hosts as $h) {
							if ((string)($h['grouping_raw_values'][$target_level] ?? '') === (string)$raw_val) {
								$matched = true;
								break;
							}
						}
						if ($matched && ($mapping['color'] ?? '') !== '') {
							$node['color'] = $mapping['color'];
							break; // Highest priority match found
						}
					}
				}
			}

			if ($level + 1 < $total_levels) {
				$node['children'] = $this->buildGroupedTree($group_hosts, $total_levels, $level + 1, $group_mappings, $group_inherits, $explicit_levels);
				$node['hosts'] = [];
			} else {
				$node['children'] = [];
				$node['hosts'] = $group_hosts;
			}
			$tree[] = $node;
		}
		return $tree;
	}

	private function resolveTextMacros(string $text, array $host): string {
		return str_replace(['{HOST.NAME}', '{HOST.HOST}', '{HOST.ID}'], [$host['name'], $host['host'], $host['hostid']], $text);
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
				if (isset($host_item_values[$row['hostid']][$pattern])) {
					$v = $host_item_values[$row['hostid']][$pattern];
					$raw = (string)$v['raw_value'];
					$value = (string)$v['value'];
				}
				break;
		}

		if ($raw === '' || $raw === null) {
			$raw = Widget::UNKNOWN_GROUP_LABEL;
			$value = Widget::UNKNOWN_GROUP_LABEL;
		}

		return ['raw' => (string)$raw, 'value' => (string)$value];
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

			// Match stable format: value=Label:Color
			$eq_pos = strpos($entry, '=');
			if ($eq_pos === false) continue;

			$raw_value = trim(substr($entry, 0, $eq_pos));
			$label_color = trim(substr($entry, $eq_pos + 1));
			
			// NULL keyword handling
			if (strcasecmp($raw_value, 'NULL') === 0 || strcasecmp($raw_value, 'EMPTY') === 0) {
				$raw_value = Widget::UNKNOWN_GROUP_LABEL;
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

			if ($label !== '') {
				$result[$raw_value] = ['label' => $label, 'color' => $color];
			}
		}
		
		$cache[$mapping_str] = $result;
		return $result;
	}
}
