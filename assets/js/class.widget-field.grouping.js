/*
 * Advanced Host Grid - Grouping widget field JavaScript class.
 * Handles dynamic rows for the group-by configuration, including
 * showing/hiding sub-fields based on selected attribute type.
 */

class AdvancedHostGrid_CWidgetFieldGrouping extends CWidgetField {

	// Must match Widget.php constants.
	static GROUP_BY_TAG_VALUE = 0;
	static GROUP_BY_HOST_GROUP = 1;
	static GROUP_BY_SEVERITY = 2;
	static GROUP_BY_HOST_INVENTORY = 3;
	static GROUP_BY_ITEM_VALUE = 4;

	/**
	 * @type {HTMLTableElement}
	 */
	#table;

	/**
	 * @type {Array}
	 */
	#value;

	/**
	 * @type {number}
	 */
	#max_rows;

	/**
	 * @type {Object}
	 */
	#inventory_fields;

	constructor({name, form_name, value, max_rows, inventory_fields}) {
		super({name, form_name});

		this.#value = value;
		this.#max_rows = max_rows;
		this.#inventory_fields = inventory_fields || {};
		this.#table = document.getElementById(`${name}-table`);

		this.#initField();
		this.#update();
	}

	#initField() {
		jQuery(this.#table)
			.dynamicRows({
				template: `#${this.getName()}-row-tmpl`,
				allow_empty: true,
				rows: this.#value,
				sortable: true,
				sortable_options: {
					target: 'tbody',
					selector_handle: `.${ZBX_STYLE_DRAG_ICON}`,
					freeze_end: 1
				}
			})
			.on('afteradd.dynamicRows, tableupdate.dynamicRows', () => {
				this.#update();
				this.dispatchUpdateEvent();
			});

		this.#table.addEventListener('input', () => this.dispatchUpdateEvent());
		this.#table.addEventListener('change', () => {
			this.#update();
			this.dispatchUpdateEvent();
		});
	}

	#update() {
		const rows = this.#table.querySelectorAll('.form_row');

		rows.forEach((row, index) => {
			for (const field of row.querySelectorAll(`[name^="${this.getName()}["]`)) {
				field.name = field.name.replace(/\[\d+]/g, `[${index}]`);
			}

			const attribute = parseInt(
				row.querySelector('[name$="[attribute]"]').value
			);

			const is_tag_value = attribute === AdvancedHostGrid_CWidgetFieldGrouping.GROUP_BY_TAG_VALUE;
			const is_inventory = attribute === AdvancedHostGrid_CWidgetFieldGrouping.GROUP_BY_HOST_INVENTORY;
			const is_item_value = attribute === AdvancedHostGrid_CWidgetFieldGrouping.GROUP_BY_ITEM_VALUE;

			// Tag name input.
			const tag_name_input = row.querySelector('input[name$="[tag_name]"]');
			if (tag_name_input) {
				tag_name_input.style.display = is_tag_value ? '' : 'none';
				tag_name_input.disabled = !is_tag_value;
			}

			// Inventory field select.
			const inventory_select = row.querySelector('[name$="[inventory_field]"]');
			if (inventory_select) {
				inventory_select.style.display = is_inventory ? '' : 'none';
				inventory_select.disabled = !is_inventory;
			}

			// Item pattern input.
			const item_pattern_input = row.querySelector('input[name$="[item_pattern]"]');
			if (item_pattern_input) {
				item_pattern_input.style.display = is_item_value ? '' : 'none';
				item_pattern_input.disabled = !is_item_value;
			}

			// Value mappings input (visible for all grouping types to support mapping or inheritance)
			const value_mappings_input = row.querySelector('input[name$="[value_mappings]"]');
			if (value_mappings_input) {
				value_mappings_input.style.display = '';
				value_mappings_input.disabled = false;
			}
		});

		const add_button = this.#table.querySelector('#group-by-add-row');
		if (add_button) {
			add_button.disabled = rows.length >= this.#max_rows;
		}
	}
}
