=== Users Data Exporter ===
Contributors: Taher Uddin
Donate link: http://example.com/
Tags: user, meta, data, export, xls, xlsx, excel, spreadsheet, AJAX, filter by user id, filter by user role, filter by email, filter by login, filter by registration date, filter by paid membership pro level, filter by user meta  
Requires at least: 3.0.1
Tested up to: 3.4
Stable tag: 1.0
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Robust way to export selected users data to .xlsx spreadsheet, specially when number of users of a site is very big like 100,000+.


== Description ==

Robust way to export selected users data to .xlsx spreadsheet, specially when number of users of a site is very big like 100,000+.

Available Filters:
- User Roles (One or more)
- User Email
- User Login
- User ID
- User Registration Date
- Paid Membership Pro Level
- User Meta Fields (One or more)

This plugin splits task of capturing users data by applying AJAX and protects from the issue of PHP execution timed out.

Filter option for Paid Membership Pro Level is available only if Paid Membership Pro plugin is available.

Single execution length can be increased to adjust with available server resources and number users intending to export.


== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/user-data-exporter` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Access at Users->Users Data Exporter


== Frequently Asked Questions ==

= Can I use "%" comarison for "equal to" filter? =

Yes, you can if the WordPress is using a MySQL database.

= Taking too long to complete the exporting. How can I shorten the duration? =

Increase the single execution length. But do not increase it too much to avoid PHP execution timeout.


== Screenshots ==

1. Initial Interface 
screenshot-1.png

2. Waiting While Exporting 
screenshot-2.png

3. Exported .xlsx File in Mirosoft Excel 
screenshot-3.png



