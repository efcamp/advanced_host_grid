<?php declare(strict_types = 0);

use Modules\AdvancedHostGrid\Widget;

?>

window.advhostgrid_column_edit_form = new class {

	/**
	 * @type {Overlay}
	 */
	#overlay;

	/**
	 * @type {HTMLElement}
	 */
	#dialogue;

	/**
	 * @type {HTMLFormElement}
	 */
	#form;

	init({form_id, thresholds, highlights, colors}) {
		this.#overlay = overlays_stack.getById('advhostgrid-column-edit-overlay');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#form = document.getElementById(form_id);

		const inputs = this.#form.querySelectorAll('[name="data"], [name="display"], [name="display_value_as"], [name="apply_to_node"], [name="prepend_item"]');

		for (const input of inputs) {
			input.addEventListener('change', () => this.#updateForm());
		}

		colorPalette.setThemeColors(colors);

		const thresholds_table = document.getElementById('thresholds_table');

		// Initialize thresholds table.
		$(thresholds_table)
			.dynamicRows({
				rows: thresholds,
				template: '#thresholds-row-tmpl',
				allow_empty: true,
				dataCallback: row_data => {
					if (!('color' in row_data)) {
						const color_pickers = this.#form.querySelectorAll(`.${ZBX_STYLE_COLOR_PICKER}`);
						const used_colors = [];

						for (const color_picker of color_pickers) {
							if (color_picker.color !== '') {
								used_colors.push(color_picker.color);
							}
						}

						row_data.color = colorPalette.getNextColor(used_colors);
					}
				}
			})
			.on('afteradd.dynamicRows', () => this.#updateForm())
			.on('afterremove.dynamicRows', () => this.#updateForm());

		const highlights_table = document.getElementById('highlights_table');

		// Initialize highlights table.
		$(highlights_table)
			.dynamicRows({
				rows: highlights,
				template: '#highlights-row-tmpl',
				allow_empty: true,
				dataCallback: row_data => {
					if (!('color' in row_data)) {
						const color_pickers = this.#form.querySelectorAll(`.${ZBX_STYLE_COLOR_PICKER}`);
						const used_colors = [];

						for (const color_picker of color_pickers) {
							if (color_picker.color !== '') {
								used_colors.push(color_picker.color);
							}
						}

						row_data.color = colorPalette.getNextColor(used_colors);
					}
				}
			})
			.on('afteradd.dynamicRows', () => this.#updateForm())
			.on('afterremove.dynamicRows', () => this.#updateForm());

		// Initialize form elements.
		this.#updateForm();

		this.#form.style.display = '';
		this.#overlay.recoverFocus();
	}

	#updateForm() {
		const data_type = this.#form.querySelector('[name="data"]').value;

		const data_type_item_value = data_type == <?= Widget::DATA_ITEM_VALUE ?>;
		const data_type_text = data_type == <?= Widget::DATA_TEXT ?>;

		const display_value_as = this.#form.querySelector('[name="display_value_as"]:checked')?.value
			?? '0';
		const display_value_as_numeric = display_value_as == 0;

		const display = this.#form.querySelector('[name="display"]:checked')?.value
			?? '<?= Widget::DISPLAY_AS_IS ?>';
		const display_as_is = display == <?= Widget::DISPLAY_AS_IS ?>;
		const display_bar = display == <?= Widget::DISPLAY_BAR ?>;
		const display_indicators = display == <?= Widget::DISPLAY_INDICATORS ?>;

		// Item pattern.
		const prepend_item = this.#form.querySelector('[name="prepend_item"]').checked;
		for (const element of this.#form.querySelectorAll('.js-item-row')) {
			element.style.display = data_type_item_value ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !data_type_item_value;
			}
		}

		// Prepend substring bounding
		for (const element of this.#form.querySelectorAll('.js-prepend-ext-row')) {
			element.style.display = data_type_item_value && prepend_item ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !(data_type_item_value && prepend_item);
			}
		}

		// Text.
		for (const element of this.#form.querySelectorAll('.js-text-row')) {
			element.style.display = data_type_text ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !data_type_text;
			}
		}

		// Display.
		for (const element of this.#form.querySelectorAll('.js-display-row')) {
			element.style.display = data_type_item_value && display_value_as_numeric ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !data_type_item_value || !display_value_as_numeric;
			}
		}

		// Min/Max.
		const show_min_max = data_type_item_value && display_value_as_numeric && (display_bar || display_indicators);

		for (const element of this.#form.querySelectorAll('.js-min-max-row')) {
			element.style.display = show_min_max ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !show_min_max;
			}
		}

		// Numeric-only features (Thresholds, Decimal places, etc.)
		for (const element of this.#form.querySelectorAll('.js-numeric-row, .js-thresholds-row')) {
			element.style.display = data_type_item_value && display_value_as_numeric ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !data_type_item_value || !display_value_as_numeric;
			}
		}

		// Highlights.
		for (const element of this.#form.querySelectorAll('.js-highlights-row')) {
			element.style.display = data_type_item_value && !display_value_as_numeric ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !data_type_item_value || display_value_as_numeric;
			}
		}

		// Apply to node (Parent Status)
		const apply_to_node = this.#form.querySelector('[name="apply_to_node"]').checked;
		for (const element of this.#form.querySelectorAll('.js-apply-node-row')) {
			element.style.display = data_type_item_value ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !data_type_item_value;
			}
		}

		// Parent Status Priority
		for (const element of this.#form.querySelectorAll('.js-parent-priority-row')) {
			element.style.display = data_type_item_value && apply_to_node ? '' : 'none';

			for (const select of element.querySelectorAll('select')) {
				select.disabled = !(data_type_item_value && apply_to_node);
			}
		}
	}

	submit() {
		const curl = new Curl(this.#form.getAttribute('action'));
		const fields = getFormFields(this.#form);

		this.#overlay.setLoading();

		this.#post(curl.getUrl(), fields);
	}

	#post(url, fields) {
		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
			body: urlEncodeData(fields)
		})
			.then(response => response.json())
			.then(response => {
				if ('error' in response) {
					throw {error: response.error};
				}

				overlayDialogueDestroy(this.#overlay.dialogueid);

				this.#dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response}));
			})
			.catch(exception => {
				for (const element of this.#form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

				this.#form.parentNode.insertBefore(message_box, this.#form);
			})
			.finally(() => {
				this.#overlay.unsetLoading();
			});
	}
};
