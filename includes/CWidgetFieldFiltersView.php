<?php declare(strict_types = 0);
 
namespace Modules\AdvancedHostGrid\Includes;
 
use Modules\AdvancedHostGrid\Widget;
use Zabbix\Widgets\CWidgetField;
use CWidgetFieldView;
use CDiv, CTable, CRow, CCol, CLabel, CSelect, CTextBox, CTextArea;
 
/**
 * View for the global filter configuration with expanded targets.
 */
class CWidgetFieldFiltersView extends CWidgetFieldView {
 
	protected array $fields;

	public function __construct(CWidgetField $primary_field, array $fields) {
		$this->field = $primary_field;
		$this->fields = $fields;
	}
 
	public function getView(): CDiv {
		$table = (new CTable())
			->setId('global-filters-table')
			->addClass('table-initial-width')
			->setHeader([
				_('No.'),
				_('Filter target'),
				_('Parameter'),
				_('Operator'),
				_('Value')
			]);
 
		for ($i = 1; $i <= 3; $i++) {
			$col_field = $this->fields['filter'.$i.'_column'];
			$param_field = $this->fields['filter'.$i.'_target_param'];
			$op_field = $this->fields['filter'.$i.'_op'];
			$val_field = $this->fields['filter'.$i.'_val'];

			$table->addRow([
				$i,
				(new CSelect('filter'.$i.'_column'))
					->setValue($col_field->getValue())
					->addOptions(CSelect::createOptionsFromArray($col_field->getValues())),
				
				(new CTextBox('filter'.$i.'_target_param', $param_field->getValue()))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
					->setAttribute('placeholder', _('Tag/Inv/Item Pattern')),

				(new CSelect('filter'.$i.'_op'))
					->setValue($op_field->getValue())
					->addOptions(CSelect::createOptionsFromArray($op_field->getValues())),

				(new CTextBox('filter'.$i.'_val', $val_field->getValue()))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			]);
		}
 
		$footer = (new CDiv([
			new CLabel(_('Logic'), 'filter_logic'),
			(new CTextBox('filter_logic', $this->fields['filter_logic']->getValue()))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('placeholder', _('ex: 1 and 2, (1 or 2) and 3'))
		]))->addStyle('margin-top: 10px;');
 
		return (new CDiv([
			$table,
			$footer
		]))
			->addClass('table-forms-separator')
			->addStyle('display: block; width: 680px; box-sizing: border-box;');
	}
}
