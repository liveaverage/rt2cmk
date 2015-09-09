# rt2cmk
Standalone plugin to support one-click additions/removals/modifications of networked objects from RackTables to Check_MK.

## Requirements

Check_MK 1.2.6p5 or above<br>
Check_MK Automation Account<br>
php-snmp

## Installation

Copy **monitor-cmk-conf.php.sample** and **monitor-cmk.php** to your RackTables plugin directory.
Modify settings in **monitor-cmk-conf.php.sample** and rename to **monitor-cmk-conf.php**.

## Usage

An object's FQDN must be set (in RackTables) in order for the plugin tab to appear.

 * Simply click the "Add host" button to auto-add a host, which also auto-creates a folder hiearchy to match the object's rack location (e.g. Row 11/Rack J)
 * If the RackTables object FQDN matches a hostname already configured in Check_MK the object's configuration and tags are shown on the Check_MK tab.
 * Click the "Remove host" button to delete the host from Check_MK.
 * Click the "Activate all changes" button to immediately activate **all** pending changes, including those made *outside* of RackTables. BE CAREFUL WITH THIS!
 * After moving an object within RackTables, simply click the "Renew host" button, which performs a remove & add operation,
	which inserts the object in the correct Check_MK folder hierarchy.

## Notes

To accomodate the SNMP agent tag, you should make certain you have appropriate Check_MK rules to assign SNMP communities.
