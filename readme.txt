=== Basic Google Maps Placemarks ===
Contributors: iandunn
Donate link: http://www.doctorswithoutborders.org
Tags: google map, map, embed, marker, placemark, icon
Requires at least: 3.0
Tested up to: 3.2.1
Stable tag: 1.4

Embeds a Google Map into your site and lets you add markers with custom icons and information windows.


== Description ==
BGMP creates a [custom post type](http://codex.wordpress.org/Post_Types) for placemarks (markers) on a Google Map. The map is added to a page or post using a shortcode, and there are settings which define it's size, center and zoom level.

Then you can create markers that will show up on the map using the featured image as the map icon. When a marker is clicked on, a box will appear showing its title and description. There's also a shortcode that will output a text listing of all of the markers.

You can see a live example of the map it creates at [washingtonhousechurches.net](http://washingtonhousechurches.net).


== Installation ==

**Installing:**

1. Upload the *basic-google-maps-placemarks* directory to your *wp-content/plugins/* directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to the 'Basic Google Maps Placemarks' page under the 'Settings' menu, then enter the width, height, zoom, etc and click the save button.
4. Type the *[bgmp-map]* [shortcode](http://coding.smashingmagazine.com/2009/02/02/mastering-wordpress-shortcodes/) in a page or post to embed the map on that page/post.
5. Go to the Placemarks menu and add your markers.
6. (optional) You can also add the *[bgmp-list]* shortcode to a page for a text listing of all of the placemarks.

**Upgrading:**

1. Just re-upload the plugin folder to the wp-content/plugins directory to overwrite the old files.
2. If you're upgrading from version 1.0, you'll need to populate the new address field based on existing coordinates. Just deactiveate and re-activate the plugin and it'll do that automatically. This may take a minute or two, depending on the number of placemarks you have.


== Frequently Asked Questions ==

= The map doesn't look right. =
This is probably because some rules from your theme's stylesheet are being applied to the map. Contact your theme developer for advice on how to override the rules.

= The page says 'Loading map...', but the map never shows up. =
[Check to see if there are any Javascript errors](http://www.cmsmarket.com/resources/dev-corner/92-how-to-check-for-javascript-errors) caused by your theme or other plugins, because an error by any script will prevent all the other scripts from running.

Also, make sure your theme is calling *[wp_footer()](http://codex.wordpress.org/Function_Reference/wp_footer)* right before the closing *body* tag in footer.php. 

= How can I force the info. window width and height to always be the same size? =
Add the following styles to your theme's style.css file:

`
.bgmp_placemark
{
	width: 450px;
	height: 350px;
}
`

= Can registered users create their own placemarks? =
Yes. The plugin creates a [custom post type](http://codex.wordpress.org/Post_Types), so it has the same [permission structure](http://codex.wordpress.org/Roles_and_Capabilities) as regular posts/pages.

= Will the plugin work in WordPress MultiSite? =
Yes. Version 1.2 added support for MultiSite installations.

= Placemarks aren't showing up the map =
If your theme is calling `add_theme_support( 'post-thumbnails' )` and passing in a specific list of post types -- rather than enabling support for all post types -- then it should check if some post types are already registered and include those as well. This only applies if it's hooking into `after_theme_setup` with a priority higher than 10. Contact your theme developer and ask them to fix their code.

= Two markers are overlapping. How can I set which one is on top? =
Edit the placemark and give it a higher stacking order in the Stacking Order meta box in the right column.

= How can I override the styles the plugin applies to the map? =
The width/height of the map and marker information windows are always defined in the Settings, but you can override everything else by putting this code in your theme's functions.php file:

`
add_action('init', 'my_theme_name_bgmp_style');
function my_theme_name_bgmp_style()
{
	wp_deregister_style( 'bgmp_style' );
	wp_register_style(
		'bgmp_style',
		get_bloginfo('template_url') . '/bgmp-style.css'
	);
	wp_enqueue_style( 'bgmp_style' );
}
`

Then create a bgmp-style.css file inside your theme directory and put your styles there. If you'd prefer, you could also just make it an empty file and put the styles in your main style.css, but either way you need to register and enqueue a style with the `bgmp_style` handle, because the plugin checks to make sure the CSS and JavaScript files are loaded before embedding the map.

= I upgraded to the latest version and now the map isn't working. =
If you're running a caching plugin like WP Super Cache, make sure you delete the cache contents so that the latest files are loaded, and then refresh your browser.

= How do I add the shortcode to a page? =
Just type *[bgmp-map]* on any post of page, and then view that page.

= Can I have multiple maps with different sets of placemarks for each? =
No. You can add embed the map on multiple pages, but it will always pull all of the placemarks onto it.

= I get an error when using do_shortcode() to call the map shortcode =
For efficiency, the plugin only loads the required JavaScript, CSS and markup files on pages where it detects the map shortcode is being called. It's not possible to detect when [do_shortcode()](http://codex.wordpress.org/Function_Reference/do_shortcode) is used, so you need to manually let the plugin know to load the files by adding this code to your theme:

`
function my_theme_name_bgmp_shortcode_check()
{
	global $post;
	
	$shortcodePageSlugs = array(
		'first-page-slug',
		'second-page-slug',
		'hello-world'
	);
	
	if( $post )
		if( in_array( $post->post_name, $shortcodePageSlugs ) )
			add_filter( 'bgmp_mapShortcodeCalled', 'your_theme_name_bgmp_shortcode_called' );
}
add_action( 'wp', 'my_theme_name_bgmp_shortcode_check' );

function your_theme_name_bgmp_shortcode_called( $mapShortcodeCalled )
{
	return true;
}
`

Copy and paste that into your theme's *functions.php* file, update the function names and filter arguments, and then add the slugs of any pages/posts containing the map to $shortcodePageSlugs. If you're using it on the home page, the slug will be 'home'.

This only works if the file that calls do_shortcode() is [registered as a page template](http://codex.wordpress.org/Pages#Creating_Your_Own_Page_Templates) and assigned to a page.

= Can I use coordinates to set the marker, instead of an address? =
Yes. You can type anything into the Address field that you would type into a standard Google Maps search field, which includes coordinates. For example: 48.61322,-123.3465.

= How can I get help when I'm having a problem? =
Check [the support forum](http://wordpress.org/tags/basic-google-maps-placemarks?forum_id=10), because there's half a chance your problem has already been answered there, and the answer you get will help others in the future. If you can't find anything, then start a new thread with a detailed description of your problem and the URL to your site. I monitor the forums and will respond as my schedule permits.

If you create a post, make sure it's tagged with `basic-google-maps-placemarks` so that I get a notification. If you use the link above it'll automatically tag it for you. Also make sure you check the 'Notify me of follow-up posts via email' box so you won't miss any replies.

= How can I send feedback that isn't of a support nature? =
You can send me feedback/comments/suggestions using the [contact form](http://iandunn.name/contact) on my website, and I'll respond as my schedule permits. *Please **don't** use this if you're having trouble using the plugin;* use the support forums instead (see above question for details). **I only provide support using the forums, not over e-mail.**


== Screenshots ==
1. This is an example of how the map looks once it's been embedded into a page.
2. The Placemarks page, where you can add/edit/delete map markers.
3. A example placemark. 
4. The map settings.


== Changelog ==

= 1.4 =
* Added meta box for placemark stacking order.
* Upgraded PHP requirement to version 5.2
* Moved settings from the Writing page to their own page
* Fixed bug where [multiple shortcodes on a page would prevent detection of map shortcode when called from do_shortcode()](http://wordpress.org/support/topic/plugin-basic-google-maps-placemarks-javascript-andor-css-files-arent-loaded#post-2280215).
* Fixed bug where [empty address would sometimes prevent placemarks from appearing](http://wordpress.org/support/topic/basic-google-maps-placemark-firefox-not-rendering-all-placemarks).
* Stopped trying to geocode empty addresses.
* Updated the FAQ to mention that [do_shortcode() has to be called from a registered page template that's been assiged to a page](http://wordpress.org/support/topic/plugin-basic-google-maps-placemarks-javascript-andor-css-files-arent-loaded?replies=14#post-2287781).

= 1.3.2 =
* The markers are now sorted alphabetically in the [bgmp-list] shortcode
* More theme styles are overriden to prevent the Maps API infowindow scroller bug
* The View screen in the Administration Panels is now sorted alphabetically
* enqueuemessage() is now declared protected instead of public

= 1.3.1 =
* Fixes bug where [standard posts and pages would lose the 'Set Featured Image' meta box](http://wordpress.org/support/topic/featured-image-option-not-showing)

= 1.3 =
* Removed AJAX because unnecessary, slow and causing several bugs
* Removed now-unnecessary front-end-footer.php
* Fixed bug where [placemarks weren't showing up when theme didn't support post-thumbnails](http://wordpress.org/support/topic/no-placemarks-on-theme-raindrops)
* Fixed bug where non-string value passed to enqueueMessage() would cause an error
* Set loadResources() to fire on 'wp' action instead of 'the_posts' filter
* [Added title to markers](http://wordpress.org/support/topic/plugin-basic-google-maps-placemarks-add-mouseover-title-to-marker)
* Enabled support for BGMP post type revisions

= 1.2.1 = 
* Fixes the [info window height bug](http://wordpress.org/support/topic/plugin-basic-google-maps-placemarks-info-window-width-height)

= 1.2 =
* Fixes bug from 1.1.3 where the default options weren't set on activation
* MultiSite - Fixed [activation error](http://wordpress.org/support/topic/plugin-basic-google-maps-placemarks-call-to-undefined-function-wp_get_current_user) from relative require paths
* MultiSite - Added support for network activation, new site activation
* MultiSite - Enabled image upload button at activation
* Fixed [bugs](http://wordpress.stackexchange.com/questions/20130/custom-admin-notices-messages-ignored-during-redirects) in message handling functions
* Fixed ['active version' stats bug](http://wordpress.stackexchange.com/questions/21132/repository-reporting-incorrect-plugin-active-version-stat)
* Added notification when geocode couldn't resolve correct coordinates

= 1.1.3 = 
* CSS and JavaScript files are only loaded on pages where the map shortcode is called
* Fixed [fatal error when trying to activate on PHP 4 servers](http://wordpress.org/support/topic/fatal-error-when-activating-basic-google-maps-placemarks)
* Styles updated for twentyeleven based themes
* Switched to wrapper function for $ instead of *$ = jQuery.noConflict();*
* JavaScript functions moved inside an object literal

= 1.1.2 = 
* Settings moved to separate class
* Updated Wordpress requirement to 3.0. Listing it at 2.9 in previous versions was a mistake.

= 1.1.1 =
* JavaScript files only loaded when needed
* Fixed bug where [JavaScript files were loaded over HTTP when they should have been over HTTPS](http://iandunn.name/basic-google-maps-placemarks-plugin/)
* A few minor back-end changes

= 1.1 = 
* Addresses are automatically geocoded
* Default markers used when no featured image set
* Default settings saved to database upon activation

= 1.0 =
* Initial release


== Upgrade Notice ==

= 1.4 =
BGMP 1.4 adds the ability to set a stacking order for placemarks that overlap, and fixes several minor bugs.

= 1.3.2 =
BGMP 1.3.2 sorts the markers in the [bgmp-list] shortcode alphabetically, and prevents the information window scrollbar bug in more cases.

= 1.3.1 =
BGMP 1.3.1 fixes a bug where standard posts and pages would lose the 'Set Featured Image' meta box.

= 1.3 =
BGMP 1.3 loads the map/placemarks faster and contains several bug fixes.

= 1.2.1 =
BGMP 1.2.1 fixes a bug related to the marker's info window width and height.

= 1.2 = 
BGMP 1.2 adds support for WordPress MultiSite and fixes several minor bugs.

= 1.1.3 = 
BGMP 1.1.3 contains bug fixes, performance improvements and updates for WordPress 3.2 compatibility.

= 1.1.2 = 
BGMP 1.1.2 just has some minor changes on the back end and a bug fix, so if you're not having problems then there's really no reason to upgrade, other than getting rid of the annoying upgrade notice.

= 1.1.1 = 
BGMP 1.1.1 only loads the JavaScript files when needed, making the rest of the pages load faster, and also fixes a minor bugs related to HTTPS pages.

= 1.1 =
BGMP 1.1 will automatically geocode addresses for you, so you no longer have to manually lookup marker coordinates. After uploading the new files, deactivate and reactivate the plugin to populate the new address field on each Placemark based on the existing coordinates.

= 1.0 =
Initial release.