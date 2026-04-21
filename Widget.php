<?php declare(strict_types = 0);

namespace Modules\AdvancedHostGrid;

use Zabbix\Core\CWidget;

class Widget extends CWidget {

	// Grouping attribute types.
	public const GROUP_BY_TAG_VALUE = 0;
	public const GROUP_BY_HOST_GROUP = 1;
	public const GROUP_BY_SEVERITY = 2;
	public const GROUP_BY_HOST_INVENTORY = 3;
	public const GROUP_BY_ITEM_VALUE = 4;
	public const GROUP_BY_HOST_NAME = 5;
	public const GROUP_BY_ITEM_NAME = 6;

	// Column data types.
	public const DATA_HOST_NAME = 0;
	public const DATA_ITEM_VALUE = 1;
	public const DATA_TEXT = 2;

	// Display types for item value columns.
	public const DISPLAY_AS_IS = 0;
	public const DISPLAY_BAR = 1;
	public const DISPLAY_INDICATORS = 2;

	// Ordering.
	public const ORDER_TOP_N = 0;
	public const ORDER_BOTTOM_N = 1;

	// Column filter operators.
	public const FILTER_OP_NONE = 0;
	public const FILTER_OP_EQUALS = 1;
	public const FILTER_OP_NOT_EQUALS = 2;
	public const FILTER_OP_GREATER = 3;
	public const FILTER_OP_LESS = 4;
	public const FILTER_OP_GREATER_EQUAL = 5;
	public const FILTER_OP_LESS_EQUAL = 6;
	public const FILTER_OP_CONTAINS = 7;
	public const FILTER_OP_NOT_CONTAINS = 8;
	public const FILTER_OP_EXISTS = 9;
	public const FILTER_OP_NOT_EXISTS = 10;

	// Default color palette.
	public const DEFAULT_COLOR_PALETTE = [
		'FF465C', 'B0AF07', '0EC9AC', '524BBC', 'ED1248',
		'D1E754', '2AB5FF', '385CC7', 'EC1594', 'BAE37D',
		'6AC8FF', 'EE2B29', '3CA20D', '6F4BBC', '00A1FF',
		'F3601B', '1CAE59', '45CFDB', '894BBC', '6D6D6D'
	];

	// Label for groups that have an empty/blank value.
	public const UNKNOWN_GROUP_LABEL = 'Unknown';

	// Host inventory field map (field key => display label).
	public const INVENTORY_FIELDS = [
		'type' => 'Type',
		'type_full' => 'Type (Full details)',
		'name' => 'Name',
		'alias' => 'Alias',
		'os' => 'OS',
		'os_full' => 'OS (Full details)',
		'os_short' => 'OS (Short)',
		'serialno_a' => 'Serial number A',
		'serialno_b' => 'Serial number B',
		'tag' => 'Tag',
		'asset_tag' => 'Asset tag',
		'macaddress_a' => 'MAC address A',
		'macaddress_b' => 'MAC address B',
		'hardware' => 'Hardware',
		'hardware_full' => 'Hardware (Full details)',
		'software' => 'Software',
		'software_full' => 'Software (Full details)',
		'software_app_a' => 'Software application A',
		'software_app_b' => 'Software application B',
		'software_app_c' => 'Software application C',
		'software_app_d' => 'Software application D',
		'software_app_e' => 'Software application E',
		'contact' => 'Contact',
		'location' => 'Location',
		'location_lat' => 'Location latitude',
		'location_lon' => 'Location longitude',
		'notes' => 'Notes',
		'chassis' => 'Chassis',
		'model' => 'Model',
		'hw_arch' => 'HW architecture',
		'vendor' => 'Vendor',
		'contract_number' => 'Contract number',
		'installer_name' => 'Installer name',
		'deployment_status' => 'Deployment status',
		'url_a' => 'URL A',
		'url_b' => 'URL B',
		'url_c' => 'URL C',
		'host_networks' => 'Host networks',
		'host_netmask' => 'Host subnet mask',
		'host_router' => 'Host router',
		'oob_ip' => 'OOB IP address',
		'oob_netmask' => 'OOB subnet mask',
		'oob_router' => 'OOB router',
		'date_hw_purchase' => 'Date HW purchased',
		'date_hw_install' => 'Date HW installed',
		'date_hw_expiry' => 'Date HW maintenance expires',
		'date_hw_decomm' => 'Date HW decommissioned',
		'site_address_a' => 'Site address A',
		'site_address_b' => 'Site address B',
		'site_address_c' => 'Site address C',
		'site_city' => 'Site city',
		'site_state' => 'Site state / province',
		'site_country' => 'Site country',
		'site_zip' => 'Site ZIP / postal',
		'site_rack' => 'Site rack location',
		'site_notes' => 'Site notes',
		'poc_1_name' => 'Primary POC name',
		'poc_1_email' => 'Primary POC email',
		'poc_1_phone_a' => 'Primary POC phone A',
		'poc_1_phone_b' => 'Primary POC phone B',
		'poc_1_cell' => 'Primary POC cell',
		'poc_1_screen' => 'Primary POC screen name',
		'poc_1_notes' => 'Primary POC notes',
		'poc_2_name' => 'Secondary POC name',
		'poc_2_email' => 'Secondary POC email',
		'poc_2_phone_a' => 'Secondary POC phone A',
		'poc_2_phone_b' => 'Secondary POC phone B',
		'poc_2_cell' => 'Secondary POC cell',
		'poc_2_screen' => 'Secondary POC screen name',
		'poc_2_notes' => 'Secondary POC notes'
	];

	public function getTranslationStrings(): array {
		return [
			'class.widget.js' => [
				'No data' => _('No data'),
				'Unknown' => _('Unknown'),
				'Collapse all' => _('Collapse all'),
				'Expand all' => _('Expand all'),
				'host' => _('host'),
				'hosts' => _('hosts')
			]
		];
	}
}
