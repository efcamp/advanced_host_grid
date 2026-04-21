<?php declare(strict_types = 0);

namespace Modules\AdvancedHostGrid\Includes;

use Modules\AdvancedHostGrid\Widget;

use CButton,
	CButtonLink,
	CCol,
	CDiv,
	CRow,
	CSelect,
	CSpan,
	CTable,
	CTemplateTag,
	CTextBox,
	CTag,
	CWidgetFieldView;

class CWidgetFieldGroupingView extends CWidgetFieldView {

	public const ZBX_STYLE_GROUPING = 'advanced-grouping';

	public function __construct(CWidgetFieldGrouping $field) {
		$this->field = $field;
	}

	public function getView(): \CTag {
		$view = (new CTable())
			->setId($this->field->getName().'-table')
			->addClass(self::ZBX_STYLE_GROUPING)
			->addClass('table-initial-width')
			->setFooter(new CRow(
				(new CCol(
					(new CButtonLink(_('Add')))
						->setId('group-by-add-row')
						->addClass('element-table-add')
				))->setColSpan(5)
			));

		return (new CDiv($view))
			->addClass('table-forms-separator')
			->addStyle('display: block;');
	}

	public function getJavaScript(): string {
		return '
			CWidgetForm.addField(
				new AdvancedHostGrid_CWidgetFieldGrouping('.json_encode([
					'name' => $this->field->getName(),
					'form_name' => $this->form_name,
					'value' => $this->field->getValue(),
					'max_rows' => CWidgetFieldGrouping::MAX_ROWS,
					'inventory_fields' => Widget::INVENTORY_FIELDS
				]).')
			);
		';
	}

	public function getTemplates(): array {
		// Build inventory options.
		$inventory_options = [];
		foreach (Widget::INVENTORY_FIELDS as $key => $label) {
			$inventory_options[$key] = $label;
		}

		return [
			new CTemplateTag($this->field->getName().'-row-tmpl',
				(new CRow([
					(new CCol((new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
					(new CSpan(':'))->addClass(ZBX_STYLE_LIST_NUMBERED_ITEM),
					(new CSelect($this->field->getName().'[#{rowNum}][attribute]'))
						->addOptions(CSelect::createOptionsFromArray([
							Widget::GROUP_BY_TAG_VALUE => _('Tag value'),
							Widget::GROUP_BY_HOST_GROUP => _('Host group'),
							Widget::GROUP_BY_SEVERITY => _('Severity'),
							Widget::GROUP_BY_HOST_INVENTORY => _('Host inventory'),
							Widget::GROUP_BY_ITEM_VALUE => _('Item value'),
							Widget::GROUP_BY_HOST_NAME => _('Host name'),
							Widget::GROUP_BY_ITEM_NAME => _('Item name')
						]))
						->setValue('#{attribute}')
						->setId($this->field->getName().'_#{rowNum}_attribute')
						->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
					(new CCol([
						(new CTextBox($this->field->getName().'[#{rowNum}][tag_name]', '#{tag_name}', false))
							->setId($this->field->getName().'_#{rowNum}_tag_name')
							->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
							->setAttribute('placeholder', _('tag')),
						(new CSelect($this->field->getName().'[#{rowNum}][inventory_field]'))
							->addOptions(CSelect::createOptionsFromArray($inventory_options))
							->setValue('#{inventory_field}')
							->setId($this->field->getName().'_#{rowNum}_inventory_field')
							->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
						(new CTextBox($this->field->getName().'[#{rowNum}][item_pattern]', '#{item_pattern}', false))
							->setId($this->field->getName().'_#{rowNum}_item_pattern')
							->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
							->setAttribute('placeholder', _('item pattern')),
						(new CTextBox($this->field->getName().'[#{rowNum}][value_mappings]', '#{value_mappings}', false))
							->setId($this->field->getName().'_#{rowNum}_value_mappings')
							->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
							->setAttribute('placeholder', _('val=label:hex, or INHERIT:group_num'))
					]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
					(new CDiv(
						(new CButton($this->field->getName().'[#{rowNum}][remove]', _('Remove')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->addClass('element-table-remove')
					))
				]))->addClass('form_row')
			)
		];
	}
}
