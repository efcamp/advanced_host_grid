<?php
/**
 * Advanced Host Grid widget view.
 *
 * @var CView $this
 * @var array $data
 */

(new CWidgetView($data))
	->addItem([
		(new CDiv())
			->setId('ahg-container')
			->addClass('advanced-host-grid-container')
	])
	->setVar('columns', $data['columns'])
	->setVar('group_by', $data['group_by'])
	->setVar('grouped_data', $data['grouped_data'])
	->setVar('host_count', $data['host_count'] ?? 0)
	->show();
