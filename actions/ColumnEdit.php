<?php declare(strict_types = 0);

namespace Modules\AdvancedHostGrid\Actions;

use CController,
	CControllerResponseData;

use Modules\AdvancedHostGrid\Includes\CWidgetFieldColumnsList;
use Modules\AdvancedHostGrid\Widget;

class ColumnEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'name'            => 'string',
			'data'            => 'int32',
			'text'            => 'string',
			'explode_text'    => 'in 0,1',
			'item'            => 'string',
			'base_color'      => 'string',
			'display'         => 'int32',
			'min'             => 'string',
			'max'             => 'string',
			'thresholds'      => 'array',
			'display_value_as' => 'int32',
			'prepend_item'    => 'in 0,1',
			'prepend_item_begin' => 'string',
			'prepend_item_end' => 'string',
			'apply_to_node'   => 'in 0,1',
			'parent_status_priority' => 'in 0,1',
			'decimal_places'  => 'int32',
			'columnid'        => 'string',
			'thresholds'      => 'array',
			'highlights'      => 'array',
			'edit'            => 'in 1',
			'update'          => 'in 1'
		];

		$ret = $this->validateInput($fields) && $this->validateFields();

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				], JSON_THROW_ON_ERROR)]))->disableView()
			);
		}

		return $ret;
	}

	protected function validateFields(): bool {
		if (!$this->hasInput('update')) {
			return true;
		}

		$input = $this->getInputAll();
		unset($input['edit'], $input['update']);

		$field = new CWidgetFieldColumnsList('columns', '');
		$field->setValue([$input + self::getColumnDefaults()]);

		$errors = $field->validate(true);
		array_map('error', $errors);

		return !$errors;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		$input = $this->getInputAll();
		unset($input['update']);

		if (!$this->hasInput('update')) {
			$data = [
				'action' => $this->getAction(),
				'colors' => Widget::DEFAULT_COLOR_PALETTE,
				'errors' => hasErrorMessages() ? getMessages() : null,
				'user' => [
					'debug_mode' => $this->getDebugMode()
				]
			] + $input + self::getColumnDefaults();

			$this->setResponse(new CControllerResponseData($data));
		}
		else {
			// Sort thresholds by value.
			if (array_key_exists('thresholds', $input)) {
				$thresholds = [];

				foreach ($input['thresholds'] as $threshold) {
					$t_val = trim($threshold['threshold']);

					if ($t_val !== '' && is_numeric($t_val)) {
						$thresholds[] = $threshold;
					}
				}

				usort($thresholds, function ($a, $b) {
					return (float) $a['threshold'] <=> (float) $b['threshold'];
				});

				$input['thresholds'] = $thresholds;
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($input, JSON_THROW_ON_ERROR)]))->disableView()
			);
		}
	}

	private static function getColumnDefaults(): array {
		static $column_defaults;

		if ($column_defaults === null) {
			$column_defaults = [
				'name'            => '',
				'data'            => Widget::DATA_ITEM_VALUE,
				'text'            => '',
				'explode_text'    => 0,
				'item'            => '',
				'base_color'      => '',
				'display'         => Widget::DISPLAY_AS_IS,
				'min'             => '',
				'max'             => '',
				'prepend_item'    => 0,
				'prepend_item_begin' => '',
				'prepend_item_end' => '',
				'apply_to_node'   => 0,
				'parent_status_priority' => 0,
				'decimal_places'  => 2,
				'display_value_as' => 0,
				'columnid'        => '',
				'thresholds'      => [],
				'highlights'      => []
			];
		}

		return $column_defaults;
	}
}
