# Advanced Host Grid - Zabbix Module

This widget module is meant to provide a status of all hosts, similar to the Nodes list in SolarWinds. It is similar to the native Top Hosts/items widgets, but with more customization.

Features:
* Increased host limit to 9999.
* Item Value Column allows simple pattern matching.
* Nested Grouping to 3 levels to create a Tree View.
   * Custom Labels/Color overrides for each Group level.
* Filter by multiple values with custom logic.
* Custom Maintenance grouping.

## Installation

1. **Download and Deploy**:
   ```bash
   # 1. Create the directory in standard Zabbix installation path
   sudo mkdir -p /usr/share/zabbix/ui/modules/advanced_host_grid

   # 2. Download and extract
   curl -L https://github.com/efcamp/advanced_host_grid/archive/refs/heads/main.tar.gz \
   | sudo tar -xz -C /usr/share/zabbix/ui/modules/advanced_host_grid --strip-components=1 \
   --exclude='.gitattributes' --exclude='LICENSE' --exclude='README.md'
   ```

2. **Permissions**:
   Set the correct ownership and permissions so the web server can read the module files. (Assuming `www-data` is your web user, change if different):
   ```bash
   # Change ownership to web user
   sudo chown -R www-data:www-data /usr/share/zabbix/ui/modules/advanced_host_grid

   # Set directory permissions to 755 and files to 644
   sudo find /usr/share/zabbix/ui/modules/advanced_host_grid -type d -exec chmod 755 {} +
   sudo find /usr/share/zabbix/ui/modules/advanced_host_grid -type f -exec chmod 644 {} +
   ```

3. **Activation**:
   * Log in to your Zabbix Frontend as an Admin.
   * Go to **Administration** > **General** > **Modules**.
   * Click **Scan modules**.
   * Locate **Advanced Host Grid** in the list and change its status to **Enabled**.
   
## Configuration
1. **Maintenance**:
   * Check the box for **Show hosts in maintenance** and **Maintenance grouping override**.
   * In the **Maintenance override** field, enter the **level**:**label**:**color** for hosts in Maintenance to use.
      * level: This is the **Group by** position that Maintenance group will replace, for example '1:' would place it as a root level replacing the first "Group by" category.
      * label: This will replace the node level label.
      * color: a hex color for the maintenance grouping to take, if left blank it will use default 6c6c6c

2. **Columns**:

3. **Group by**:

4. **Filter by**:

5. **Order by**: 
