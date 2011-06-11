=== Basic Google Maps Placemarks ===
Contributors: iandunn
Donate link: http://www.doctorswithoutborders.org/
Tags: google maps, map, markers, placemarks
Requires at least: 2.9
Tested up to: 3.1.3
Stable tag: 1.1.1

Embeds a Google Map into your site and lets you add markers with custom icons.


== Description ==
BGMP creates a [custom post type](http://codex.wordpress.org/Post_Types) for Google Maps placemarks, where you can enter a title, description and address and then set a featured image. You can set the map's size, default location and zoom, and then embed it via a shortcode inside a page. The placemarks will show up on the map using the featured image as the icon, and when you click on a marker it will display a box with the title and description. It also supplies a shortcode to get a text-based list of the markers.

You can see a live example of the map it creates at [washingtonhousechurches.net](http://washingtonhousechurches.net).


== Installation ==
1. Upload the *basic-google-maps-placemarks* directory to your *wp-content/plugins/* directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to the 'Writing' page under the 'Settings' menu, then enter the width, height, zoom, etc and click the save button.
4. Type *[bgmp-map]* in a page to embed the map on that page.
5. Go to the Placemarks menu and add your markers.
6. (optional) You can also add the *[bgmp-list]* shortcode to a page for a text listing of all of the placemarks.

**Upgrading:**

1. Just re-upload the plugin folder to the wp-content/plugins directory to overwrite the old files
2. If you're upgrading from version 1.0, you'll need to populate the new address field based on existing coordinates. Just deactiveate and re-activate the plugin and it'll do that automatically. This may take a minute or two, depending on the number of placemarks you have.


== Frequently Asked Questions ==

= The map doesn't look right. =
This is probably because some rules from your theme's stylesheet are being applied to the map. Contact your theme developer for advice on how to override the rules.

= The page says 'Loading map...', but the map never shows up =
Make sure your theme is calling *[wp_footer()](http://codex.wordpress.org/Function_Reference/wp_footer)* right before the </body> tag in footer.php. 

If that doesn't work, make sure that there aren't any Javascript errors caused by your theme or other plugins, because an error by any script will prevent all the other scripts from running.

= How do I get rid of the scrollbars on the marker popup? =
Go to the Settings page and increase the width/height of the info window.

= Can registered users create their own placemarks? =
Yes. The plugin creates a [custom post type](http://codex.wordpress.org/Post_Types), so it has the same [permission structure](http://codex.wordpress.org/Roles_and_Capabilities) as regular posts/pages.

= How can I override the styles the plugin applies to the map? =
The width/height of the map and marker window are always defined in the Settings, but you can override everything else by calling [wp_dequeue_style( 'BGMP_style' )](http://codex.wordpress.org/Function_Reference/wp_dequeue_style) -- that's an underscore between "BGMP" and "style" -- inside an [init hook](http://codex.wordpress.org/Action_Reference), and then putting your own styles in your theme's stylesheet.

= I upgraded to the latest version and now the map isn't working =
If you're running a caching plugin like WP Super Cache, make sure you delete the cache contents so that the latest files are loaded, and refresh your browser.

= How can I contact you? =
If you're having a problem with the plugin, please use [the support forum](http://wordpress.org/tags/basic-google-maps-placemarks?forum_id=10) to increase your chances of getting an answer and so that others can benefit from the answers you get. I monitor them and other people in the community may be able to help you too.

If you just have a general comment or suggestion, you can use the [contact form](http://iandunn.name/contact) on my website, although I don't always have time to respond.

== Screenshots ==
1. This is an example of how the map looks once it's been embeded into a page.
2. The Placemarks page, where you can add/edit/delete map markers.
3. A example placemark. 
4. The map settings on the Writing Settings page.


== Changelog ==

= 1.1.1 =
* JavaScript files only loaded when needed
* JavaScript files loaded via HTTPS when the page is -- see http://iandunn.name/basic-google-maps-placemarks-plugin/#comment-4574
* A few minor back-end changes

= 1.1 = 
* Addresses are automatically geocoded
* Default markers used when no featured image set
* Default settings saved to database upon activation

= 1.0 =
* Initial release


== Upgrade Notice ==

= 1.1.1 = 
Basic Google Maps Placemarks 1.1.1 only loads the JavaScript files when needed, making the rest of the pages load faster, and also fixes a minor bugs related to HTTPS pages.

= 1.1 =
Basic Google Maps Placemarks 1.1 will automatically geocode addresses for you, so you no longer have to manually lookup marker coordinates. After uploading the new files, deactivate and reactivate the plugin to populate the new address field on each Placemark based on the existing coordinates.

= 1.0 =
Initial release