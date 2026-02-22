<?php /*** The template for displaying comments */
if ( post_password_required() ) { return; }
?>

<div id="comments" class="comments-area">

	<?php
	if ( have_comments() ) :
		?>
		<h2 class="comments-title">
			<?php
			$battleplan_comment_count = get_comments_number();
			if ( '1' === $battleplan_comment_count ) {
				printf(
					esc_html__( 'One comment on &ldquo;%1$s&rdquo;', 'battleplan' ),
					'<span>' . wp_kses_post( get_the_title() ) . '</span>'
				);
			} else {
				printf(
					/* translators: 1: comment count number, 2: title. */
					esc_html( _nx( '%1$s comment on &ldquo;%2$s&rdquo;', '%1$s comments on &ldquo;%2$s&rdquo;', $battleplan_comment_count, 'comments title', 'battleplan' ) ),
					number_format_i18n( $battleplan_comment_count ?? 0.0 ) , '<span>' . wp_kses_post( get_the_title() ) . '</span>'
				);
			}
			?>
		</h2><!-- .comments-title -->

		<?php the_comments_navigation(); ?>

		<ul class="comment-list">
			<?php wp_list_comments( 'type=comment&&max_depth=2&callback=battleplan_comment_structure' );	?>
		</ul><!-- .comment-list -->

		<?php
		the_comments_navigation();

		if ( ! comments_open() ) : ?>
			<p class="no-comments"><?php esc_html_e( 'Comments are closed.', 'battleplan' ); ?></p>
			<?php
		endif;

	endif; // Check for have_comments().

	$comments_args = array(
        'title_reply' => __( 'Write a Comment', 'battleplan' ),
        'comment_notes_after' => '',
		'fields' => array(
			'author' => '<p class="comment-form-author"><label for="author">' . _x( 'Your Name', 'battleplan' ) . '</label><br><input type="text" id="author" name="author" aria-required="true"></input></p>',
			'email' => '<p class="comment-form-email"><label for="email">' . _x( 'Your Email', 'battleplan' ) . '</label><br><input type="text" id="email" name="email"></input></p>',
			'url' => '<p class="comment-form-url"><label for="url">' . _x( 'Your Website', 'battleplan' ) . '</label><br><input type="text" id="url" name="url"></input></p>',
		),
        'comment_field' => '<p class="comment-form-comment"><label for="comment">' . _x( 'Your Comment', 'battleplan' ) . '</label><br><textarea id="comment" name="comment" aria-required="true"></textarea></p>',
        'label_submit' => __( 'Submit', 'battleplan' ),
	);
	comment_form( $comments_args );
?>

</div><!-- #comments -->