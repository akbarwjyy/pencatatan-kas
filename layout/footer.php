<?php

?>
</div> <!-- .main-content -->
</div> <!-- .wrapper -->
<div class="footer bg-gray-900 text-white py-4 text-center shadow-inner mt-8 w-full flex-shrink-0">
    <p class="text-sm tracking-wide">&copy; <?php echo date("Y"); ?> Industri Rumah Tangga Ampyang Cap Garuda. All rights reserved.</p>
</div>
</div> <!-- .flex-1 -->
</div> <!-- .flex -->

<script src="<?php echo get_relative_path_to_root(); ?>assets/js/script.js"></script>
<?php
// Flush output buffer at the end
if (ob_get_level()) {
    ob_end_flush();
}
?>
</body>

</html>