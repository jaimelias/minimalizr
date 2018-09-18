<?php if(get_theme_mod('google_optimize_container_id') != null): ?>
	<!-- Google Optimize -->
	<style>.async-hide { opacity: 0 !important} </style>
	<script>(function(a,s,y,n,c,h,i,d,e){s.className+=' '+y;h.start=1*new Date;
	h.end=i=function(){s.className=s.className.replace(RegExp(' ?'+y),'')};
	(a[n]=a[n]||[]).hide=h;setTimeout(function(){i();h.end=null},c);h.timeout=c;
	})(window,document.documentElement,'async-hide','dataLayer',4000,
	{'<?php echo esc_html(get_theme_mod('google_optimize_container_id')); ?>':true});</script>
	<!-- Google Optimize -->
<?php endif; ?>

<?php if(get_theme_mod('tagmanager_container_id') == null && get_theme_mod('analytics_tracking_id') != null): ?>
	<!-- Google Analytics -->
	<script>
	  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
	  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
	  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
	  })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

	  ga('create', '<?php echo esc_html(get_theme_mod('analytics_tracking_id')); ?>', 'auto');
	  <?php if(get_theme_mod('google_optimize_container_id') != null): ?>ga('require', '<?php echo esc_html(get_theme_mod('google_optimize_container_id')); ?>');<?php endif; ?>
	  ga('send', 'pageview'); 
	</script>
	<!-- Google Analytics -->
<?php endif; ?>

<?php if(get_theme_mod('tagmanager_container_id') != null): ?>
	<!-- Google Tag Manager -->
	<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
	new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
	j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
	'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
	})(window,document,'script','dataLayer','<?php echo esc_html(get_theme_mod('tagmanager_container_id')); ?>');</script>
	<!-- End Google Tag Manager -->	
<?php endif; ?>
