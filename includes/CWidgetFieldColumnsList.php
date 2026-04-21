<?php declare(strict_types = 0);

namespace Modules\AdvancedHostGrid\Includes;

use Modules\AdvancedHostGrid\Widget;
use Zabbix\Widgets\CWidgetField;

/**
 * Custom widget field for configurable columns.
 */
class CWidgetFieldColumnsList extends CWidgetField {

	public const DEFAULT_VIEW = CWidgetFieldColumnsListView::class;
	public const DEFAULT_VALUE = [];

	// Maximum columns.
	public const MAX_COLUMNS = 20;

	public function __construct(string $name, ?string $label = null) {
		parent::__construct($name, $label);

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setValidationRules(['type' => API_OBJECTS, 'length' => self::MAX_COLUMNS, 'fields' => [
				'data'        => ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [
					Widget::DATA_HOST_NAME,
					Widget::DATA_ITEM_VALUE,
					Widget::DATA_TEXT
				])],
				'name'        => ['type' => API_STRING_UTF8, 'length' => 255],
				'item'        => ['type' => API_STRING_UTF8, 'length' => 255],
				'display'     => ['type' => API_INT32, 'in' => implode(',', [
					Widget::DISPLAY_AS_IS,
					Widget::DISPLAY_BAR,
					Widget::DISPLAY_INDICATORS
				])],
				'base_color'  => ['type' => API_STRING_UTF8, 'length' => 6],
				'min'         => ['type' => API_STRING_UTF8, 'length' => 255],
				'max'         => ['type' => API_STRING_UTF8, 'length' => 255],
				'text'        => ['type' => API_STRING_UTF8, 'length' => 255],
				'display_value_as' => ['type' => API_INT32, 'in' => '0,1'],
				'prepend_item' => ['type' => API_INT32, 'in' => '0,1'],
				'prepend_item_begin' => ['type' => API_STRING_UTF8, 'length' => 255],
				'prepend_item_end' => ['type' => API_STRING_UTF8, 'length' => 255],
				'apply_to_node' => ['type' => API_INT32, 'in' => '0,1'],
				'parent_status_priority' => ['type' => API_INT32, 'in' => '0,1'],
				'decimal_places' => ['type' => API_INT32, 'in' => '0:10'],
				'columnid'    => ['type' => API_STRING_UTF8, 'length' => 255],
				'thresholds'  => ['type' => API_OBJECTS, 'fields' => [
					'color'     => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 6],
					'threshold' => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255]
				]],
				'highlights'  => ['type' => API_OBJECTS, 'fields' => [
					'color'     => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 6],
					'pattern'   => ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => 255]
				]]
			]]);
	}

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		if ($errors) {
			return $errors;
		}

		$columns = $this->getValue();

		foreach ($columns as $index => $column) {
			if ($column['data'] == Widget::DATA_ITEM_VALUE) {
				if (!array_key_exists('item', $column) || $column['item'] === '') {
					$errors[] = _s('Invalid parameter "%1$s": %2$s.',
						_('Columns').' #'.($index + 1),
						_('item pattern cannot be empty')
					);
				}
			}
		}

		return $errors;
	}

	public function toApi(array &$widget_fields = []): void {
		$columns = $this->getValue();

		foreach ($columns as $index => $column) {
			$widget_fields[] = [
				'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
				'name' => $this->name.'.'.$index.'.data',
				'value' => $column['data']
			];

			if (array_key_exists('name', $column) && $column['name'] !== '') {
				$widget_fields[] = [
					'type' => $this->save_type,
					'name' => $this->name.'.'.$index.'.name',
					'value' => $column['name']
				];
			}

			if (array_key_exists('item', $column) && $column['item'] !== '') {
				$widget_fields[] = [
					'type' => $this->save_type,
					'name' => $this->name.'.'.$index.'.item',
					'value' => $column['item']
				];
			}

			if (array_key_exists('display', $column)) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
					'name' => $this->name.'.'.$index.'.display',
					'value' => $column['display']
				];
			}

			if (array_key_exists('base_color', $column) && $column['base_color'] !== '') {
				$widget_fields[] = [
					'type' => $this->save_type,
					'name' => $this->name.'.'.$index.'.base_color',
					'value' => $column['base_color']
				];
			}

			if (array_key_exists('min', $column) && $column['min'] !== '') {
				$widget_fields[] = [
					'type' => $this->save_type,
					'name' => $this->name.'.'.$index.'.min',
					'value' => $column['min']
				];
			}

			if (array_key_exists('max', $column) && $column['max'] !== '') {
				$widget_fields[] = [
					'type' => $this->save_type,
					'name' => $this->name.'.'.$index.'.max',
					'value' => $column['max']
				];
			}

			if (array_key_exists('text', $column) && $column['text'] !== '') {
				$widget_fields[] = [
					'type' => $this->save_type,
					'name' => $this->name.'.'.$index.'.text',
					'value' => $column['text']
				];
			}

			if (array_key_exists('prepend_item', $column)) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
					'name' => $this->name.'.'.$index.'.prepend_item',
					'value' => $column['prepend_item']
				];
			}
			if (array_key_exists('prepend_item_begin', $column) && $column['prepend_item_begin'] !== '') {
				$widget_fields[] = [
					'type' => $this->save_type,
					'name' => $this->name.'.'.$index.'.prepend_item_begin',
					'value' => $column['prepend_item_begin']
				];
			}
			if (array_key_exists('prepend_item_end', $column) && $column['prepend_item_end'] !== '') {
				$widget_fields[] = [
					'type' => $this->save_type,
					'name' => $this->name.'.'.$index.'.prepend_item_end',
					'value' => $column['prepend_item_end']
				];
			}

			if (array_key_exists('display_value_as', $column)) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
					'name' => $this->name.'.'.$index.'.display_value_as',
					'value' => $column['display_value_as']
				];
			}

			if (array_key_exists('apply_to_node', $column)) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
					'name' => $this->name.'.'.$index.'.apply_to_node',
					'value' => $column['apply_to_node']
				];
			}

			if (array_key_exists('parent_status_priority', $column)) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
					'name' => $this->name.'.'.$index.'.parent_status_priority',
					'value' => $column['parent_status_priority']
				];
			}

			if (array_key_exists('decimal_places', $column)) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
					'name' => $this->name.'.'.$index.'.decimal_places',
					'value' => $column['decimal_places']
				];
			}

			if (array_key_exists('columnid', $column) && $column['columnid'] !== '') {
				$widget_fields[] = [
					'type' => $this->save_type,
					'name' => $this->name.'.'.$index.'.columnid',
					'value' => $column['columnid']
				];
			}

			if (array_key_exists('thresholds', $column)) {
				foreach ($column['thresholds'] as $t_index => $threshold) {
					$widget_fields[] = [
						'type' => $this->save_type,
						'name' => $this->name.'.'.$index.'.thresholds.'.$t_index.'.color',
						'value' => $threshold['color']
					];
					$widget_fields[] = [
						'type' => $this->save_type,
						'name' => $this->name.'.'.$index.'.thresholds.'.$t_index.'.threshold',
						'value' => $threshold['threshold']
					];
				}
			}

			if (array_key_exists('highlights', $column)) {
				foreach ($column['highlights'] as $h_index => $highlight) {
					$widget_fields[] = [
						'type' => $this->save_type,
						'name' => $this->name.'.'.$index.'.highlights.'.$h_index.'.color',
						'value' => $highlight['color']
					];
					$widget_fields[] = [
						'type' => $this->save_type,
						'name' => $this->name.'.'.$index.'.highlights.'.$h_index.'.pattern',
						'value' => $highlight['pattern']
					];
				}
			}
		}
	}
}
