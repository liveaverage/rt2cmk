# rt2cmk
Standalone plugin to support one-click additions/removals/modifications of networked objects from RackTables to Check_MK.

## Requirements

Check_MK 1.2.6p5 or above 
php-snmp

## Installation

Copy **monitor-cmk-conf.php.sample** and **monitor-cmk.php** to your RackTables plugin directory. Modify settings in **monitor-cmk-conf.php.sample** and rename to **monitor-cmk-conf.php**.

## Notes

To accomodate the SNMP agent tag, you should make certain you have appropriate Check_MK rules to assign SNMP communities.
