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

	init({form_id, thresholds, colors}) {
		this.#overlay = overlays_stack.getById('advhostgrid-column-edit-overlay');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#form = document.getElementById(form_id);

		const inputs = this.#form.querySelectorAll('[name="data"], [name="display"]');

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

		// Initialize form elements.
		this.#updateForm();

		this.#form.style.display = '';
		this.#overlay.recoverFocus();
	}

	#updateForm() {
		const data_type = this.#form.querySelector('[name="data"]').value;

		const data_type_item_value = data_type == <?= Widget::DATA_ITEM_VALUE ?>;
		const data_type_text = data_type == <?= Widget::DATA_TEXT ?>;

		const display = this.#form.querySelector('[name="display"]:checked')?.value
			?? '<?= Widget::DISPLAY_AS_IS ?>';
		const display_bar = display == <?= Widget::DISPLAY_BAR ?>;
		const display_indicators = display == <?= Widget::DISPLAY_INDICATORS ?>;

		// Item pattern.
		for (const element of this.#form.querySelectorAll('.js-item-row')) {
			element.style.display = data_type_item_value ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !data_type_item_value;
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
			element.style.display = data_type_item_value ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !data_type_item_value;
			}
		}

		// Min/Max.
		const show_min_max = data_type_item_value && (display_bar || display_indicators);

		for (const element of this.#form.querySelectorAll('.js-min-max-row')) {
			element.style.display = show_min_max ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !show_min_max;
			}
		}

		// Thresholds.
		for (const element of this.#form.querySelectorAll('.js-thresholds-row')) {
			element.style.display = data_type_item_value ? '' : 'none';

			for (const input of element.querySelectorAll('input')) {
				input.disabled = !data_type_item_value;
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
