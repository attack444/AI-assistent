<div id="ai-buddy-feedback-modal" class="ai-buddy-feedback-modal">
	<div class="feedback-modal-content">
		<span class="feedback-modal-close">&times;</span>
		<span class="feedback-thank-you" style="display: none;">
			<img src="<?php echo esc_url( AI_BUDDY_FILES_PATH . '/assets/images/feedback-thank-you.svg' ); ?>">
		</span>
		<h2><?php echo esc_html__( 'Please leave a Feedback', 'aibuddy-openai-chatgpt' ); ?></h2>

		<div class="feedback-rating-stars">
			<ul id="feedback-stars">
				<li class="star selected" title="Poor" data-value="1">
					<i class="feedback-star"></i>
				</li>
				<li class="star selected" title="Bad" data-value="2">
					<i class="feedback-star"></i>
				</li>
				<li class="star selected" title="Fair" data-value="3">
					<i class="feedback-star"></i>
				</li>
				<li class="star selected" title="Good" data-value="4">
					<i class="feedback-star"></i>
				</li>
				<li class="star selected" title="Excellent!" data-value="5">
					<i class="feedback-star"></i>
				</li>
			</ul>
			<span class="rating-text"><?php echo esc_html__( 'Excellent!', 'aibuddy-openai-chatgpt' ); ?></span>
		</div>

		<p class="feedback-review-text" style="display: none;"></p>
		<div class="feedback-extra">
			<textarea id="feedback-review" rows="5" placeholder="<?php echo esc_html__( 'Please enter your Review...', 'aibuddy-openai-chatgpt' ); ?>"></textarea>
			<small><?php echo esc_html__( 'Found a bug in the plugin?', 'aibuddy-openai-chatgpt' ); ?> <a href="<?php echo esc_url( 'https://aibudwp.com/contact/' ); ?>" target="_blank" rel="nofollow"><?php echo esc_html__( 'Click here', 'aibuddy-openai-chatgpt' ); ?></a>
				<?php echo esc_html__( 'to report it.', 'aibuddy-openai-chatgpt' ); ?></small>
		</div>
		<a href="https://wordpress.org/support/plugin/aibuddy-openai-chatgpt/reviews/?filter=5#new-post" class="feedback-submit" target="_blank" rel="nofollow">
			<?php echo esc_html__( 'Submit', 'aibuddy-openai-chatgpt' ); ?>
			<img src="<?php echo esc_url( AI_BUDDY_FILES_PATH . '/assets/images/feedback-external-link.svg' ); ?>">
		</a>
	</div>
</div>