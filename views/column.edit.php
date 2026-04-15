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
	))->addClass('js-item-row')
]);

// Text.
$form_grid->addItem([
	(new CLabel(_('Text'), 'text'))
		->setAsteriskMark()
		->addClass('js-text-row'),
	(new CFormField(
		(new CTextBox('text', $data['text']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', _('Static text value'))
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

$form_grid->addItem([
	(new CLabel(_('Thresholds'), 'thresholds_table'))->addClass('js-thresholds-row'),
	(new CFormField($thresholds))->addClass('js-thresholds-row')
]);

$form
	->addItem($form_grid)
	->addItem(
		(new CScriptTag('
			advhostgrid_column_edit_form.init('.json_encode([
				'form_id' => $form->getId(),
				'thresholds' => $data['thresholds'],
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
