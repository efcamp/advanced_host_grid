<?php declare(strict_types = 0);

/**
 * Column editor dialog view — rendered as a Zabbix overlay popup.
 *
 * @var CView $this
 * @var array $data
 */

use Modules\AdvancedHostGrid\Widget;

$form = (new CForm())
	->setId('advhostgrid_column_edit_form')
	->setName('advhostgrid_column')
	->addStyle('display: none;')
	->addVar('action', $data['action'])
	->addVar('update', 1);

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$form_grid = new CFormGrid();

if (array_key_exists('edit', $data)) {
	$form->addVar('edit', 1);
}

// Name.
$form_grid->addItem([
	(new CLabel(_('Name'), 'column_name'))->setAsteriskMark(),
	new CFormField(
		(new CTextBox('name', $data['name'], false))
			->setId('column_name')
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
			->setAriaRequired()
	)
]);

// Data type.
$form_grid->addItem([
	new CLabel(_('Data'), 'data'),
	new CFormField(
		(new CSelect('data'))
			->setValue($data['data'])
			->addOptions(CSelect::createOptionsFromArray([
				Widget::DATA_ITEM_VALUE => _('Item value'),
				Widget::DATA_HOST_NAME => _('Host name'),
				Widget::DATA_TEXT => _('Text')
			]))
			->setFocusableElementId('data')
	)
]);

// Item pattern.
$form_grid->addItem([
	(new CLabel(_('Item pattern'), 'item'))
		->setAsteriskMark()
		->addClass('js-item-row'),
	(new CFormField(
		(new CTextBox('item', $data['item']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', _('Item name or pattern'))
	))->addClass('js-item-row'),
	(new CLabel(_('Prepend item name'), 'prepend_item'))->addClass('js-item-row'),
	(new CFormField(
		(new CCheckBox('prepend_item'))
			->setChecked((int) $data['prepend_item'] === 1)
			->setUncheckedValue(0)
	))->addClass('js-item-row')
]);

$form_grid->addItem([
	(new CLabel(_('Substring begin'), 'prepend_item_begin'))->addClass('js-prepend-ext-row'),
	(new CFormField(
		(new CTextBox('prepend_item_begin', $data['prepend_item_begin']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', _('Optional starting string'))
	))->addClass('js-prepend-ext-row'),
	(new CLabel(_('Substring end'), 'prepend_item_end'))->addClass('js-prepend-ext-row'),
	(new CFormField(
		(new CTextBox('prepend_item_end', $data['prepend_item_end']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', _('Optional ending string'))
	))->addClass('js-prepend-ext-row')
]);

// Text.
$form_grid->addItem([
	(new CLabel(_('Text'), 'text'))
		->setAsteriskMark()
		->addClass('js-text-row'),
	(new CFormField(
		(new CTextArea('text', $data['text']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('rows', 3)
			->setAttribute('placeholder', _('Static text value or macros (one per line if exploding)'))
	))->addClass('js-text-row'),
	(new CLabel(_('Display each line as a separate row'), 'explode_text'))->addClass('js-text-row'),
	(new CFormField(
		(new CCheckBox('explode_text'))
			->setChecked((int) $data['explode_text'] === 1)
			->setUncheckedValue(0)
	))->addClass('js-text-row')
]);

// Base color.
$form_grid->addItem([
	new CLabel(_('Base color'), 'lbl_base_color'),
	new CFormField(
		(new CColorPicker('base_color'))
			->setColor($data['base_color'])
			->allowEmpty()
	)
]);

// Display item value as.
$form_grid->addItem([
	new CLabel(_('Display item value as'), 'display_value_as'),
	new CFormField(
		(new CRadioButtonList('display_value_as', (int) $data['display_value_as']))
			->addValue(_('Numeric'), 0)
			->addValue(_('Text'), 1)
			->setModern()
	)
]);

// Display.
$form_grid->addItem([
	(new CLabel(_('Display'), 'display'))->addClass('js-display-row'),
	(new CFormField(
		(new CRadioButtonList('display', (int) $data['display']))
			->addValue(_('As is'), Widget::DISPLAY_AS_IS)
			->addValue(_('Bar'), Widget::DISPLAY_BAR)
			->addValue(_('Indicators'), Widget::DISPLAY_INDICATORS)
			->setModern()
	))->addClass('js-display-row')
]);

// Min.
$form_grid->addItem([
	(new CLabel(_('Min'), 'min'))->addClass('js-min-max-row'),
	(new CFormField(
		(new CTextBox('min', $data['min']))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			->setAttribute('placeholder', _('calculated'))
	))->addClass('js-min-max-row')
]);

// Max.
$form_grid->addItem([
	(new CLabel(_('Max'), 'max'))->addClass('js-min-max-row'),
	(new CFormField(
		(new CTextBox('max', $data['max']))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			->setAttribute('placeholder', _('calculated'))
	))->addClass('js-min-max-row')
]);

// Decimal places.
$form_grid->addItem([
	(new CLabel(_('Decimal places'), 'decimal_places'))->addClass('js-numeric-row'),
	(new CFormField(
		(new CNumericBox('decimal_places', $data['decimal_places'], 2))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	))->addClass('js-numeric-row')
]);

// Thresholds.
$threshold_header_row = [
	'',
	_('Threshold'),
	(new CColHeader(''))->setWidth('100%')
];

$thresholds = (new CDiv([
	(new CTable())
		->setId('thresholds_table')
		->addClass(ZBX_STYLE_TABLE_FORMS)
		->setHeader($threshold_header_row)
		->setFooter(new CRow(
			(new CCol(
				(new CButtonLink(_('Add')))->addClass('element-table-add')
			))->setColSpan(count($threshold_header_row))
		)),
	(new CTemplateTag('thresholds-row-tmpl'))
		->addItem((new CRow([
			(new CColorPicker('thresholds[#{rowNum}][color]'))
				->setColor('#{color}')
				->allowEmpty(),
			(new CTextBox('thresholds[#{rowNum}][threshold]', '#{threshold}', false))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired(),
			(new CButton('thresholds[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))->addClass('form_row'))
]))
	->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

// Highlights.
$highlight_header_row = [
	'',
	_('Pattern'),
	(new CColHeader(''))->setWidth('100%')
];

$highlights = (new CDiv([
	(new CTable())
		->setId('highlights_table')
		->addClass(ZBX_STYLE_TABLE_FORMS)
		->setHeader($highlight_header_row)
		->setFooter(new CRow(
			(new CCol(
				(new CButtonLink(_('Add')))->addClass('element-table-add')
			))->setColSpan(count($highlight_header_row))
		)),
	(new CTemplateTag('highlights-row-tmpl'))
		->addItem((new CRow([
			(new CColorPicker('highlights[#{rowNum}][color]'))
				->setColor('#{color}')
				->allowEmpty(),
			(new CTextBox('highlights[#{rowNum}][pattern]', '#{pattern}', false))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAriaRequired(),
			(new CButton('highlights[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))->addClass('form_row'))
]))
	->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$form_grid->addItem([
	(new CLabel(_('Thresholds'), 'thresholds_table'))->addClass('js-thresholds-row'),
	(new CFormField($thresholds))->addClass('js-thresholds-row')
]);

$form_grid->addItem([
	(new CLabel(_('Use status for parent'), 'apply_to_node'))->addClass('js-apply-node-row'),
	(new CFormField(
		(new CCheckBox('apply_to_node'))
			->setId('apply_to_node')
			->setChecked((int) $data['apply_to_node'] === 1)
			->setUncheckedValue(0)
	))->addClass('js-apply-node-row')
]);

$form_grid->addItem([
	(new CLabel(_('Parent status priority'), 'parent_status_priority'))->addClass('js-parent-priority-row'),
	(new CFormField(
		(new CSelect('parent_status_priority'))
			->setId('parent_status_priority')
			->setValue((int) $data['parent_status_priority'])
			->addOptions(CSelect::createOptionsFromArray([
				0 => _('Highest matched'),
				1 => _('Lowest matched')
			]))
	))->addClass('js-parent-priority-row')
]);

$form_grid->addItem([
	(new CLabel(_('Highlights'), 'highlights_table'))->addClass('js-highlights-row'),
	(new CFormField($highlights))->addClass('js-highlights-row')
]);

$form
	->addItem($form_grid)
	->addItem(
		(new CScriptTag('
			advhostgrid_column_edit_form.init('.json_encode([
				'form_id' => $form->getId(),
				'thresholds' => $data['thresholds'],
				'highlights' => $data['highlights'],
				'colors' => $data['colors']
			], JSON_THROW_ON_ERROR).');
		'))->setOnDocumentReady()
	);

$output = [
	'header'		=> array_key_exists('edit', $data) ? _('Update column') : _('New column'),
	'script_inline'	=> $this->readJsFile('column.edit.js.php', null, ''),
	'body'			=> $form->toString(),
	'buttons'		=> [
		[
			'title'		=> array_key_exists('edit', $data) ? _('Update') : _('Add'),
			'keepOpen'	=> true,
			'isSubmit'	=> true,
			'action'	=> 'advhostgrid_column_edit_form.submit();'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output, JSON_THROW_ON_ERROR);
