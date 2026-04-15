<?php
/**
 * Advanced Host Grid widget form view.
 *
 * @var CView $this
 * @var array $data
 */

use Modules\AdvancedHostGrid\Includes\WidgetForm;
use Modules\AdvancedHostGrid\Includes\CWidgetFieldGroupingView;
use Modules\AdvancedHostGrid\Includes\CWidgetFieldColumnsListView;
use Modules\AdvancedHostGrid\Includes\CWidgetFieldFiltersView;

$grouping_view = array_key_exists('group_by', $data['fields'])
	? new CWidgetFieldGroupingView($data['fields']['group_by'])
	: null;

$columns_view = array_key_exists('columns', $data['fields'])
	? new CWidgetFieldColumnsListView($data['fields']['columns'])
	: null;

$filters_view = null;
if (array_key_exists('filter_logic', $data['fields'])) {
	$filters_view = new CWidgetFieldFiltersView($data['fields']['filter_logic'], [
		'filter1_column' => $data['fields']['filter1_column'],
		'filter1_target_param' => $data['fields']['filter1_target_param'],
		'filter1_op'     => $data['fields']['filter1_op'],
		'filter1_val'    => $data['fields']['filter1_val'],
		'filter2_column' => $data['fields']['filter2_column'],
		'filter2_target_param' => $data['fields']['filter2_target_param'],
		'filter2_op'     => $data['fields']['filter2_op'],
		'filter2_val'    => $data['fields']['filter2_val'],
		'filter3_column' => $data['fields']['filter3_column'],
		'filter3_target_param' => $data['fields']['filter3_target_param'],
		'filter3_op'     => $data['fields']['filter3_op'],
		'filter3_val'    => $data['fields']['filter3_val'],
		'filter_logic'   => $data['fields']['filter_logic']
	]);
}

(new CWidgetFormView($data))
	->addField(
		array_key_exists('groupids', $data['fields'])
			? new CWidgetFieldMultiSelectGroupView($data['fields']['groupids'])
			: null
	)
	->addField(
		array_key_exists('hostids', $data['fields'])
			? new CWidgetFieldMultiSelectHostView($data['fields']['hostids'])
			: null
	)
	->addField(
		array_key_exists('evaltype', $data['fields'])
			? new CWidgetFieldRadioButtonListView($data['fields']['evaltype'])
			: null
	)
	->addField(
		array_key_exists('tags', $data['fields'])
			? new CWidgetFieldTagsView($data['fields']['tags'])
			: null
	)
	->addField(
		array_key_exists('maintenance', $data['fields'])
			? new CWidgetFieldCheckBoxView($data['fields']['maintenance'])
			: null
	)
	->addField(
		array_key_exists('maintenance_override', $data['fields'])
			? new CWidgetFieldCheckBoxView($data['fields']['maintenance_override'])
			: null
	)
	->addField(
		array_key_exists('maintenance_override_settings', $data['fields'])
			? new CWidgetFieldTextBoxView($data['fields']['maintenance_override_settings'])
			: null
	)
	// ---- Columns ----
	->addField($columns_view)

	// ---- Grouping ----
	->addField($grouping_view)

	// ---- Filters ----
	->addField($filters_view)

	// ---- Ordering ----
	->addField(
		array_key_exists('column', $data['fields'])
			? new CWidgetFieldSelectView($data['fields']['column'])
			: null
	)
	->addField(
		array_key_exists('order', $data['fields'])
			? new CWidgetFieldRadioButtonListView($data['fields']['order'])
			: null
	)
	->addField(
		array_key_exists('show_lines', $data['fields'])
			? new CWidgetFieldIntegerBoxView($data['fields']['show_lines'])
			: null
	)
	->addJavaScript('
		window.widget_advanced_host_grid_form = new class {
			init() {
				const _this = this;
				const targets = ['.WidgetForm::FILTER_TARGET_TAG_VALUE.', '.WidgetForm::FILTER_TARGET_INVENTORY.', '.WidgetForm::FILTER_TARGET_ITEM_VALUE.'];

				jQuery(document).on("change", "z-select[name*=\"filter\"][name*=\"_column\"]", function() {
					_this._updateFields(targets);
				});

				// Small delay to ensure the form is fully rendered
				setTimeout(() => this._updateFields(targets), 200);
			}

			_updateFields(targets) {
				jQuery("z-select[name*=\"filter\"][name*=\"_column\"]").each(function() {
					const name = jQuery(this).attr("name");
					const match = name.match(/filter(\d)_column/);
					
					if (match) {
						const i = match[1];
						const val = parseInt(jQuery(this).val());
						const enabled = targets.includes(val);
						const $input = jQuery(`input[name*="filter${i}_target_param"]`);

						if ($input.length) {
							$input.prop("disabled", !enabled).prop("readonly", !enabled);
							if (!enabled) {
								$input.val("").addClass("readonly");
							} else {
								$input.removeClass("readonly");
							}
						}
					}
				});
			}
		};
		widget_advanced_host_grid_form.init();
	')
	->show();
