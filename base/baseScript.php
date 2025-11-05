

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

    function toggleActions(id) {
        const actions = document.getElementById('actions-' + id);
        if (actions.style.display === 'none') {
            actions.style.display = 'block';
        } else {
            actions.style.display = 'none';
        }
    }
    function toggleDisciplines(id) {
        const disciplines = document.getElementById('disciplines-' + id);
        if (disciplines.style.display === 'none') {
            disciplines.style.display = 'block';
        } else {
            disciplines.style.display = 'none';
        }
    }

</script>