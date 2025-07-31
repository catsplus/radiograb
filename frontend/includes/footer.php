    </div> <!-- End container -->

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/radiograb.js"></script>
    <?php if (isset($additional_js)): ?>
        <?= $additional_js ?>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="bg-light mt-5 py-3">
        <div class="container">
            <div class="row">
                <div class="col text-center text-muted">
                                        <small>
                            &copy; 2025 <a href="https://svaha.com" target="_blank" rel="noopener noreferrer">Svaha LLC</a>. All rights reserved. | <a href="https://github.com/mattbaya/radiograb" target="_blank" rel="noopener noreferrer">RadioGrab (MIT License)</a> | Version: <a href="https://github.com/mattbaya/radiograb/releases/tag/<?= getVersionNumber() ?>" target="_blank" rel="noopener noreferrer"><?= getVersionNumber() ?></a><br>Questions? Let us know: <a href="mailto:service@svaha.com">service@svaha.com</a>
                        </small>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>