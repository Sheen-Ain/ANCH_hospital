<?php
/**
 * admin/footer.php
 * Included at the very bottom of every admin page.
 * Closes the <main> opened by header.php, then loads all JS.
 *
 * Page-specific inline scripts go AFTER this include
 * by using the $extraJs variable:
 *
 *   $extraJs = <<<'JS'
 *       // page-specific JS here
 *   JS;
 *   require_once BASE_PATH . '/admin/footer.php';
 *
 * Or simply include additional <script> tags after the include.
 */
?>

</main><!-- /.main-content -->

</div><!-- /.app-shell -->

<!-- ═══════════════════════════════════════════════════════════
     JAVASCRIPT
     ═══════════════════════════════════════════════════════════ -->

<!-- Common JS (toast, modal, dark mode, ping, clock…) -->
<script src="<?= BASE_URL ?>/assets/js/common.js"></script>

<?php if (!empty($extraJs)): ?>
<!-- Page-specific JS -->
<script>
<?= $extraJs ?>
</script>
<?php endif; ?>

</body>
</html>
