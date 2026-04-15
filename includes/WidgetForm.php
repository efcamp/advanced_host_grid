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
			if (array_key_exists('column', $values) && (int)$values['column'] < 100 && array_key_exists('columns', $values['sortorder'])) {
				$values['column'] = (int) array_search($values['column'], $values['sortorder']['columns'], true);
			}

			// Also update global filters column references if they were swapped.
			for ($i = 1; $i <= 3; $i++) {
				$f_col = $values['filter'.$i.'_column'] ?? '';
				if ($f_col !== '' && (int)$f_col < 100 && array_key_exists('columns', $values['sortorder'])) {
					$new_idx = array_search((string)$f_col, $values['sortorder']['columns'], true);
					if ($new_idx !== false) {
						$values['filter'.$i.'_column'] = (string)$new_idx;
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
				$value['name'] = trim($value['name'] ?? '');

				switch ($value['data']) {
					case Widget::DATA_ITEM_VALUE:
						$this->field_column_values[$key] = $value['name'] === ''
							? ($value['item'] ?? '')
							: $value['name'];
						break;

					case Widget::DATA_HOST_NAME:
						$this->field_column_values[$key] = $value['name'] === ''
							? _('Host name')
							: $value['name'];
						break;

					case Widget::DATA_TEXT:
						$this->field_column_values[$key] = $value['name'] === ''
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
						Widget::FILTER_OP_NOT_CONTAINS => _('Not contains')
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
