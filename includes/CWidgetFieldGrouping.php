<?php declare(strict_types = 0);

namespace Modules\AdvancedHostGrid\Includes;

use Modules\AdvancedHostGrid\Widget;
use Zabbix\Widgets\CWidgetField;

/**
 * Custom widget field for nested grouping (up to 3 levels).
 * Each grouping row specifies an attribute type plus a sub-value depending on type.
 */
class CWidgetFieldGrouping extends CWidgetField {

	public const DEFAULT_VIEW = CWidgetFieldGroupingView::class;
	public const DEFAULT_VALUE = [];
	public const MAX_ROWS = 3;

	public function __construct(string $name, ?string $label = null) {
		parent::__construct($name, $label);

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setValidationRules(['type' => API_OBJECTS, 'length' => self::MAX_ROWS, 'fields' => [
				'attribute'       => ['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [
					Widget::GROUP_BY_TAG_VALUE,
					Widget::GROUP_BY_HOST_GROUP,
					Widget::GROUP_BY_SEVERITY,
					Widget::GROUP_BY_HOST_INVENTORY,
					Widget::GROUP_BY_ITEM_VALUE,
					Widget::GROUP_BY_HOST_NAME,
					Widget::GROUP_BY_ITEM_NAME
				])],
				'tag_name'        => ['type' => API_STRING_UTF8, 'length' => $this->getMaxLength()],
				'inventory_field' => ['type' => API_STRING_UTF8, 'length' => 64],
				'item_pattern'    => ['type' => API_STRING_UTF8, 'length' => 255],
				'value_mappings'  => ['type' => API_STRING_UTF8, 'length' => 2048],
				'group_order_by'  => ['type' => API_INT32, 'in' => implode(',', [
					Widget::GROUP_ORDER_BY_LABEL,
					Widget::GROUP_ORDER_BY_ITEM_VALUE,
					Widget::GROUP_ORDER_BY_HOST_COUNT
				])],
				'group_order'     => ['type' => API_INT32, 'in' => implode(',', [
					Widget::GROUP_ORDER_ASC,
					Widget::GROUP_ORDER_DESC
				])],
				'group_order_item_pattern' => ['type' => API_STRING_UTF8, 'length' => 255]
			]]);
	}

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);

		if ($errors) {
			return $errors;
		}

		$group_by = $this->getValue();

		foreach ($group_by as $index => $row) {
			switch ($row['attribute']) {
				case Widget::GROUP_BY_TAG_VALUE:
					if (!array_key_exists('tag_name', $row) || $row['tag_name'] === '') {
						$errors[] = _s('Invalid parameter "%1$s": %2$s.',
							_('Group by').' #'.($index + 1),
							_('tag name cannot be empty')
						);
					}
					break;

				case Widget::GROUP_BY_HOST_INVENTORY:
					if (!array_key_exists('inventory_field', $row) || $row['inventory_field'] === '') {
						$errors[] = _s('Invalid parameter "%1$s": %2$s.',
							_('Group by').' #'.($index + 1),
							_('inventory field cannot be empty')
						);
					}
					break;

				case Widget::GROUP_BY_ITEM_VALUE:
					if (!array_key_exists('item_pattern', $row) || $row['item_pattern'] === '') {
						$errors[] = _s('Invalid parameter "%1$s": %2$s.',
							_('Group by').' #'.($index + 1),
							_('item pattern cannot be empty')
						);
					}
					break;
			}

			if (array_key_exists('group_order_by', $row) && $row['group_order_by'] == Widget::GROUP_ORDER_BY_ITEM_VALUE) {
				if (!array_key_exists('group_order_item_pattern', $row) || $row['group_order_item_pattern'] === '') {
					$errors[] = _s('Invalid parameter "%1$s": %2$s.',
						_('Group by').' #'.($index + 1),
						_('group order item pattern cannot be empty')
					);
				}
			}
		}

		// Check uniqueness.
		$signatures = [];

		foreach ($group_by as $row) {
			$sig = $row['attribute'];

			switch ($row['attribute']) {
				case Widget::GROUP_BY_TAG_VALUE:
					$sig .= '|'.$row['tag_name'];
					break;

				case Widget::GROUP_BY_HOST_INVENTORY:
					$sig .= '|'.$row['inventory_field'];
					break;

				case Widget::GROUP_BY_ITEM_VALUE:
					$sig .= '|'.$row['item_pattern'];
					break;
			}

			$signatures[] = $sig;
		}

		if (count($signatures) != count(array_unique($signatures))) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Group by'), _('rows must be unique'));
		}

		return $errors;
	}

	public function toApi(array &$widget_fields = []): void {
		foreach ($this->getValue() as $index => $value) {
			$widget_fields[] = [
				'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
				'name' => $this->name.'.'.$index.'.attribute',
				'value' => $value['attribute']
			];

			if ($value['attribute'] == Widget::GROUP_BY_TAG_VALUE
					&& array_key_exists('tag_name', $value)) {
				$widget_fields[] = [
					'type' => $this->save_type,
					'name' => $this->name.'.'.$index.'.tag_name',
					'value' => $value['tag_name']
				];
			}

			if ($value['attribute'] == Widget::GROUP_BY_HOST_INVENTORY
					&& array_key_exists('inventory_field', $value)) {
				$widget_fields[] = [
					'type' => $this->save_type,
					'name' => $this->name.'.'.$index.'.inventory_field',
					'value' => $value['inventory_field']
				];
			}

			if ($value['attribute'] == Widget::GROUP_BY_ITEM_VALUE
					&& array_key_exists('item_pattern', $value)) {
				$widget_fields[] = [
					'type' => $this->save_type,
					'name' => $this->name.'.'.$index.'.item_pattern',
					'value' => $value['item_pattern']
				];
			}

			// Value mappings are now supported across all grouping types
			if (array_key_exists('value_mappings', $value) && $value['value_mappings'] !== '') {
				$widget_fields[] = [
					'type' => $this->save_type,
					'name' => $this->name.'.'.$index.'.value_mappings',
					'value' => $value['value_mappings']
				];
			}

			if (array_key_exists('group_order_by', $value)) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
					'name' => $this->name.'.'.$index.'.group_order_by',
					'value' => $value['group_order_by']
				];
			}

			if (array_key_exists('group_order', $value)) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
					'name' => $this->name.'.'.$index.'.group_order',
					'value' => $value['group_order']
				];
			}

			if (array_key_exists('group_order_by', $value) && $value['group_order_by'] == Widget::GROUP_ORDER_BY_ITEM_VALUE
					&& array_key_exists('group_order_item_pattern', $value) && $value['group_order_item_pattern'] !== '') {
				$widget_fields[] = [
					'type' => $this->save_type,
					'name' => $this->name.'.'.$index.'.group_order_item_pattern',
					'value' => $value['group_order_item_pattern']
				];
			}
		}
	}
}
