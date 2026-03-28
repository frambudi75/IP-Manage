            </div>
            
            <footer class="app-footer">
                <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <span>&copy; <?php echo date('Y'); ?> <b><?php echo APP_NAME; ?></b> — Developed by </span>
                    <a href="https://github.com/frambudi75" target="_blank" style="display: flex; align-items: center; gap: 5px; color: var(--primary);">
                         Habib Frambudi
                        <i data-lucide="github" style="width: 14px;"></i>
                    </a>
                </div>
            </footer>
        </main>
    </div>
    <script>
        lucide.createIcons();
        
        // Mobile Sidebar Toggle
        const menuBtn = document.getElementById('menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        if (menuBtn && sidebar) {
            menuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.toggle('active');
            });
            
            document.addEventListener('click', (e) => {
                if (!sidebar.contains(e.target) && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            });
        }
    </script>
</body>
</html>
