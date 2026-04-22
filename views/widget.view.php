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
	->setVar('show_host_count', $data['show_host_count'] ?? false)
	->setVar('expand_depth', $data['expand_depth'] ?? 1)
	->setVar('grouping_color_full', $data['grouping_color_full'] ?? false)
	->setVar('honeycomb_view', $data['honeycomb_view'] ?? false)
	->setVar('honeycomb_shape', $data['honeycomb_shape'] ?? 0)
	->setVar('honeycomb_primary_label', $data['honeycomb_primary_label'] ?? 2)
	->setVar('honeycomb_secondary_label', $data['honeycomb_secondary_label'] ?? 0)
	->show();
