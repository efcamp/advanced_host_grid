<?php declare(strict_types = 0);

namespace Modules\AdvancedHostGrid\Includes;

use Modules\AdvancedHostGrid\Widget;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldCheckBox,
	CWidgetFieldIntegerBox,
	CWidgetFieldMultiSelectGroup,
	CWidgetFieldMultiSelectHost,
	CWidgetFieldMultiSelectOverrideHost,
	CWidgetFieldRadioButtonList,
	CWidgetFieldSelect,
	CWidgetFieldTags,
	CWidgetFieldTextBox
};

/**
 * Advanced Host Grid widget form.
 */
class WidgetForm extends CWidgetForm {

	public const FILTER_TARGET_HOST_GROUP = 100;
	public const FILTER_TARGET_TAG_VALUE  = 101;
	public const FILTER_TARGET_INVENTORY  = 102;
	public const FILTER_TARGET_SEVERITY   = 103;
	public const FILTER_TARGET_ITEM_VALUE = 104;

	private const DEFAULT_ORDER_COLUMN = 0;
	private const LINES_MIN = 1;
	private const LINES_MAX = 9999;
	private const LINES_DEFAULT = 100;

	private array $field_column_values = [];

	protected function normalizeValues(array $values): array {
		$values = parent::normalizeValues($values);

		// Merge threshold sub-fields into columns.
		if (array_key_exists('columnsthresholds', $values)) {
			foreach ($values['columnsthresholds'] as $column_index => $fields) {
				$values['columns'][$column_index]['thresholds'] = [];

				foreach ($fields as $field_key => $field_values) {
					foreach ($field_values as $value_index => $value) {
						$values['columns'][$column_index]['thresholds'][$value_index][$field_key] = $value;
					}
				}
			}
		}

		// Apply sortable changes to data.
		if (array_key_exists('sortorder', $values)) {
			if (array_key_exists('column', $values) && array_key_exists('columns', $values['sortorder'])) {
				$new_column = array_search($values['column'], $values['sortorder']['columns'], true);
				if ($new_column !== false) {
					$values['column'] = $new_column;
				}
			}

			for ($i = 1; $i <= 3; $i++) {
				if (array_key_exists('filter'.$i.'_column', $values) && array_key_exists('columns', $values['sortorder'])) {
					$target = $values['filter'.$i.'_column'];
					if (is_numeric($target) && (int)$target < 100) {
						$new_target = array_search($target, $values['sortorder']['columns'], true);
						if ($new_target !== false) {
							$values['filter'.$i.'_column'] = $new_target;
						}
					}
				}
			}

			foreach ($values['sortorder'] as $key => $sortorder) {
				if (!array_key_exists($key, $values)) {
					continue;
				}

				$sorted = [];

				foreach ($sortorder as $index) {
					$sorted[] = $values[$key][$index];
				}

				$values[$key] = $sorted;
			}
		}

		// Build column label map.
		if (array_key_exists('columns', $values)) {
			foreach ($values['columns'] as $key => $value) {
				$value['name'] = (string)($value['name'] ?? '');
				$value['is_hidden'] = (int)($value['is_hidden'] ?? 0);
				$value['threshold_color_cell'] = (int)($value['threshold_color_cell'] ?? 1);
				$values['columns'][$key] = $value;

				$col_id = (int)$key;

				switch ($value['data']) {
					case Widget::DATA_ITEM_VALUE:
						$this->field_column_values[$col_id] = $value['name'] === ''
							? ($value['item'] ?? '')
							: $value['name'];
						break;

					case Widget::DATA_HOST_NAME:
						$this->field_column_values[$col_id] = $value['name'] === ''
							? _('Host name')
							: $value['name'];
						break;

					case Widget::DATA_TEXT:
						$this->field_column_values[$col_id] = $value['name'] === ''
							? ($value['text'] ?? '')
							: $value['name'];
						break;
				}
			}
		}

		return $values;
	}

	public function addFields(): self {
		$this
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldMultiSelectGroup('groupids', _('Host groups'))
			)
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldMultiSelectHost('hostids', _('Hosts'))
			)
			->addField($this->isTemplateDashboard()
				? null
				: (new CWidgetFieldRadioButtonList('evaltype', _('Host tags'), [
					TAG_EVAL_TYPE_AND_OR => _('And/Or'),
					TAG_EVAL_TYPE_OR => _('Or')
				]))->setDefault(TAG_EVAL_TYPE_AND_OR)
			)
			->addField($this->isTemplateDashboard()
				? null
				: new CWidgetFieldTags('tags')
			)
			->addField(
				(new CWidgetFieldCheckBox('show_host_count', _('Show total host count')))
					->setDefault(0)
			)
			->addField(
				(new CWidgetFieldCheckBox('show_all_matches', _('Expand item patterns')))
					->setDefault(0)
			)
			->addField(
				(new CWidgetFieldCheckBox('honeycomb_view', _('Honeycomb view')))
					->setDefault(0)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('honeycomb_shape', _('Shape'), [
					0 => _('Hexagon'),
					1 => _('Square')
				]))->setDefault(0)
			)
			->addField(
				(new CWidgetFieldSelect('honeycomb_primary_label', _('Primary label'), [
					-1 => _('None'),
					0 => _('Host name'),
					1 => _('Item name'),
					2 => _('Item value')
				]))->setDefault(2)
			)
			->addField(
				(new CWidgetFieldSelect('honeycomb_secondary_label', _('Secondary label'), [
					-1 => _('None'),
					0 => _('Host name'),
					1 => _('Item name'),
					2 => _('Item value')
				]))->setDefault(0)
			)
			->addField(
				(new CWidgetFieldIntegerBox('expand_depth', _('Expand tree depth'), 0, 10))
					->setDefault(0)
			)
			->addField(
				(new CWidgetFieldCheckBox('remember_expanded', _('Remember expanded groups')))
					->setDefault(0)
			)
			->addField(
				(new CWidgetFieldCheckBox('maintenance',
					$this->isTemplateDashboard()
						? _('Show data in maintenance')
						: _('Show hosts in maintenance')
				))->setDefault(0)
			)
			->addField(
				(new CWidgetFieldCheckBox('maintenance_override', _('Maintenance grouping override')))
					->setDefault(0)
			)
			->addField(
				(new CWidgetFieldTextBox('maintenance_override_settings', _('Maintenance override (level:label:color)')))
					->setDefault('1:Maintenance:6c6c6c')
			)
			->addField(
				(new CWidgetFieldColumnsList('columns', _('Columns')))
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
					->setDefault([])
			)
			->addField(
				(new CWidgetFieldGrouping('group_by', _('Group by')))
					->setDefault([])
			)
			->addField(
				(new CWidgetFieldCheckBox('grouping_color_full', _('Color entire group label')))
					->setDefault(0)
			)
			->addField(
				(new CWidgetFieldSelect('column', _('Order by'), $this->field_column_values))
					->setDefault($this->field_column_values
						? self::DEFAULT_ORDER_COLUMN
						: CWidgetFieldSelect::DEFAULT_VALUE
					)
					->setFlags(CWidgetField::FLAG_LABEL_ASTERISK)
			)
			->addField(
				(new CWidgetFieldRadioButtonList('order', _('Order'), [
					Widget::ORDER_TOP_N => _('Top N'),
					Widget::ORDER_BOTTOM_N => _('Bottom N')
				]))->setDefault(Widget::ORDER_TOP_N)
			)
			->addField($this->isTemplateDashboard()
				? null
				: (new CWidgetFieldIntegerBox('show_lines', _('Host limit'),
					self::LINES_MIN, self::LINES_MAX
				))
					->setDefault(self::LINES_DEFAULT)
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			);

		// Global Filter targets
		$targets = [
			self::FILTER_TARGET_HOST_GROUP => _('Host group'),
			self::FILTER_TARGET_TAG_VALUE  => _('Tag Value'),
			self::FILTER_TARGET_INVENTORY  => _('Inventory'),
			self::FILTER_TARGET_SEVERITY   => _('Severity'),
			self::FILTER_TARGET_ITEM_VALUE => _('Item Value')
		];
		$targets += $this->field_column_values;

		// Global Filters.
		for ($i = 1; $i <= 3; $i++) {
			$this
				->addField(
					(new CWidgetFieldSelect('filter'.$i.'_column', _('Filter target').' '.$i, $targets))
				)
				->addField(
					new CWidgetFieldTextBox('filter'.$i.'_target_param', _('Target parameter').' '.$i)
				)
				->addField(
					(new CWidgetFieldSelect('filter'.$i.'_op', _('Operator').' '.$i, [
						Widget::FILTER_OP_NONE         => _('None'),
						Widget::FILTER_OP_EQUALS       => _('Equals'),
						Widget::FILTER_OP_NOT_EQUALS   => _('Not equals'),
						Widget::FILTER_OP_GREATER      => _('Greater than'),
						Widget::FILTER_OP_LESS         => _('Less than'),
						Widget::FILTER_OP_GREATER_EQUAL => _('Greater or equal'),
						Widget::FILTER_OP_LESS_EQUAL   => _('Less or equal'),
						Widget::FILTER_OP_CONTAINS     => _('Contains'),
						Widget::FILTER_OP_NOT_CONTAINS => _('Not contains'),
						Widget::FILTER_OP_EXISTS       => _('Exists'),
						Widget::FILTER_OP_NOT_EXISTS   => _('Not exists')
					]))->setDefault(Widget::FILTER_OP_NONE)
				)
				->addField(
					(new CWidgetFieldTextBox('filter'.$i.'_val', _('Value').' '.$i))
				);
		}

		$this->addField(
			new CWidgetFieldTextBox('filter_logic', _('Filter by'))
		);

		return $this->addField(
			new CWidgetFieldMultiSelectOverrideHost()
		);
	}
}
