<?php declare(strict_types = 0);

namespace Modules\AdvancedHostGrid\Includes;

use Modules\AdvancedHostGrid\Widget;

use CButton,
	CButtonLink,
	CCol,
	CColHeader,
	CDiv,
	CList,
	CRow,
	CSpan,
	CTable,
	CTag,
	CVar,
	CWidgetFieldView;

class CWidgetFieldColumnsListView extends CWidgetFieldView {

	public function __construct(CWidgetFieldColumnsList $field) {
		$this->field = $field;
	}

	public function getView(): \CTag {
		$columns = $this->field->getValue();

		$header = [
			'',
			(new CColHeader(_('Name')))->addStyle('width: 39%'),
			(new CColHeader(_('Data')))->addStyle('width: 59%'),
			_('Actions')
		];

		$row_actions = [
			(new CButton('edit', _('Edit')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->removeId(),
			(new CButton('remove', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->removeId()
		];

		$view = (new CTable())
			->setId('list_'.$this->field->getName())
			->setHeader($header)
			->addClass('table-initial-width');

		foreach ($columns as $column_index => $column) {
			$column_data = [new CVar('sortorder['.$this->field->getName().'][]', $column_index)];

			foreach ($column as $key => $value) {
				if (is_array($value)) {
					// Handle nested arrays (thresholds).
					foreach ($value as $sub_index => $sub_value) {
						if (is_array($sub_value)) {
							foreach ($sub_value as $sub_key => $sub_val) {
								$column_data[] = new CVar(
									$this->field->getName().'['.$column_index.']['.$key.']['.$sub_index.']['.$sub_key.']',
									$sub_val
								);
							}
						}
						else {
							$column_data[] = new CVar(
								$this->field->getName().'['.$column_index.']['.$key.']['.$sub_index.']',
								$sub_value
							);
						}
					}
				}
				else {
					$column_data[] = new CVar(
						$this->field->getName().'['.$column_index.']['.$key.']',
						$value
					);
				}
			}

			if ($column['data'] == Widget::DATA_HOST_NAME) {
				$label = new CTag('em', true, _('Host name'));
			}
			elseif ($column['data'] == Widget::DATA_TEXT) {
				$label = new CTag('em', true, $column['text'] ?? '');
			}
			elseif (array_key_exists('item', $column)) {
				$label = $column['item'];
			}
			else {
				$label = '';
			}

			// Add filter badge if active.
			$filter_info = '';
			$filter_op = $column['filter_operator'] ?? 0;
			if ($filter_op > 0) {
				$op_labels = [
					1 => '=', 2 => '≠', 3 => '>', 4 => '<',
					5 => '≥', 6 => '≤', 7 => '∋', 8 => '∌',
					9 => _('exists'), 10 => _('not exists')
				];
				$op_label = $op_labels[$filter_op] ?? '?';
				$filter_info = ' '.(new CSpan(
					'Filter: '.$op_label.' '.($column['filter_value'] ?? '')
				))->addClass('column-filter-badge');
			}

			$view->addRow([
				(new CCol((new CDiv)->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
				(new CDiv($column['name'] ?? ''))->addClass('text'),
				(new CDiv([$label, $filter_info]))->addClass('text'),
				[
					(new CList($row_actions))->addClass(ZBX_STYLE_HOR_LIST),
					$column_data
				]
			]);
		}

		$view->addRow(
			(new CCol(
				(new CButton('add', _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->setEnabled(!$this->isDisabled())
			))->setColSpan(count($header))
		);

		return (new CDiv($view))
			->addClass('table-forms-separator')
			->addStyle('display: block;');
	}

	public function getJavaScript(): string {
		$field_name = $this->field->getName();

		return '
			var columns_table = document.getElementById("list_'.$field_name.'");

			if (columns_table) {
				var DATA_HOST_NAME = '.Widget::DATA_HOST_NAME.';
				var DATA_ITEM_VALUE = '.Widget::DATA_ITEM_VALUE.';
				var DATA_TEXT = '.Widget::DATA_TEXT.';

				function getDataLabel(data) {
					switch (parseInt(data)) {
						case DATA_HOST_NAME: return "Host name";
						case DATA_ITEM_VALUE: return "Item value";
						case DATA_TEXT: return "Text";
						default: return "";
					}
				}

				function getColumnDisplayLabel(col) {
					switch (parseInt(col.data)) {
						case DATA_HOST_NAME: return "Host name";
						case DATA_TEXT: return col.text || "Text";
						case DATA_ITEM_VALUE: return col.item || "Item";
						default: return "";
					}
				}

				function getNextColumnIndex() {
					var inputs = columns_table.querySelectorAll("input[type=hidden]");
					var max_index = -1;
					inputs.forEach(function(inp) {
						var match = inp.name.match(/'.$field_name.'\[(\d+)\]/);
						if (match) {
							var idx = parseInt(match[1]);
							if (idx > max_index) max_index = idx;
						}
					});
					return max_index + 1;
				}

				function createHiddenInput(name, value) {
					var inp = document.createElement("input");
					inp.type = "hidden";
					inp.name = name;
					inp.value = (value !== undefined && value !== null) ? value : "";
					return inp;
				}

				function addColumnHiddenInputs(container, index, col) {
					var fields = ["data", "name", "item", "text", "display",
						"base_color", "min", "max", "prepend_item", "prepend_item_begin", "prepend_item_end", "apply_to_node", "parent_status_priority", "decimal_places", "display_value_as", "columnid"];

					container.appendChild(createHiddenInput(
						"sortorder[" + "'.$field_name.'" + "][]", index
					));

					fields.forEach(function(key) {
						if (col[key] !== undefined) {
							container.appendChild(createHiddenInput(
								"'.$field_name.'[" + index + "][" + key + "]", col[key]
							));
						}
					});

					// Thresholds.
					if (col.thresholds && Array.isArray(col.thresholds)) {
						col.thresholds.forEach(function(t, t_idx) {
							container.appendChild(createHiddenInput(
								"'.$field_name.'[" + index + "][thresholds][" + t_idx + "][color]",
								t.color || ""
							));
							container.appendChild(createHiddenInput(
								"'.$field_name.'[" + index + "][thresholds][" + t_idx + "][threshold]",
								t.threshold || ""
							));
						});
					}

					// Highlights.
					if (col.highlights && Array.isArray(col.highlights)) {
						col.highlights.forEach(function(highlight, h_index) {
							container.appendChild(createHiddenInput(
								"'.$field_name.'[" + index + "][highlights][" + h_index + "][color]",
								highlight.color
							));
							container.appendChild(createHiddenInput(
								"'.$field_name.'[" + index + "][highlights][" + h_index + "][pattern]",
								highlight.pattern
							));
						});
					}
				}

				function reIndexRows() {
					// We do not strictly NEED to re-index names because sortorder[] handles it,
					// but it keeps the indices clean and prevents potential issues with
					// large gaps if many columns are added/removed.
					jQuery(columns_table).find("tbody tr").not(":last-child").each(function(index) {
						var row = this;
						jQuery(row).find("input[type=hidden]").each(function() {
							if (this.name.indexOf("sortorder") !== -1) {
								this.value = index;
							} else {
								this.name = this.name.replace(/('.$field_name.')\[\d+\]/, "$1[" + index + "]");
							}
						});
					});
				}

				function createColumnRow(index, col) {
					var tr = document.createElement("tr");

					// Drag icon cell.
					var td_drag = document.createElement("td");
					td_drag.className = "' . ZBX_STYLE_TD_DRAG_ICON . '";
					var drag_div = document.createElement("div");
					drag_div.className = "' . ZBX_STYLE_DRAG_ICON . '";
					td_drag.appendChild(drag_div);
					tr.appendChild(td_drag);

					// Name cell.
					var td_name = document.createElement("td");
					var name_div = document.createElement("div");
					name_div.className = "text";
					name_div.textContent = col.name || "";
					td_name.appendChild(name_div);
					tr.appendChild(td_name);

					// Data cell.
					var td_data = document.createElement("td");
					var data_div = document.createElement("div");
					data_div.className = "text";
					var data_label = getColumnDisplayLabel(col);
					if (parseInt(col.data) !== DATA_ITEM_VALUE) {
						var em = document.createElement("em");
						em.textContent = data_label;
						data_div.appendChild(em);
					} else {
						data_div.textContent = data_label;
					}
					td_data.appendChild(data_div);
					tr.appendChild(td_data);

					// Actions cell.
					var td_actions = document.createElement("td");

					var edit_btn = document.createElement("button");
					edit_btn.type = "button";
					edit_btn.name = "edit";
					edit_btn.className = "' . ZBX_STYLE_BTN_LINK . '";
					edit_btn.textContent = "' . _('Edit') . '";
					td_actions.appendChild(edit_btn);

					var sep = document.createTextNode(" ");
					td_actions.appendChild(sep);

					var remove_btn = document.createElement("button");
					remove_btn.type = "button";
					remove_btn.name = "remove";
					remove_btn.className = "' . ZBX_STYLE_BTN_LINK . '";
					remove_btn.textContent = "' . _('Remove') . '";
					td_actions.appendChild(remove_btn);

					tr.appendChild(td_actions);

					// Hidden inputs inside the row.
					addColumnHiddenInputs(tr, index, col);

					return tr;
				}

				jQuery(columns_table).find("tbody").sortable({
					items: "tr:not(:last-child)",
					handle: "." + "'.ZBX_STYLE_DRAG_ICON.'",
					cursor: "move",
					opacity: 0.6,
					axis: "y",
					update: function() {
						reIndexRows();
					}
				});

				// Event delegation for Add / Edit / Remove.
				columns_table.addEventListener("click", function(e) {

					// ---- ADD ----
					var add_btn = e.target.closest("button[name=add]");
					if (add_btn) {
						PopUp("widget.advhostgrid.column.edit", {}, {
							dialogueid: "advhostgrid-column-edit-overlay",
							dialogue_class: "modal-popup-generic"
						}).$dialogue[0].addEventListener("dialogue.submit", function(ev) {
							var col = ev.detail;
							var new_index = getNextColumnIndex();

							// Ensure unique column ID for persistence across reordering
							if (!col.columnid) {
								col.columnid = "col_" + Date.now() + "_" + Math.floor(Math.random() * 1000);
							}

							// Create visible row with hidden inputs.
							var new_row = createColumnRow(new_index, col);

							// Insert before the last row (the "Add" button row).
							var tbody = columns_table.querySelector("tbody");
							var add_row = add_btn.closest("tr");
							tbody.insertBefore(new_row, add_row);

							reIndexRows();
						});

						return;
					}

					// ---- EDIT ----
					var edit_btn = e.target.closest("button[name=edit]");
					if (edit_btn) {
						var row = edit_btn.closest("tr");
						if (!row) return;

						// Collect existing column data from hidden inputs in this row.
						var params = {edit: 1};
						var hidden_inputs = row.querySelectorAll("input[type=hidden]");
						hidden_inputs.forEach(function(inp) {
							var match = inp.name.match(/\[(\w+)\](?:\[(\d+)\](?:\[(\w+)\])?)?$/);
							if (match) {
								var key = match[1];
								if (match[2] !== undefined && match[3] !== undefined) {
									if (!params[key]) params[key] = [];
									var idx = parseInt(match[2]);
									if (!params[key][idx]) params[key][idx] = {};
									params[key][idx][match[3]] = inp.value;
								}
								else if (key !== "sortorder") {
									params[key] = inp.value;
								}
							}
						});

						PopUp("widget.advhostgrid.column.edit", params, {
							dialogueid: "advhostgrid-column-edit-overlay",
							dialogue_class: "modal-popup-generic"
						}).$dialogue[0].addEventListener("dialogue.submit", function(ev) {
							var updated_col = ev.detail;

							// Find the column index from existing hidden inputs.
							var column_index = null;
							row.querySelectorAll("input[type=hidden]").forEach(function(inp) {
								var match = inp.name.match(/'.$field_name.'\[(\d+)\]/);
								if (match && column_index === null) {
									column_index = match[1];
								}
							});

							if (column_index === null) return;

							// Remove all old hidden inputs.
							row.querySelectorAll("input[type=hidden]").forEach(function(inp) {
								inp.remove();
							});

							// Re-add with updated values.
							addColumnHiddenInputs(row, column_index, updated_col);

							// Update visible cells.
							var name_div = row.querySelector("td:nth-child(2) .text");
							if (name_div) name_div.textContent = updated_col.name || "";

							var data_div = row.querySelector("td:nth-child(3) .text");
							if (data_div) {
								data_div.innerHTML = "";
								var label = getColumnDisplayLabel(updated_col);
								if (parseInt(updated_col.data) !== DATA_ITEM_VALUE) {
									var em = document.createElement("em");
									em.textContent = label;
									data_div.appendChild(em);
								} else {
									data_div.textContent = label;
								}
							}
						});

						return;
					}

					// ---- REMOVE ----
					var remove_btn = e.target.closest("button[name=remove]");
					if (remove_btn) {
						var row = remove_btn.closest("tr");
						if (row) {
							row.remove();
							reIndexRows();
						}
					}
				});
			}
		';
	}

	private function getDataTypeLabel(int $data): string {
		switch ($data) {
			case Widget::DATA_HOST_NAME:
				return _('Host name');
			case Widget::DATA_ITEM_VALUE:
				return _('Item value');
			case Widget::DATA_TEXT:
				return _('Text');
		}

		return '';
	}
}
