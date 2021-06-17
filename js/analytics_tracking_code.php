<?php

	$analytics_tracking_id = get_theme_mod('analytics_tracking_id');

	if($analytics_tracking_id != null): ?>
	<!-- Google Analytics -->	
	<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_html($analytics_tracking_id); ?>"></script>
	<script>
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag('js', new Date());
		gtag('config', '<?php echo esc_html($analytics_tracking_id); ?>');
	</script>
	<!-- Google Analytics -->
<?php endif; ?>