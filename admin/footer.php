<!-- footer.php -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('mobileSidebar');
    const toggleBtn = document.getElementById('toggleSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.add('show');
            overlay.classList.add('show');
        });
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
        sidebar.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 991) {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                }
            });
        });
    }
});
</script>
</body>
</html>