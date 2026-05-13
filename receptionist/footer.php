<?php
/**
 * receptionist/footer.php
 * Closes the <main> opened by receptionist/header.php.
 * Loads common.js and optional $extraJs from the page.
 *
 * Usage:
 *   $extraJs = <<<'JS'
 *       // page-specific JS
 *   JS;
 *   require_once BASE_PATH . '/receptionist/footer.php';
 */
?>

</main><!-- /.main-content -->

</div><!-- /.app-shell -->

<!-- Common JS -->
<script src="<?= BASE_URL ?>/assets/js/common.js"></script>

<?php if (!empty($extraJs)): ?>
<script>
<?= $extraJs ?>
</script>
<?php endif; ?>

</body>
</html>
