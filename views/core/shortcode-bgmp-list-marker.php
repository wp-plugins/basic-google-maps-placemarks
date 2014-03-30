<li id="bgmp_list-item-<?php esc_attr_e( $p->ID ); ?>" class="bgmp_list-item">
	<h3 class="bgmp_list-placemark-title">
		<?php echo apply_filters( 'the_title', $p->post_title ); ?>

		<?php if ( $viewOnMap ) : ?>
			<span class="bgmp_view-on-map-container">
				[<a href="javascript:;" data-marker-id="<?php esc_attr_e( $p->ID ); ?>" class="bgmp_view-on-map">View On Map</a>]
			</span>
		<?php endif; ?>
	</h3>

	<div class="bgmp_list-description">
		<?php /* note: don't use setup_postdata/get_the_content() in this instance -- http://lists.automattic.com/pipermail/wp-hackers/2013-January/045053.html */ ?>
		<?php echo apply_filters( 'the_content', $p->post_content ); ?>
	</div>

	<p class="bgmp_list-link">
		<a href="<?php echo esc_url( 'http://google.com/maps?q=' . str_replace( ' ', '+', $address ) ); ?>">
			<?php echo wp_kses( $address, wp_kses_allowed_html( 'post' ) ); ?>
		</a>
	</p>
</li>
