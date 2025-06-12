

<script>
    function toggleSidebar() {
        document.getElementById("sidebar").classList.toggle("active");
    }

    function toggleDescription(id) {
        const description = document.getElementById('description-' + id);
        if (description.style.display === 'none') {
            description.style.display = 'block';
        } else {
            description.style.display = 'none';
        }
    }

    function toggleInfo(id) {
        const info = document.getElementById('info-' + id);
        if (info.style.display === 'none') {
            info.style.display = 'block';
        } else {
            info.style.display = 'none';
        }
    }
</script>