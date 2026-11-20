<?php /** @var Database $db */ ?>

</div>

<div id="footer">
  <?php echo APP_NAME ?> <?php echo APP_VERSION ?>
  &middot; vygenerováno za <?php echo number_format(microtime(true) - $time_start, 2, ',', ' ') ?> s
  &middot; SQL dotazů: <?php echo $db->query_count ?>
</div>

<script><?php echo Minify::file(ROOT . '/js/app.js') ?></script>
</body>
</html>
