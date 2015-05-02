<div class="wrap">
	<?php // maybe rename this so it doesn't match settings.php in the root dir ?>

	<div id="icon-options-general" class="icon32"><br /></div>
	<h2><?php printf( __( '%s Settings', 'bgmp' ), BGMP_NAME ); ?></h2>

	<form method="post" action="options.php">
		<?php do_action( 'bgmp_settings-before' ); ?>

		<?php // @todo add nonce for settings? ?>

		<div id="bgmp_settings-fields">
			<?php settings_fields( 'bgmp_settings' ); ?>
			<?php do_settings_sections( 'bgmp_settings' ); ?>

			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button-primary" value="<?php echo esc_attr( 'Save Changes' ); ?>" />
				<!-- todo i18n -->
			</p>
		</div>
		<!-- /#bgmp_settings-fields -->

		<div id="bgmp_settings-meta-boxes" class="metabox-holder">
			<div class="postbox-container">
				<?php do_meta_boxes( 'settings_page_' . 'bgmp_settings', 'side', NULL ); ?>
				<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce' ); ?>
			</div>
		</div>

		<?php do_action( 'bgmp_settings-after' ); ?>
	</form>
</div> <!-- .wrap -->
