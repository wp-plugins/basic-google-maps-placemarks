=== Basic Google Maps Placemarks ===
Contributors: iandunn
Donate link: http://www.doctorswithoutborders.org/
Tags: google maps, map, markers, placemarks
Requires at least: 2.9
Tested up to: 3.1.2
Stable tag: 1.0

Embeds a Google Map into your site and lets you add markers with custom icons.


== Description ==
BGMP creates a custom post type for Google Maps placemarks, where you can enter a title, description and marker coordinates and then set a featured image. You can set the map's size, default location and zoom, and then embed it via a shortcode inside a page. The placemarks will show up on the map using the featured image as the icon, and when you click on a marker it will pop up a box with the title and description.

You can see a live version of the plugin in use at [washingtonhousechurches.net](http://washingtonhousechurches.net).


== Installation ==
1. Upload the *basic-google-maps-placemarks* directory to your *wp-content/plugins/* directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to the 'Writing' page under the 'Settings' menu, then enter the width, height, zoom, etc and click the save button.
4. Type *[bgmp-map]* in a page to embed the map on that page.
5. Go to the Placemarks menu and add your markers. Make sure you set a featured image for each one.
6. (optional) You can also add the *[bgmp-list]* shortcode to a page for a text listing of all of the placemarks.

== Frequently Asked Questions ==

= I added the [bgmp-map] shortcode to a page, but I don't see the map there. =
Make sure you go to the settings page and enter width/height values. Click on the 'Save Settings' button, even if you're just using the defaults.

= The map doesn't look right. =
This is probably because some rules from your theme's stylesheet are being applied to the map. Contact your theme developer for advice on how to override the rules.

= One of the markers isn't showing up. =
Make sure you've set a featured image on the placemark, because it will be used as the icon.

= How do I get rid of the scrollbars on the marker popup? =
Go to the Settings page and increase the width/height of the info window.
	
= How can I contact you? =
If you're having a problem with the plugin, please use [the support forum](http://wordpress.org/tags/basic-google-maps-placemarks?forum_id=10) to increase your chances of getting an answer and so that others can benefit from the answers you get. I monitor them and other people in the community may be able to help you too.

If you just have a general comment or suggestion, you can use the [contact form](http://iandunn.name/contact) on my website, although I don't always have time to respond.

== Screenshots ==
1. This is how the map looks once it's been embeded into a page.
2. The Placemarks page, where you can add/edit/delete map markers.
3. A example placemark. 
4. The map settings on the Writing Settings page.


== Changelog ==
= 1.0 =
* Initial release


== Upgrade Notice ==
= 1.0 =
Initial release