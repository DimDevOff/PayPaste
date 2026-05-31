    </div>

<script nonce="<?= csp_nonce() ?>">
document.addEventListener('submit', function(e) {
    if (e.target.classList.contains('form-confirm-delete-user')) {
        if (!confirm('Видалення є незворотнім. Видалити користувача?')) {
            e.preventDefault();
        }
    }
});
</script>

</body>
</html>
